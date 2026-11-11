<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\SubscriptionGateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Feature-flag guard for optional-module routes (R5.4).
 *
 * Parameterized middleware mounted on endpoints that belong to an optional
 * module gated by a per-plan Feature_Flag (e.g. `exports`, `advance_orders`,
 * `api_access`, `whatsapp_notifications`, `otp_login`, `ai_reports`). When the
 * flag is disabled on the active tenant's plan, the request is denied with HTTP
 * 403 before the controller runs.
 *
 * Usage (route middleware with the flag as a parameter):
 *
 *     Route::post('/reports/export', ...)
 *         ->middleware('feature:exports');
 *
 * This is the feature-flag portion of the pipeline's "Subscription + quota
 * guard" step (design: "Request Pipeline / Middleware Order", step 5). It runs
 * AFTER tenancy initialization so `tenant('id')` is available, and the flag is
 * read from the central database via {@see SubscriptionGateService}.
 *
 * NOTE: the broader AI gate (R30.6/R30.7) additionally requires the
 * `AI_REPORTS_ENABLED` env master switch; that composite gate is implemented
 * with the AI layer (task 11.x) and layers the env check on top of this flag
 * guard rather than duplicating it.
 */
class EnsureFeatureEnabled
{
    public function __construct(private readonly SubscriptionGateService $gate) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        // Throws FeatureDisabledException (403) when the flag is disabled (or no
        // plan resolves); the centralized ApiExceptionRenderer maps it to the
        // error envelope.
        $this->gate->assertFeatureEnabled($feature);

        return $next($request);
    }
}
