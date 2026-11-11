<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTPS enforcement (R36.1).
 *
 * The first link in every route pipeline. The platform serves all traffic over
 * HTTPS; a request that is not secure is rejected before any tenancy, auth, or
 * RBAC work happens so that credentials and tenant data never travel in the
 * clear.
 *
 * The guard is intentionally relaxed in local/testing environments and for
 * requests already recognised as secure (including those terminated at a trusted
 * proxy/load balancer that forwards `X-Forwarded-Proto: https`). Laravel's
 * {@see Request::isSecure()} already honours trusted proxies, so no additional
 * header parsing is needed here.
 *
 * On failure the request is aborted with HTTP 426 ("Upgrade Required") which the
 * centralized API exception renderer wraps in the standard
 * `{ "error": { code, message, details } }` envelope.
 */
class EnforceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldEnforce() || $request->isSecure()) {
            return $next($request);
        }

        abort(426, 'All traffic must be served over HTTPS.');
    }

    /**
     * HTTPS is enforced everywhere except local development and the test suite,
     * where TLS termination is typically unavailable.
     */
    private function shouldEnforce(): bool
    {
        return ! app()->environment('local', 'testing');
    }
}
