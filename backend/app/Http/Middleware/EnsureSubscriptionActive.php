<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\SubscriptionGateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Subscription-status gate for resource-creation routes (R5.7).
 *
 * Attached to tenant routes that CREATE a new tenant-owned resource. When the
 * active tenant's subscription is `past_due` or `cancelled`, the request is
 * denied with HTTP 403 before the controller runs; `active` and `trial`
 * subscriptions pass through.
 *
 * This is the subscription-status portion of the pipeline's "Subscription +
 * quota guard" step (design: "Request Pipeline / Middleware Order", step 5). It
 * runs AFTER tenancy initialization so `tenant('id')` is available, and the
 * gate reads the subscription from the central database via
 * {@see SubscriptionGateService}.
 *
 * Mount this guard only on creation (write) endpoints — reads and updates are
 * not gated by subscription status under R5.7, which speaks specifically to the
 * "creation of new tenant-owned resources".
 */
class EnsureSubscriptionActive
{
    public function __construct(private readonly SubscriptionGateService $gate) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Throws SubscriptionInactiveException (403) when past_due/cancelled;
        // the centralized ApiExceptionRenderer maps it to the error envelope.
        $this->gate->assertResourceCreationAllowed();

        return $next($request);
    }
}
