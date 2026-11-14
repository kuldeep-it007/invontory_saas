<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Domain;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Bridge between the domain table and the host-side certbot script
 * (deploy/custom-domains.sh). The app container cannot run certbot or reload
 * nginx, so the script asks what to do ({@see work()}) and reports back
 * ({@see report()}). The app never executes anything from that exchange.
 */
class CustomDomainSslService
{
    /**
     * What the host script should do now.
     *
     * `keep`  every active custom host that should have a certificate and an nginx
     *         server block (anything else found on disk is removed).
     * `issue` the subset that needs a certificate requested now: DNS already
     *         routes to this server, and no recent failed attempt is cooling down.
     *
     * @return array{keep: list<string>, issue: list<string>}
     */
    public function work(): array
    {
        $active = Domain::query()->active()->custom()->orderBy('id')->get();

        $issue = $active->filter(fn (Domain $domain): bool => $domain->routing_ok
            && in_array($domain->ssl_status, [Domain::SSL_NONE, Domain::SSL_PENDING, Domain::SSL_FAILED], true)
            && $this->isDueForIssuance($domain));

        return [
            'keep' => $active->pluck('domain')->all(),
            'issue' => $issue->pluck('domain')->values()->all(),
        ];
    }

    /**
     * Record certificate outcomes from the host script. Unknown hosts and
     * unexpected statuses are ignored, so the input is never trusted.
     *
     * @param  list<array{host?: mixed, status?: mixed, expires?: mixed}>  $results
     */
    public function report(array $results): int
    {
        $applied = 0;

        foreach ($results as $result) {
            $host = is_string($result['host'] ?? null) ? strtolower($result['host']) : '';
            $status = $result['status'] ?? null;

            $domain = Domain::query()->active()->custom()->where('domain', $host)->first();
            if ($domain === null || ! in_array($status, [Domain::SSL_ISSUED, Domain::SSL_FAILED], true)) {
                continue;
            }

            $domain->forceFill([
                'ssl_status' => $status,
                'ssl_checked_at' => now(),
                'ssl_attempts' => $status === Domain::SSL_ISSUED ? 0 : $domain->ssl_attempts + 1,
                'ssl_expires_at' => $status === Domain::SSL_ISSUED ? $this->parseDate($result['expires'] ?? null) : null,
            ])->save();

            $applied++;
        }

        return $applied;
    }

    private function isDueForIssuance(Domain $domain): bool
    {
        if ($domain->ssl_status !== Domain::SSL_FAILED) {
            return true;
        }

        if ($domain->ssl_attempts >= (int) config('domains.ssl.max_attempts')) {
            return false;
        }

        $wait = min(86400, $domain->ssl_attempts * (int) config('domains.ssl.retry_backoff'));

        return $domain->ssl_checked_at === null || $domain->ssl_checked_at->lte(now()->subSeconds($wait));
    }

    private function parseDate(mixed $value): ?Carbon
    {
        try {
            return is_string($value) && $value !== '' ? Carbon::parse($value) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
