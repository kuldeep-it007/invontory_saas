<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Gives every request a correlation id (reusing a valid incoming
 * `X-Request-Id`), records it and the client IP in Laravel's Context so every
 * log line and queued job carries them, returns it as `X-Request-Id`, and
 * writes one access line per API request to the archive log.
 */
class AssignRequestContext
{
    /** Paths not worth an access line (health checks, the Telescope UI itself). */
    private const SKIP = ['up', 'api/telescope', 'api/telescope/*'];

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = (string) $request->headers->get('X-Request-Id', '');
        $requestId = preg_match('/^[A-Za-z0-9._-]{8,128}$/', $incoming) === 1 ? $incoming : (string) Str::uuid();

        Context::add('request_id', $requestId);
        Context::add('ip', $request->ip());
        $request->attributes->set('request_started_at', microtime(true));

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! config('logging.archive.access_log') || $request->is(...self::SKIP)) {
            return;
        }

        try {
            $started = (float) $request->attributes->get('request_started_at', microtime(true));
            Log::channel('archive')->info('http.request', [
                'event' => 'http.request',
                'method' => $request->method(),
                'path' => '/'.ltrim($request->path(), '/'),
                'host' => $request->getHost(),
                'status' => $response->getStatusCode(),
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            ]);
        } catch (Throwable) {
            // Never let access logging affect the response.
        }
    }
}
