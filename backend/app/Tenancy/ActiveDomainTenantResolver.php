<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Domain;
use App\Support\HostName;
use Stancl\Tenancy\Contracts\Tenant;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;

/**
 * Resolves a tenant from the request host, and only through an ACTIVE domain
 * row. Platform subdomains, tenant subdomains, and custom domains all take this
 * one path, so no tenant or host is ever special-cased.
 *
 * Two stored forms are accepted, because both exist in real data:
 *
 *   - the full host (`acme.example.com`, `shop.customer.com`): matched exactly;
 *   - a bare subdomain label (`acme`): what provisioning stores when no explicit
 *     domain is given. It matches `<label>.<central domain>` for EVERY central
 *     domain, which is how the previous subdomain middleware behaved. Only
 *     platform rows match this way, and only for a direct child of a central
 *     domain, so a custom domain can never be reached through it.
 *
 * A pending, failed, or disabled domain never resolves, which is what keeps an
 * unverified claim from serving (or spoofing) another company's host.
 */
class ActiveDomainTenantResolver extends DomainTenantResolver
{
    public function resolveWithoutCache(...$args): Tenant
    {
        $host = (string) $args[0];
        $label = $this->platformLabel($host);

        $domain = Domain::query()
            ->active()
            ->where(function ($query) use ($host, $label): void {
                $query->where('domain', $host);
                if ($label !== null) {
                    $query->orWhere(fn ($legacy) => $legacy->where('domain', $label)->where('type', Domain::TYPE_PLATFORM));
                }
            })
            // An exact full-host row wins over a bare label.
            ->orderByRaw('CASE WHEN domain = ? THEN 0 ELSE 1 END', [$host])
            ->with('tenant.domains')
            ->first();

        if ($domain === null || $domain->tenant === null) {
            throw new TenantCouldNotBeIdentifiedOnDomainException($host);
        }

        static::$currentDomain = $domain;

        return $domain->tenant;
    }

    /**
     * The leftmost label when $host is exactly one level below a central domain
     * (`acme.localhost` -> `acme`), otherwise null.
     */
    private function platformLabel(string $host): ?string
    {
        foreach ((array) config('tenancy.central_domains') as $central) {
            $central = strtolower((string) $central);
            if ($central === '' || ! HostName::isSameOrSubdomainOf($host, $central) || $host === $central) {
                continue;
            }

            $label = substr($host, 0, -(strlen($central) + 1));
            if ($label !== '' && ! str_contains($label, '.')) {
                return $label;
            }
        }

        return null;
    }
}
