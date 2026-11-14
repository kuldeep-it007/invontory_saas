<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\HostName;
use App\Tenancy\ActiveDomainTenantResolver;
use Closure;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

/**
 * Identifies the tenant from the incoming Host header and switches the
 * connection to that tenant's database (R1.2).
 *
 * Replaces stancl's "domain or subdomain" middleware. That one guessed whether
 * a host was a platform subdomain (suffix match, then looked up only the first
 * label) or a custom domain. Here every host, whatever its shape, is looked up
 * verbatim in the `domains` table via {@see ActiveDomainTenantResolver}.
 *
 * The host is normalised first, so `Shop.Example.com.` and `shop.example.com`
 * are the same lookup. A host that is not a valid public DNS name (IP address,
 * single label, malformed) is rejected like any unknown tenant. The shared
 * `$onFail` handler (AppServiceProvider) turns that into the standard 403.
 */
class ResolveTenantByHost extends InitializeTenancyByDomain
{
    public function handle($request, Closure $next)
    {
        $host = HostName::normalize($request->getHost());

        if ($host === null) {
            $onFail = static::$onFail ?? fn () => abort(403, 'The request could not be matched to a tenant.');

            return $onFail(null, $request, $next);
        }

        return $this->initializeTenancy($request, $next, $host);
    }
}
