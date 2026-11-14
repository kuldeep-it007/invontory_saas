<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\EnforceHttps;
use App\Http\Middleware\EnsureAiReportingEnabled;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\ReconcileTenantUser;
use App\Http\Middleware\ResolveTenantByHost;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
        |------------------------------------------------------------------
        | Trusted proxies
        |------------------------------------------------------------------
        | TLS is terminated at a reverse proxy (nginx) in production and the
        | request reaches PHP over plain HTTP with `X-Forwarded-Proto: https`.
        | Without trusting that proxy, Request::isSecure() is false and the
        | EnforceHttps middleware (R36.1) would reject EVERY request with 426,
        | and the original tenant host (X-Forwarded-Host) would be ignored.
        | The app container is only reachable through the proxy, so trusting
        | all upstream addresses ("*") is the default; set TRUSTED_PROXIES to a
        | comma-separated list of IPs/CIDRs to narrow it.
        */
        $trusted = (string) env('TRUSTED_PROXIES', '*');
        $middleware->trustProxies(
            at: $trusted === '*' ? '*' : array_map('trim', explode(',', $trusted)),
        );

        /*
        |------------------------------------------------------------------
        | Middleware aliases
        |------------------------------------------------------------------
        | Short names for the building blocks of the request pipeline so
        | route groups can compose them in a readable, documented order.
        */
        $middleware->alias([
            // HTTPS enforcement (R36.1) — first link in both pipelines. Runs as
            // route middleware (after the framework's TrustProxies global
            // middleware) so `isSecure()` correctly honours TLS terminated at a
            // trusted proxy. Its priority is pinned ahead of the tenancy
            // identification middleware in TenancyPipelineServiceProvider so it
            // executes before any tenant is resolved.
            'https' => EnforceHttps::class,

            // stancl/tenancy identification + connection switching. Resolves
            // the tenant from the subdomain / custom domain and switches the
            // default DB connection to that tenant's database (R1.2).
            'tenant' => ResolveTenantByHost::class,

            // Rejects tenant routes reached on a central domain (defence in
            // depth alongside the identification middleware).
            'tenant.prevent-central' => PreventAccessFromCentralDomains::class,

            // Post-authentication tenant/user reconciliation (R1.6). Runs AFTER
            // Sanctum and rejects a token stamped for a different tenant than
            // the one resolved for this request (cross-tenant replay) with 403.
            'tenant.reconcile' => ReconcileTenantUser::class,

            // RBAC authorization (spatie) — authorizes the action against the
            // authenticated user's roles/abilities (R8.2, R8.3, R8.4, R36.3).
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,

            // Subscription-status gate (R5.7) — denies creation of new tenant-
            // owned resources with 403 when the active subscription is
            // past_due/cancelled. Mounted per-route on creation endpoints since
            // only resource creation is gated by status.
            'subscription.active' => EnsureSubscriptionActive::class,

            // Feature-flag guard (R5.4) — `feature:<flag>` denies an optional
            // module with 403 when its per-plan Feature_Flag is disabled.
            'feature' => EnsureFeatureEnabled::class,

            // Composite AI-reporting gate (R30.6, R30.7) — `ai.enabled` denies
            // with 403 unless BOTH the `AI_REPORTS_ENABLED` env master switch
            // AND the tenant's `ai_reports` feature flag are on.
            'ai.enabled' => EnsureAiReportingEnabled::class,
        ]);

        /*
        |------------------------------------------------------------------
        | Tenant route pipeline (ordered — order matters for correctness)
        |------------------------------------------------------------------
        | 1. HTTPS enforcement                       (R36.1)
        | 2. Tenant identification + connection swap (R1.2) — reject no-tenant
        | 3. Sanctum authentication                  (R7) — resolved against the
        |    now-active tenant database
        | 4. Tenant/user reconciliation              (R1.6) — reject a token
        |    stamped for a different tenant than the one resolved (defence in
        |    depth over the physical boundary)
        | RBAC (role/permission) is applied per-route via the alias above since
        | the required ability varies by endpoint (R8.2, R36.3).
        */
        $middleware->group('tenant', [
            'https',
            PreventAccessFromCentralDomains::class,
            ResolveTenantByHost::class,
            'auth:sanctum',
            ReconcileTenantUser::class,
        ]);

        /*
        |------------------------------------------------------------------
        | Tenant PUBLIC route pipeline (tenant resolved, NOT authenticated)
        |------------------------------------------------------------------
        | For unauthenticated endpoints that still must run inside a resolved
        | tenant's database — login and password-reset request (R7.1, R7.4):
        | 1. HTTPS enforcement                       (R36.1)
        | 2. Tenant identification + connection swap (R1.2) — reject no-tenant
        | Sanctum is intentionally omitted: the caller has no token yet. Login
        | itself verifies credentials and issues the token.
        */
        $middleware->group('tenant.public', [
            'https',
            PreventAccessFromCentralDomains::class,
            ResolveTenantByHost::class,
        ]);

        /*
        |------------------------------------------------------------------
        | Central platform.admin route pipeline (NO tenancy initialization)
        |------------------------------------------------------------------
        | Runs against the CENTRAL database for cross-tenant/platform work
        | (provisioning, plan management, impersonation setup) (R1.6):
        | 1. HTTPS enforcement      (R36.1)
        | 2. Sanctum authentication (R7)
        | RBAC still authorizes via the per-route `role`/`permission` alias
        | (R8.2, R36.3); the Platform_Admin role gate is applied in the route
        | group definition.
        */
        $middleware->group('platform.admin', [
            'https',
            'auth:sanctum',
        ]);

        /*
        |------------------------------------------------------------------
        | Central platform PUBLIC pipeline (NO tenancy, NOT authenticated)
        |------------------------------------------------------------------
        | For the unified login's CENTRAL path: a Platform_Admin signing in on
        | a central (apex/app) host authenticates against the central `users`
        | table. HTTPS only; no tenancy init, no prior token (login issues it).
        */
        $middleware->group('platform.public', [
            'https',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Render every API / JSON error through the centralized envelope renderer:
        //   { "error": { code, message, details } }
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return app(ApiExceptionRenderer::class)->render($e, $request);
            }

            return null;
        });
    })->create();
