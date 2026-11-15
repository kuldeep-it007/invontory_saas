<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Domain;
use App\Models\PlatformAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
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
     *
enew hosts a Platform_Admin asked to renew: requested now, even if a valid
     *         certificate exists and regardless of earlier failures.
     *
     * @return array{keep: list<string>, issue: list<string>, renew: list<string>}
     */
    public function work(): array
    {
        $active = Domain::query()->active()->custom()->orderBy('id')->get();

        $renew = $active->filter(fn (Domain $domain): bool => $domain->routing_ok && $domain->ssl_renew_requested_at !== null);

        $issue = $active->filter(fn (Domain $domain): bool => $domain->routing_ok
            && $domain->ssl_renew_requested_at === null
            && in_array($domain->ssl_status, [Domain::SSL_NONE, Domain::SSL_PENDING, Domain::SSL_FAILED], true)
            && $this->isDueForIssuance($domain));

        return [
            'keep' => $active->pluck('domain')->all(),
            'issue' => $issue->pluck('domain')->values()->all(),
            'renew' => $renew->pluck('domain')->values()->all(),
        ];
    }

    /**
     * Record certificate outcomes from the host script. Unknown hosts and
     * unexpected statuses are ignored, so the input is never trusted.
     *
     * A result flagged `renew: true` closes a Platform_Admin renewal request,
     * whatever its outcome, so a failing renewal is not retried forever.
     *
     * @param  list<array{host?: mixed, status?: mixed, expires?: mixed, renew?: mixed}>  $results
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
            ]);

            if (($result['renew'] ?? false) === true) {
                $domain->ssl_renew_requested_at = null;
            }

            $domain->save();

            $applied++;
        }

        return $applied;
    }

    /**
     * Platform_Admin: queue a forced certificate renewal for a custom domain.
     * The host script renews it on its next run (within about two minutes),
     * ignoring the retry backoff. Tenants cannot call this.
     *
     * @throws ConflictHttpException When the domain cannot be renewed right now.
     */
    public function requestRenewal(Domain $domain, ?Request $request = null): Domain
    {
        if (! $domain->isCustom()) {
            throw new ConflictHttpException('Platform addresses use the platform certificate and cannot be renewed here.');
        }

        if (! $domain->isActive()) {
            throw new ConflictHttpException('Only an active domain can get a certificate. Verify or enable it first.');
        }

        if (! $domain->routing_ok) {
            throw new ConflictHttpException('The domain does not point at this server yet, so a certificate cannot be issued. Ask the client to fix the routing record.');
        }

        if ($domain->ssl_renew_requested_at !== null) {
            throw new ConflictHttpException('A renewal is already queued for this domain. It runs within a few minutes.');
        }

        $before = $domain->only(['ssl_status', 'ssl_attempts', 'ssl_expires_at']);

        $domain->forceFill([
            'ssl_renew_requested_at' => now(),
            'ssl_status' => Domain::SSL_PENDING,
            'ssl_attempts' => 0,
            'ssl_checked_at' => null,
        ])->save();

        try {
            PlatformAuditLog::record(
                'domain.ssl_renew_requested',
                $request,
                $domain->tenant_id,
                ['domain' => $domain->domain] + $before,
                ['domain' => $domain->domain, 'ssl_status' => Domain::SSL_PENDING],
            );
        } catch (Throwable $e) {
            // The audit trail must not block the renewal; the log keeps a record.
            report($e);
        }

        Log::info('custom_domain.ssl_renew_requested', [
            'domain' => $domain->domain,
            'tenant_id' => $domain->tenant_id,
            'actor_id' => $request?->user()?->getKey(),
        ]);

        return $domain;
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
