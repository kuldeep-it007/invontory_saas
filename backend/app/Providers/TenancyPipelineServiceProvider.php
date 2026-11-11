<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\EnforceHttps;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;

/**
 * Pins HTTPS enforcement ahead of tenancy identification in the request
 * pipeline.
 *
 * stancl/tenancy's own service provider promotes its identification middleware
 * (PreventAccessFromCentralDomains, InitializeTenancyBy*) to the highest
 * middleware priority during boot so tenancy is established before downstream
 * middleware. HTTPS enforcement (R36.1) must run even earlier — before any
 * tenant is resolved or the database connection is switched — so this provider
 * prepends {@see EnforceHttps} to the Kernel's middleware priority list.
 *
 * This provider is registered AFTER TenancyServiceProvider in
 * bootstrap/providers.php so its priority adjustment wins: Laravel sorts a
 * route's middleware by this list, so EnforceHttps ends up first regardless of
 * tenancy's promotion.
 *
 * EnforceHttps is attached as route middleware (not a global middleware), so it
 * runs after the framework's TrustProxies global middleware — meaning
 * Request::isSecure() correctly reflects TLS terminated at a trusted proxy or
 * load balancer.
 */
class TenancyPipelineServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /** @var Kernel $kernel */
        $kernel = $this->app->make(Kernel::class);

        $kernel->prependToMiddlewarePriority(EnforceHttps::class);
    }
}
