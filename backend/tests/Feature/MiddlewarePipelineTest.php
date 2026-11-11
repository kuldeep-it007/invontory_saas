<?php

declare(strict_types=1);

use App\Http\Middleware\EnforceHttps;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
|--------------------------------------------------------------------------
| Task 1.5 — Middleware pipeline wiring
|--------------------------------------------------------------------------
|
| Verifies the two ordered route-group pipelines defined by the design's
| "Request Pipeline / Middleware Order" section:
|
|   • Tenant routes        : HTTPS → tenancy init (domain/subdomain) → Sanctum
|                            → RBAC (per-route). Reject requests resolving to no
|                            tenant with 403 (R1.2, R1.3, R1.4, R36.1).
|   • platform.admin routes: HTTPS → Sanctum → RBAC, NO tenancy init (R1.6,
|                            R36.1, R36.3).
|
| Structural assertions (middleware order, alias registration, no tenancy on
| central routes) run everywhere. The unresolved-tenant 403 behavior is
| DB-gated and skips gracefully when MySQL is unreachable.
|
*/

/**
 * Return the ordered, fully-resolved middleware for a named/URI route.
 *
 * @return array<int, string>
 */
function resolvedMiddlewareForUri(string $method, string $uri): array
{
    /** @var Router $router */
    $router = app(Router::class);

    foreach ($router->getRoutes() as $route) {
        if ($route->uri() === $uri && in_array($method, $route->methods(), true)) {
            return array_values($router->gatherRouteMiddleware($route));
        }
    }

    test()->fail("Route [{$method} {$uri}] was not found.");
}

it('registers the pipeline middleware aliases', function () {
    $aliases = app(Router::class)->getMiddleware();

    expect($aliases)->toHaveKey('https')
        ->and($aliases['https'])->toBe(EnforceHttps::class)
        ->and($aliases)->toHaveKey('tenant')
        ->and($aliases['tenant'])->toBe(InitializeTenancyByDomainOrSubdomain::class)
        ->and($aliases)->toHaveKey('tenant.prevent-central')
        ->and($aliases['tenant.prevent-central'])->toBe(PreventAccessFromCentralDomains::class)
        ->and($aliases)->toHaveKey('role')
        ->and($aliases['role'])->toBe(RoleMiddleware::class)
        ->and($aliases)->toHaveKey('permission')
        ->and($aliases['permission'])->toBe(PermissionMiddleware::class)
        ->and($aliases)->toHaveKey('role_or_permission')
        ->and($aliases['role_or_permission'])->toBe(RoleOrPermissionMiddleware::class);
});

it('orders the tenant pipeline as HTTPS → tenancy init → Sanctum (R36.1, R1.2)', function () {
    $mw = resolvedMiddlewareForUri('GET', 'api/me');

    // HTTPS enforcement runs first.
    expect($mw[0])->toBe(EnforceHttps::class);

    $httpsPos = array_search(EnforceHttps::class, $mw, true);
    $preventPos = array_search(PreventAccessFromCentralDomains::class, $mw, true);
    $initPos = array_search(InitializeTenancyByDomainOrSubdomain::class, $mw, true);
    $authPos = array_search('Illuminate\Auth\Middleware\Authenticate:sanctum', $mw, true);

    // Each stage is present...
    expect($preventPos)->not->toBeFalse()
        ->and($initPos)->not->toBeFalse()
        ->and($authPos)->not->toBeFalse();

    // ...and strictly ordered: HTTPS < prevent-central < tenancy init < Sanctum.
    expect($httpsPos)->toBeLessThan($preventPos)
        ->and($preventPos)->toBeLessThan($initPos)
        ->and($initPos)->toBeLessThan($authPos);
});

it('orders the platform.admin pipeline as HTTPS → Sanctum → RBAC with NO tenancy init (R1.6, R36.3)', function () {
    $mw = resolvedMiddlewareForUri('GET', 'api/admin/ping');

    // HTTPS first.
    expect($mw[0])->toBe(EnforceHttps::class);

    $httpsPos = array_search(EnforceHttps::class, $mw, true);
    $authPos = array_search('Illuminate\Auth\Middleware\Authenticate:sanctum', $mw, true);
    $rolePos = array_search(RoleMiddleware::class.':Platform_Admin', $mw, true);

    expect($authPos)->not->toBeFalse()
        ->and($rolePos)->not->toBeFalse()
        ->and($httpsPos)->toBeLessThan($authPos)
        ->and($authPos)->toBeLessThan($rolePos);

    // Central routes must NOT initialize tenancy — they operate against the
    // central database for cross-tenant/platform work (R1.6).
    expect($mw)->not->toContain(InitializeTenancyByDomainOrSubdomain::class)
        ->and($mw)->not->toContain(PreventAccessFromCentralDomains::class);
});

it('does not pipeline the health probe (reachable without TLS/tenant/auth)', function () {
    $mw = resolvedMiddlewareForUri('GET', 'api/health');

    expect($mw)->not->toContain(EnforceHttps::class)
        ->and($mw)->not->toContain(InitializeTenancyByDomainOrSubdomain::class)
        ->and($mw)->not->toContain('Illuminate\Auth\Middleware\Authenticate:sanctum');

    // And it responds OK.
    $this->getJson('/api/health')
        ->assertOk()
        ->assertJsonPath('status', 'ok');
});

it('rejects a tenant route reached on a central domain with the JSON error envelope', function () {
    // The default test host is a central domain (localhost). A tenant route hit
    // on a central domain is rejected by PreventAccessFromCentralDomains before
    // any tenant resolution — surfaced through the standard error envelope.
    $centralHost = config('tenancy.central_domains')[0];

    $response = $this->getJson('https://'.$centralHost.'/api/me');

    expect($response->getStatusCode())->toBe(404);
    $response->assertJsonStructure(['error' => ['code', 'message']]);
});

it('rejects an unresolved tenant on a non-central host with 403 (R1.4)', function () {
    // Resolving a tenant requires the central database (domains table). Without
    // a reachable MySQL this is skipped. When reachable, an unknown non-central
    // host resolves to no tenant and the configured onFail handler aborts 403.
    skip_without_mysql('central');

    $response = $this->getJson('https://does-not-exist.example.test/api/me');

    expect($response->getStatusCode())->toBe(403);
    $response->assertJsonPath('error.code', 'FORBIDDEN')
        ->assertJsonStructure(['error' => ['code', 'message']]);
});

it('enforces HTTPS when not in local/testing (R36.1)', function () {
    // EnforceHttps intentionally no-ops in local/testing. Verify the guard
    // directly against a non-secure request while simulating production.
    $middleware = new EnforceHttps;

    app()->detectEnvironment(fn () => 'production');

    $request = Request::create('http://tenant.example.test/api/me', 'GET');

    $threw = false;
    try {
        $middleware->handle($request, fn ($req) => response('ok'));
    } catch (HttpException $e) {
        $threw = true;
        expect($e->getStatusCode())->toBe(426);
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }

    expect($threw)->toBeTrue();

    // A secure request passes through untouched.
    app()->detectEnvironment(fn () => 'production');
    $secure = Request::create('https://tenant.example.test/api/me', 'GET');
    $result = $middleware->handle($secure, fn ($req) => response('ok'));
    app()->detectEnvironment(fn () => 'testing');

    expect($result->getContent())->toBe('ok');
});
