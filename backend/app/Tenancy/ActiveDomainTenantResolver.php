<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Domain;
use Stancl\Tenancy\Contracts\Tenant;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;

/**
 * Resolves a tenant from the FULL request host, and only through an ACTIVE
 * domain row. Platform subdomains, tenant subdomains, and custom domains all
 * take this one path, so no tenant or host is ever special-cased.
 *
 * A pending, failed, or disabled domain never resolves, which is what keeps an
 * unverified claim from serving (or spoofing) another company's host.
 */
class ActiveDomainTenantResolver extends DomainTenantResolver
{
    public function resolveWithoutCache(...$args): Tenant
    {
        $host = (string) $args[0];

        $domain = Domain::query()
            ->active()
            ->where('domain', $host)
            ->with('tenant.domains')
            ->first();

        if ($domain === null || $domain->tenant === null) {
            throw new TenantCouldNotBeIdentifiedOnDomainException($host);
        }

        static::$currentDomain = $domain;

        return $domain->tenant;
    }
}
