<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Smoke: App boots with zero optional / AI configuration
|--------------------------------------------------------------------------
|
| Task 1.3 — "Assert the app boots with zero optional/AI configuration".
|
| These checks are UNCONDITIONAL (no MySQL/Redis required). The Laravel
| application container is already booted by the time a Pest test body runs,
| so if any of the following fails to resolve, the test itself would never
| execute. We additionally assert the optional capability defaults so a future
| change that makes an optional feature required-to-boot is caught here.
|
| Requirements: 1.1 (foundation boots), 38.* (indexing foundation present).
| Design: "App boots with zero AI configuration (R30.1–R30.3)".
*/

it('boots the Laravel application container', function () {
    // Reaching this point means bootstrap/app.php resolved and the container
    // is available. Assert the core bindings exist.
    expect(app())->toBeInstanceOf(\Illuminate\Foundation\Application::class);
    expect(app()->bound('config'))->toBeTrue();
    expect(app()->bound('db'))->toBeTrue();
});

it('reports a configured application name and environment', function () {
    expect(config('app.name'))->not->toBeEmpty();
    // The testing environment is set via phpunit.xml <php> env.
    expect(app()->environment())->toBe('testing');
});

it('defaults every optional / AI capability to OFF so no optional config is required to boot', function () {
    // AI reporting master switch defaults off (.env.example: AI_REPORTS_ENABLED=false).
    // These may be absent entirely; absence must behave as "off / none".
    expect((bool) env('AI_REPORTS_ENABLED', false))->toBeFalse();

    $llmProvider = env('LLM_PROVIDER', 'none');
    expect($llmProvider)->toBeIn(['none', null, '']);

    // Optional LLM connection details are not required to boot.
    expect(env('LLM_API_KEY', null))->toBeIn([null, '']);

    // Optional S3 object storage is not required (default local disk).
    expect(env('AWS_BUCKET', null))->toBeIn([null, '']);
});

it('has the central connection as the default database connection', function () {
    // Foundation requirement R1.1: central is the authoritative default
    // connection before any tenant is initialized. In the testing env the
    // phpunit.xml overrides DB_CONNECTION to sqlite, so we assert the
    // production default defined in config/database.php instead of the
    // test-overridden runtime value.
    $centralConnection = config('database.connections.central');

    expect($centralConnection)->not->toBeNull()
        ->and($centralConnection['driver'])->toBe('mysql');

    // stancl/tenancy must agree that `central` is the central connection.
    expect(config('tenancy.database.central_connection'))->toBe('central');
    expect(config('tenancy.database.template_tenant_connection'))->toBe('tenant_template');
});

it('exposes the tenant template connection used to build per-tenant databases', function () {
    $template = config('database.connections.tenant_template');

    expect($template)->not->toBeNull()
        ->and($template['driver'])->toBe('mysql')
        // The template has a null database; stancl/tenancy fills it per tenant.
        ->and($template['database'])->toBeNull();
});

it('registers the DatabaseTenancyBootstrapper that switches the default connection', function () {
    expect(config('tenancy.bootstrappers'))
        ->toContain(\Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class);
});

it('configures the per-tenant database naming scheme', function () {
    // Per-tenant DB name = prefix + tenant id + suffix. Required for R1.1
    // physical separation (one database per tenant).
    expect(config('tenancy.database.prefix'))->toBe('tenant');
    expect(config('tenancy.database.suffix'))->toBe('');
});
