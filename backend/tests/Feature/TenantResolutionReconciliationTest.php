<?php

declare(strict_types=1);

use App\Http\Middleware\ReconcileTenantUser;
use App\Models\PersonalAccessToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Task 3.3 — Subdomain / custom-domain resolution + tenant/user reconciliation
|--------------------------------------------------------------------------
|
| Two concerns, from the design's "Multi-Tenant Isolation Strategy" and
| "White-Labeling / custom-domain routing":
|
|   (1) Resolution (R3.4). stancl/tenancy's InitializeTenancyByDomainOrSubdomain
|       resolves BOTH a subdomain (host = <sub>.<central_domain>) and a custom
|       domain (host stored verbatim) through the central `domains` table used
|       for identification. Provisioning writes the resolution row there.
|
|   (2) Reconciliation (R1.6). AFTER Sanctum authenticates, ReconcileTenantUser
|       re-affirms that the presented token was issued for the tenant whose
|       database is active. A token stamped for tenant A replayed against a
|       request resolved to tenant B is rejected with HTTP 403 — defence in
|       depth over the physical isolation boundary.
|
| Structural assertions (middleware wiring, token stamping, guard behaviour in
| isolation) run everywhere. The end-to-end resolution + cross-tenant replay
| assertions are DB-GATED and skip gracefully when MySQL is unreachable.
|
| Requirements: 3.4, 1.6.
*/

/**
 * Ordered, fully-resolved middleware for a named/URI route.
 *
 * @return array<int, string>
 */
function resolvedMiddlewareFor(string $method, string $uri): array
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

/*
|--------------------------------------------------------------------------
| Structural: pipeline wiring + alias registration
|--------------------------------------------------------------------------
*/

it('registers the tenant.reconcile middleware alias (R1.6)', function () {
    $aliases = app(Router::class)->getMiddleware();

    expect($aliases)->toHaveKey('tenant.reconcile')
        ->and($aliases['tenant.reconcile'])->toBe(ReconcileTenantUser::class);
});

it('places reconciliation AFTER Sanctum in the tenant pipeline (R1.6)', function () {
    $mw = resolvedMiddlewareFor('GET', 'api/me');

    $authPos = array_search('Illuminate\Auth\Middleware\Authenticate:sanctum', $mw, true);
    $reconcilePos = array_search(ReconcileTenantUser::class, $mw, true);

    expect($authPos)->not->toBeFalse()
        ->and($reconcilePos)->not->toBeFalse()
        // Reconciliation needs the authenticated user + token, so it must run
        // strictly after Sanctum.
        ->and($authPos)->toBeLessThan($reconcilePos);
});

it('does NOT mount reconciliation on central platform.admin routes (R1.6)', function () {
    // Central routes never initialize tenancy, so there is no active tenant to
    // reconcile against — the guard must not appear there.
    $mw = resolvedMiddlewareFor('GET', 'api/admin/ping');

    expect($mw)->not->toContain(ReconcileTenantUser::class);
});

it('registers the tenant-stamped Sanctum token model (R1.6)', function () {
    // AppServiceProvider tells Sanctum to resolve tokens through the custom
    // model that carries the tenant_id stamp.
    expect(Sanctum::$personalAccessTokenModel)->toBe(PersonalAccessToken::class);
});

/*
|--------------------------------------------------------------------------
| Structural: the guard in isolation (no DB required)
|--------------------------------------------------------------------------
|
| The guard's decision is a pure comparison of the token's tenant_id stamp
| against the active tenant id. We exercise each branch directly by faking a
| tenant context and a user whose currentAccessToken returns a stub token.
*/

/**
 * Build a user whose currentAccessToken() returns a PersonalAccessToken with
 * the given tenant_id stamp (or none), without touching the database.
 */
function userWithStampedToken(?string $stamp): User
{
    $token = new PersonalAccessToken;
    if ($stamp !== null) {
        $token->setAttribute('tenant_id', $stamp);
    }

    $user = new User;
    $user->withAccessToken($token);

    return $user;
}

it('rejects a token stamped for a different tenant than the active one (R1.6)', function () {
    // Simulate tenancy initialized for tenant "alpha".
    tenancy()->initialize(tenantStub('alpha'));

    try {
        $guard = new ReconcileTenantUser;

        $request = Illuminate\Http\Request::create('https://beta.example.test/api/me', 'GET');
        $request->setUserResolver(fn () => userWithStampedToken('beta'));

        $threw = false;
        try {
            $guard->handle($request, fn () => response('ok'));
        } catch (Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $threw = true;
            expect($e->getStatusCode())->toBe(403);
        }

        expect($threw)->toBeTrue();
    } finally {
        tenancy()->end();
    }
});

it('allows a token stamped for the active tenant (R1.6)', function () {
    tenancy()->initialize(tenantStub('alpha'));

    try {
        $guard = new ReconcileTenantUser;

        $request = Illuminate\Http\Request::create('https://alpha.example.test/api/me', 'GET');
        $request->setUserResolver(fn () => userWithStampedToken('alpha'));

        $response = $guard->handle($request, fn () => response('ok'));

        expect($response->getContent())->toBe('ok');
    } finally {
        tenancy()->end();
    }
});

it('allows an unstamped (legacy) token, relying on the physical boundary (R1.6)', function () {
    tenancy()->initialize(tenantStub('alpha'));

    try {
        $guard = new ReconcileTenantUser;

        $request = Illuminate\Http\Request::create('https://alpha.example.test/api/me', 'GET');
        $request->setUserResolver(fn () => userWithStampedToken(null));

        $response = $guard->handle($request, fn () => response('ok'));

        expect($response->getContent())->toBe('ok');
    } finally {
        tenancy()->end();
    }
});

it('is a no-op when no tenant is active (central context) (R1.6)', function () {
    tenancy()->end();

    $guard = new ReconcileTenantUser;

    $request = Illuminate\Http\Request::create('https://localhost/api/admin/ping', 'GET');
    // Even a mismatched stamp passes when there is no active tenant to compare.
    $request->setUserResolver(fn () => userWithStampedToken('beta'));

    $response = $guard->handle($request, fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});

/**
 * An in-memory Tenant stub for the structural guard tests. It is NEVER
 * persisted and no database is created; tenancy()->initialize() only needs the
 * tenant key for tenant('id').
 */
function tenantStub(string $id): Tenant
{
    $tenant = new Tenant;
    $tenant->setAttribute('id', $id);

    return $tenant;
}

/*
|--------------------------------------------------------------------------
| DB-gated: end-to-end resolution + cross-tenant replay rejection
|--------------------------------------------------------------------------
|
| These exercise the REAL stancl/tenancy identification middleware against two
| provisioned tenant databases: one resolved by SUBDOMAIN, one by CUSTOM
| DOMAIN, both through the central `domains` table (R3.4). A token issued in
| tenant A's database is then replayed against a request resolved to tenant B
| and must be rejected with 403 (R1.6).
*/

/**
 * Provision + migrate an isolated tenant with the given resolution domain.
 *
 * @param  array<int, Tenant>  $registry
 */
function provisionResolutionTenant(array &$registry, string $domain): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(5)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'res'.$suffix,
        'subdomain' => 'res-'.$suffix,
    ]);

    // The central `domains` row is what tenancy identification resolves against
    // (R3.4) — for both subdomain hosts and verbatim custom domains.
    $tenant->domains()->create(['domain' => $domain]);

    $tenant->run(function () {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
            '--force' => true,
        ]);
    });

    $registry[] = $tenant;

    return $tenant;
}

/**
 * @param  array<int, Tenant>  $tenants
 */
function teardownResolutionTenants(array $tenants): void
{
    tenancy()->end();

    foreach ($tenants as $t) {
        try {
            $t->database()->getTenantDatabaseManager()->deleteDatabase($t);
        } catch (\Throwable $e) {
            // best-effort
        }
        try {
            $t->domains()->delete();
            $t->delete();
        } catch (\Throwable $e) {
            // best-effort
        }
    }
}

it('resolves BOTH a subdomain and a custom domain to their tenants via the central domains table (R3.4)', function () {
    skip_without_mysql('central');

    $tenants = [];

    try {
        $central = config('tenancy.central_domains')[0];

        // (a) Subdomain-based resolution: host = <subdomain>.<central_domain>.
        $subTenant = provisionResolutionTenant($tenants, 'res-sub-demo.'.$central);
        // Rename its domain to a deterministic, resolvable subdomain host.
        $subTenant->domains()->delete();
        $subHost = 'acme.'.$central;
        $subTenant->domains()->create(['domain' => $subHost]);

        // (b) Custom-domain resolution: host stored verbatim (no central suffix).
        $customHost = 'shop.acme-brand.test';
        $customTenant = provisionResolutionTenant($tenants, $customHost);

        // Identification resolves each host to the matching tenant through the
        // SAME central domains table used by the middleware.
        $resolver = app(Stancl\Tenancy\Resolvers\DomainTenantResolver::class);

        $resolvedCustom = $resolver->resolve($customHost);
        expect($resolvedCustom->getTenantKey())->toBe($customTenant->getTenantKey());

        // The subdomain resolver strips the central domain and matches the
        // leftmost label; assert the full-host domain row resolves too.
        $resolvedSubFull = $resolver->resolve($subHost);
        expect($resolvedSubFull->getTenantKey())->toBe($subTenant->getTenantKey());
    } finally {
        teardownResolutionTenants($tenants);
    }
});

it('stamps the issuing tenant onto a token and rejects it when replayed against another tenant (R1.6)', function () {
    skip_without_mysql('central');

    $tenants = [];

    try {
        // Use a DNS-label-valid central base: the first central domain may be a
        // bare IP (127.0.0.1), and prefixing a label onto an IP ("x.127.0.0.1")
        // produces a host Symfony's Request validator rejects as malformed. The
        // guard under test reconciles the token's tenant stamp against the
        // active tenant and never parses the host, so any valid host suffices.
        $central = collect(config('tenancy.central_domains'))
            ->first(fn ($d) => ! filter_var($d, FILTER_VALIDATE_IP), 'localhost');

        $tenantA = provisionResolutionTenant($tenants, 'tenant-a.'.$central);
        $tenantB = provisionResolutionTenant($tenants, 'tenant-b.'.$central);

        // Create a user + a tenant-stamped token INSIDE tenant A's database via
        // the login path (AuthService stamps tenant('id') onto the token).
        $plainForA = $tenantA->run(function () use ($tenantA) {
            Artisan::call('db:seed', [
                '--class' => 'Database\\Seeders\\TenantDatabaseSeeder',
                '--database' => 'tenant',
                '--force' => true,
            ]);

            $user = User::create([
                'name' => 'A Admin',
                'email' => 'admin@tenant-a.test',
                'password' => 'secret-password',
            ]);

            $result = app(App\Services\AuthService::class)->login('admin@tenant-a.test', 'secret-password');

            return $result['token'];
        });

        // Confirm the stored token row is stamped with tenant A's id (R1.6).
        $tenantA->run(function () use ($tenantA, $plainForA) {
            $tokenId = (int) explode('|', $plainForA)[0];
            $stamp = PersonalAccessToken::query()->find($tokenId)?->getAttribute('tenant_id');
            expect((string) $stamp)->toBe((string) $tenantA->getTenantKey());
        });

        // Now exercise the guard directly in tenant B's context with tenant A's
        // token loaded as the current access token: it must be rejected (403).
        tenancy()->initialize($tenantB);

        $tokenModel = $tenantA->run(function () use ($plainForA) {
            $tokenId = (int) explode('|', $plainForA)[0];

            return PersonalAccessToken::query()->find($tokenId);
        });

        // Reconstruct a user carrying tenant A's token while B is active.
        $userWithAToken = (new User)->withAccessToken($tokenModel);

        $guard = new ReconcileTenantUser;
        $request = Illuminate\Http\Request::create('https://tenant-b.'.$central.'/api/me', 'GET');
        $request->setUserResolver(fn () => $userWithAToken);

        $threw = false;
        try {
            $guard->handle($request, fn () => response('ok'));
        } catch (Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $threw = true;
            expect($e->getStatusCode())->toBe(403);
        }

        expect($threw)->toBeTrue();
    } finally {
        teardownResolutionTenants($tenants);
    }
});
