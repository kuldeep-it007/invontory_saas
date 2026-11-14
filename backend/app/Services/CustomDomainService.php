<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\FeatureDisabledException;
use App\Exceptions\QuotaExceededException;
use App\Models\Domain;
use App\Models\PlanFeature;
use App\Models\PlanLimit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Dns\DnsResolver;
use App\Support\HostName;
use App\Tenancy\ActiveDomainTenantResolver;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Exceptions\DomainOccupiedByOtherTenantException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

/**
 * Custom domain lifecycle: add, verify DNS, activate, re-check, remove.
 *
 * Ownership is proven with a TXT record containing a per-claim random token
 * (control of the domain's DNS). Traffic routing is checked separately
 * (CNAME to the platform target host, or A record to its IPs) and is needed
 * for HTTPS issuance, not for activation. Only `active` rows resolve to a
 * tenant, see {@see ActiveDomainTenantResolver}.
 */
class CustomDomainService
{
    /** Second-level labels under which a registrable domain has three labels (example.co.uk). */
    private const COMMON_SECOND_LEVEL = ['co', 'com', 'org', 'net', 'gov', 'ac', 'edu', 'or', 'ne'];

    public function __construct(
        private readonly DnsResolver $dns,
        private readonly SubscriptionGateService $gate,
    ) {}

    /**
     * Start a claim on a host. The claim is `pending` until DNS ownership is verified.
     *
     * @throws ValidationException When the value is not a usable public hostname.
     * @throws ConflictHttpException When the host is already added to this workspace.
     */
    public function add(Tenant $tenant, string $input, ?User $actor = null): Domain
    {
        $host = HostName::normalize($input);

        if ($host === null) {
            throw ValidationException::withMessages([
                'domain' => 'Enter a valid domain name such as shop.example.com (no IP address, port or wildcard).',
            ]);
        }

        if ($this->isReserved($host)) {
            throw ValidationException::withMessages([
                'domain' => 'This domain belongs to the platform and cannot be mapped.',
            ]);
        }

        $this->assertAllowedToAdd($tenant);

        $own = Domain::query()->where('tenant_id', $tenant->getTenantKey())->where('domain', $host)->first();
        if ($own !== null && $own->status !== Domain::STATUS_FAILED) {
            throw new ConflictHttpException('This domain is already added to your workspace.');
        }

        // A host served by another workspace may still be claimed: the claim cannot serve
        // traffic until DNS ownership is proven, and activation then decides (see activate()).
        $own?->delete();

        $domain = Domain::query()->create([
            'domain' => $host,
            'tenant_id' => $tenant->getTenantKey(),
            'type' => Domain::TYPE_CUSTOM,
            'status' => Domain::STATUS_PENDING,
            'verification_token' => Str::random(40),
            'expires_at' => now()->addHours((int) config('domains.pending_ttl_hours')),
            'ssl_status' => Domain::SSL_NONE,
        ]);

        $this->audit('added', $domain, $actor);

        return $domain;
    }

    /**
     * Check DNS for a domain and move it forward. Safe to call repeatedly.
     *
     * Manual calls are rate-limited per domain. A `failed` claim is re-armed by
     * a manual call. An `active` domain is only re-checked and flagged, never
     * switched off here.
     */
    public function verify(Domain $domain, bool $manual = false): Domain
    {
        if (! $domain->isCustom() || $domain->status === Domain::STATUS_DISABLED) {
            return $domain;
        }

        if ($manual && $domain->last_checked_at?->gt(now()->subSeconds((int) config('domains.manual_check_interval')))) {
            return $domain;
        }

        if ($domain->status === Domain::STATUS_FAILED) {
            if (! $manual) {
                return $domain;
            }
            $domain->forceFill([
                'status' => Domain::STATUS_PENDING,
                'expires_at' => now()->addHours((int) config('domains.pending_ttl_hours')),
                'check_attempts' => 0,
            ]);
        }

        $ownership = $this->ownershipState($domain);
        $routing = $this->routesToPlatform($domain->domain);

        $domain->forceFill([
            'last_checked_at' => now(),
            'check_attempts' => $domain->check_attempts + 1,
            'routing_ok' => $routing,
        ]);

        if ($domain->status === Domain::STATUS_PENDING) {
            $this->advancePending($domain, $ownership, $routing);
        } else {
            $domain->last_error = $this->describeProblem($ownership, $routing);
            $this->syncSslStatus($domain);
            $domain->save();
        }

        return $domain;
    }

    /**
     * Remove a custom domain. A tenant must always keep at least one active host.
     *
     * @throws ConflictHttpException
     */
    public function remove(Domain $domain, ?User $actor = null): void
    {
        if (! $domain->isCustom()) {
            throw new ConflictHttpException('The platform address cannot be removed here.');
        }

        if ($domain->isActive() && ! Domain::query()->active()
            ->where('tenant_id', $domain->tenant_id)->whereKeyNot($domain->getKey())->exists()) {
            throw new ConflictHttpException('Your workspace needs at least one active address.');
        }

        $domain->delete();
        $this->audit('removed', $domain, $actor);
    }

    /**
     * Mark (or clear) the canonical host. Other hosts then redirect to it in the browser.
     *
     * @throws ConflictHttpException When the host cannot be served over HTTPS yet.
     */
    public function setPrimary(Domain $domain, bool $primary, ?User $actor = null): Domain
    {
        // A bare label (cme) has no single host to redirect to, so it cannot be primary.
        if ($primary && (! $domain->isActive() || ! $domain->isFullHost() || ($domain->isCustom() && $domain->ssl_status !== Domain::SSL_ISSUED))) {
            throw new ConflictHttpException('Only an active domain with an issued HTTPS certificate can be the primary address.');
        }

        DB::connection('central')->transaction(function () use ($domain, $primary): void {
            Domain::query()->where('tenant_id', $domain->tenant_id)->update(['is_primary' => false]);
            if ($primary) {
                Domain::query()->whereKey($domain->getKey())->update(['is_primary' => true]);
            }
        });

        $this->audit($primary ? 'primary_set' : 'primary_cleared', $domain, $actor);

        return $domain->refresh();
    }

    /** Platform admin: stop serving a custom domain immediately. */
    public function disable(Domain $domain, ?User $actor = null): Domain
    {
        if ($domain->isCustom()) {
            $domain->forceFill(['status' => Domain::STATUS_DISABLED, 'is_primary' => false])->save();
            $this->audit('disabled', $domain, $actor);
        }

        return $domain;
    }

    /** Platform admin: allow a disabled domain again; it must re-prove DNS ownership. */
    public function enable(Domain $domain, ?User $actor = null): Domain
    {
        if ($domain->isCustom() && $domain->status === Domain::STATUS_DISABLED) {
            $domain->forceFill([
                'status' => Domain::STATUS_PENDING,
                'expires_at' => now()->addHours((int) config('domains.pending_ttl_hours')),
                'check_attempts' => 0,
            ])->save();
            $this->audit('enabled', $domain, $actor);
        }

        return $domain;
    }

    /**
     * Whether $host may never be claimed: the platform's own domains and any
     * subdomain of them (those are provisioned by platform admins only).
     */
    public function isReserved(string $host): bool
    {
        foreach ((array) config('tenancy.central_domains') as $central) {
            if (HostName::isSameOrSubdomainOf($host, (string) $central)) {
                return true;
            }
        }

        return HostName::isSameOrSubdomainOf($host, (string) config('domains.target_host'));
    }

    /**
     * API/UI shape for a domain, including the DNS records the customer must add.
     *
     * @return array<string, mixed>
     */
    public function describe(Domain $domain): array
    {
        return [
            'id' => $domain->getKey(),
            'tenant_id' => $domain->tenant_id,
            'domain' => $domain->domain,
            'type' => $domain->type,
            'status' => $domain->status,
            'routing_ok' => $domain->routing_ok,
            'ssl_status' => $domain->ssl_status,
            'ssl_expires_at' => $domain->ssl_expires_at?->toIso8601String(),
            'is_primary' => $domain->is_primary,
            'verified_at' => $domain->verified_at?->toIso8601String(),
            'last_checked_at' => $domain->last_checked_at?->toIso8601String(),
            'expires_at' => $domain->status === Domain::STATUS_PENDING ? $domain->expires_at?->toIso8601String() : null,
            'last_error' => $domain->last_error,
            'dns_records' => $domain->isCustom() ? $this->dnsRecords($domain) : [],
        ];
    }

    /**
     * DNS records the customer must create, with names for both "full name" and
     * "relative to the zone" panels.
     *
     * @return list<array<string, mixed>>
     */
    public function dnsRecords(Domain $domain): array
    {
        $zone = $this->zoneOf($domain->domain);
        $relative = static fn (string $fqdn): string => $fqdn === $zone ? '@' : substr($fqdn, 0, -(strlen($zone) + 1));
        $isApex = $domain->domain === $zone;

        $records = [[
            'purpose' => 'ownership',
            'type' => 'TXT',
            'name' => $domain->txtRecordName(),
            'host' => $relative($domain->txtRecordName()),
            'value' => $domain->txtRecordValue(),
            'recommended' => true,
        ]];

        $records[] = [
            'purpose' => 'routing',
            'type' => 'CNAME',
            'name' => $domain->domain,
            'host' => $relative($domain->domain),
            'value' => (string) config('domains.target_host'),
            'recommended' => ! $isApex,
        ];

        foreach ((array) config('domains.target_ips') as $ip) {
            $records[] = [
                'purpose' => 'routing',
                'type' => 'A',
                'name' => $domain->domain,
                'host' => $relative($domain->domain),
                'value' => $ip,
                'recommended' => $isApex,
            ];
        }

        return $records;
    }

    private function assertAllowedToAdd(Tenant $tenant): void
    {
        $plan = $this->gate->activePlan($tenant->getTenantKey());

        $flag = $plan?->features->firstWhere('key', PlanFeature::CUSTOM_DOMAINS);
        if ($flag !== null && ! $flag->enabled) {
            throw new FeatureDisabledException(PlanFeature::CUSTOM_DOMAINS);
        }

        $limit = $plan?->limitValue(PlanLimit::MAX_CUSTOM_DOMAINS) ?? (int) config('domains.max_per_tenant');
        if (PlanLimit::isUnlimitedValue($limit)) {
            return;
        }

        $count = Domain::query()->custom()
            ->where('tenant_id', $tenant->getTenantKey())
            ->whereIn('status', [Domain::STATUS_PENDING, Domain::STATUS_ACTIVE, Domain::STATUS_DISABLED])
            ->count();

        if ($count >= $limit) {
            throw new QuotaExceededException(PlanLimit::MAX_CUSTOM_DOMAINS, $count, $limit);
        }
    }

    /**
     * @return 'ok'|'missing'|'mismatch'
     */
    private function ownershipState(Domain $domain): string
    {
        $records = array_map('trim', $this->dns->txt($domain->txtRecordName()));

        if ($records === []) {
            return 'missing';
        }

        foreach ($records as $value) {
            if (hash_equals($domain->txtRecordValue(), $value)) {
                return 'ok';
            }
        }

        return 'mismatch';
    }

    private function routesToPlatform(string $host): bool
    {
        $target = strtolower(rtrim((string) config('domains.target_host'), '.'));

        if (in_array($target, $this->dns->cname($host), true)) {
            return true;
        }

        $ips = (array) config('domains.target_ips') ?: $this->dns->a($target);

        return $ips !== [] && array_intersect($ips, $this->dns->a($host)) !== [];
    }

    private function advancePending(Domain $domain, string $ownership, bool $routing): void
    {
        if ($ownership === 'ok') {
            $this->activate($domain, $routing);

            return;
        }

        if ($domain->expires_at !== null && $domain->expires_at->isPast()) {
            $domain->forceFill([
                'status' => Domain::STATUS_FAILED,
                'last_error' => 'Verification window expired. Check your DNS records and try again.',
            ])->save();

            return;
        }

        $domain->last_error = $this->describeProblem($ownership, $routing);
        $domain->save();
    }

    private function activate(Domain $domain, bool $routing): void
    {
        try {
            DB::connection('central')->transaction(function () use ($domain, $routing): void {
                $this->releaseHostFromStaleHolder($domain);

                // Competing unverified claims on this host lose to the verified one.
                Domain::query()
                    ->where('domain', $domain->domain)
                    ->whereKeyNot($domain->getKey())
                    ->where('status', Domain::STATUS_PENDING)
                    ->get()
                    ->each->delete();

                $domain->forceFill([
                    'status' => Domain::STATUS_ACTIVE,
                    'verified_at' => now(),
                    'expires_at' => null,
                    'last_error' => $routing ? null : $this->describeProblem('ok', false),
                    'ssl_status' => Domain::SSL_PENDING,
                    'ssl_attempts' => 0,
                ])->save();
            });
        } catch (Throwable $e) {
            if (! $e instanceof DomainOccupiedByOtherTenantException && ! $e instanceof UniqueConstraintViolationException) {
                throw $e;
            }

            $domain->refresh()->forceFill([
                'status' => Domain::STATUS_FAILED,
                'last_error' => 'This domain is already connected to another workspace.',
            ])->save();

            return;
        }

        $this->audit('activated', $domain);
    }

    /**
     * If another workspace holds this host as ACTIVE but its ownership record is
     * gone (the domain changed hands), the verified new owner takes it over.
     */
    private function releaseHostFromStaleHolder(Domain $claimant): void
    {
        $holder = Domain::query()->active()->where('domain', $claimant->domain)
            ->where('tenant_id', '!=', $claimant->tenant_id)->lockForUpdate()->first();

        if ($holder === null) {
            return;
        }

        if ($holder->isCustom() && $this->ownershipState($holder) !== 'ok') {
            $holder->forceFill([
                'status' => Domain::STATUS_FAILED,
                'is_primary' => false,
                'last_error' => 'Domain ownership moved to another workspace.',
            ])->save();
            $this->audit('ownership_transferred', $holder);
        }
    }

    private function syncSslStatus(Domain $domain): void
    {
        if ($domain->isActive() && $domain->ssl_status === Domain::SSL_NONE) {
            $domain->ssl_status = Domain::SSL_PENDING;
        }
    }

    private function describeProblem(string $ownership, bool $routing): ?string
    {
        if ($ownership === 'missing') {
            return 'Ownership TXT record not found yet. DNS changes can take a while to propagate.';
        }

        if ($ownership === 'mismatch') {
            return 'A TXT record exists but its value does not match. Copy the value exactly.';
        }

        return $routing ? null : 'Routing record not found. Point the domain at the address shown so HTTPS can be issued.';
    }

    private function zoneOf(string $host): string
    {
        $labels = explode('.', $host);
        $count = count($labels);

        if ($count <= 2) {
            return $host;
        }

        $secondLevel = $labels[$count - 2];
        $keep = (strlen($labels[$count - 1]) === 2 && in_array($secondLevel, self::COMMON_SECOND_LEVEL, true)) ? 3 : 2;

        return implode('.', array_slice($labels, -$keep));
    }

    private function audit(string $event, Domain $domain, ?User $actor = null): void
    {
        try {
            Log::channel(config('logging.default'))->info('custom_domain.'.$event, [
                'event' => 'custom_domain.'.$event,
                'tenant_id' => $domain->tenant_id,
                'domain' => $domain->domain,
                'domain_id' => $domain->getKey(),
                'actor_id' => $actor?->getKey(),
                'actor_email' => $actor?->email,
                'at' => now()->toIso8601String(),
            ]);
        } catch (Throwable) {
            // Logging must never break the domain operation.
        }
    }
}
