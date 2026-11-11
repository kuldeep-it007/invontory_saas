<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\SubscriptionGateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Composite AI-reporting gate for the AI reporting routes (R30.6, R30.7).
 *
 * Unlike the plain `feature:<flag>` guard — which checks only a single per-plan
 * Feature_Flag — AI reporting carries a COMPOSITE gate: it is permitted if and
 * only if BOTH the platform-wide `AI_REPORTS_ENABLED` env master switch is on
 * (R30.6, default off per R30.1) AND the active tenant's `ai_reports` feature
 * flag is enabled on its plan (R30.7). Either one off denies the request with a
 * 403 before the controller runs.
 *
 * The conjunction itself lives in {@see SubscriptionGateService::assertAiReportingEnabled()}
 * so the gate is defined in exactly one place (reused by the service as defence
 * in depth); this middleware simply mounts it on the route pipeline, mirroring
 * how {@see EnsureFeatureEnabled} mounts the single-flag guard.
 *
 * Usage (route middleware):
 *
 *     Route::post('/ai/reports', ...)->middleware('ai.enabled');
 */
class EnsureAiReportingEnabled
{
    public function __construct(private readonly SubscriptionGateService $gate) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Throws FeatureDisabledException (403) when the master switch OR the
        // tenant `ai_reports` flag is off; the centralized ApiExceptionRenderer
        // maps it to the standard error envelope.
        $this->gate->assertAiReportingEnabled();

        return $next($request);
    }
}
