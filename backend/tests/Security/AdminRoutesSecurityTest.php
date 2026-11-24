<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Deployment security gate: boundaries that must never regress.
|--------------------------------------------------------------------------
*/

it('rejects every central admin and platform route without a token', function () {
    $checked = 0;

    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();
        $protected = str_starts_with($uri, 'api/admin/') || in_array($uri, ['api/platform/me', 'api/platform/logout'], true);
        if (! $protected) {
            continue;
        }
        $path = '/'.preg_replace('/\{[^}]+\??\}/', '1', str_replace('{id}', '20260101T100000Z-aaaaaa', $uri));
        foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
            $status = $this->json($method, $path)->getStatusCode();
            expect($status)->toBeIn([401, 403], "{$method} {$path} answered {$status} without a token");
            $checked++;
        }
    }

    expect($checked)->toBeGreaterThan(20);
});

it('rejects a garbage bearer token on the admin API', function () {
    skip_without_mysql('central');

    $this->withHeader('Authorization', 'Bearer 1|not-a-real-token')
        ->getJson('/api/admin/monitoring/overview')
        ->assertUnauthorized();
});

it('keeps the public health probe free of configuration details', function () {
    $response = $this->getJson('/api/health')->assertOk();

    expect(array_keys($response->json()))->toEqualCanonicalizing(['status', 'service']);
});

it('does not expose debug output or the environment on an unknown route', function () {
    $body = $this->getJson('/api/this-route-does-not-exist')->assertNotFound()->getContent();

    expect($body)->not->toContain('APP_KEY')
        ->and($body)->not->toContain(base_path());
});
