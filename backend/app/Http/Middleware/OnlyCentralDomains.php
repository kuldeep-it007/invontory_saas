<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 404 unless the request is on a platform (central) domain. Keeps platform
 * tools such as Telescope off every tenant and custom domain.
 */
class OnlyCentralDomains
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array(strtolower($request->getHost()), array_map('strtolower', (array) config('tenancy.central_domains')), true)) {
            abort(404);
        }

        return $next($request);
    }
}
