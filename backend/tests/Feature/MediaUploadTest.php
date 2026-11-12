<?php

declare(strict_types=1);

use App\Auth\Roles;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MediaStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| Uploaded files on the uploads disk (logo, favicon, profile picture)
|--------------------------------------------------------------------------
|
| DB-gated. Runs against real provisioned tenants. The uploads disk is the
| configured one (UPLOADS_DISK), so pointing UPLOADS_DISK at the `oci` disk
| (e.g. a MinIO stand-in, or a real OCI bucket) exercises object storage end
| to end; otherwise the local fallback disk is used.
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/** @return array{0: Tenant, 1: string} */
function provisionMediaTenant(array &$registry, string $role = Roles::COMPANY_ADMIN): array
{
    $suffix = strtolower(bin2hex(random_bytes(4)));
    $domain = 'md-'.$suffix.'.example.test';

    /** @var Tenant $tenant */
    $tenant = Tenant::create(['id' => 'md'.$suffix, 'subdomain' => 'md-'.$suffix]);
    $tenant->domains()->create(['domain' => $domain]);
    $registry[] = $tenant;

    $tenant->run(function () use ($role) {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => 'database/migrations/tenant', '--force' => true]);
        Artisan::call('db:seed', ['--class' => 'Database\Seeders\TenantDatabaseSeeder', '--force' => true]);
        $user = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-pass-1']);
        $user->assignRole(Role::findByName($role, 'web'));
    });

    return [$tenant, $domain];
}

function teardownMediaTenants(array $registry): void
{
    foreach ($registry as $t) {
        try {
            $t->run(fn () => app(MediaStorage::class)->disk()->deleteDirectory('tenants/'.$t->getTenantKey()));
        } catch (Throwable $e) {
            // best-effort
        }
        try {
            $t->database()->getTenantDatabaseManager()->deleteDatabase($t);
        } catch (Throwable $e) {
            // best-effort
        }
        $t->domains()->delete();
        $t->delete();
    }
}

function mediaAuth($test, string $domain): array
{
    $res = $test->postJson("http://{$domain}/api/login", ['login' => 'admin@example.test', 'password' => 'secret-pass-1']);
    $res->assertOk();

    return ['Authorization' => 'Bearer '.$res->json('token'), 'Accept' => 'application/json'];
}

it('uploads a logo to the uploads disk, serves it publicly, and replaces it cleanly', function () {
    $tenants = [];
    try {
        [$tenant, $domain] = provisionMediaTenant($tenants);
        $auth = mediaAuth($this, $domain);
        $media = app(MediaStorage::class);

        $first = $this->withHeaders($auth)->post("http://{$domain}/api/tenant/settings/logo", [
            'file' => UploadedFile::fake()->image('logo.png', 400, 120),
        ]);
        $first->assertOk();
        $url = (string) $first->json('data.logo');
        expect($url)->toStartWith('/api/media/tenants/'.$tenant->getTenantKey().'/logo/');

        $path = $media->pathFromUrl($url);
        expect($media->disk()->exists($path))->toBeTrue();

        // Public (no token) and branding points at it.
        $this->get("http://{$domain}{$url}")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->getJson("http://{$domain}/api/tenant/branding")->assertJsonPath('data.logo', $url);

        // Replacing deletes the old object.
        $second = $this->withHeaders($auth)->post("http://{$domain}/api/tenant/settings/logo", [
            'file' => UploadedFile::fake()->image('logo2.jpg', 300, 100),
        ]);
        $second->assertOk();
        expect($media->disk()->exists($path))->toBeFalse()
            ->and($media->disk()->exists($media->pathFromUrl((string) $second->json('data.logo'))))->toBeTrue();

        // Removing clears the setting and deletes the object.
        $newPath = $media->pathFromUrl((string) $second->json('data.logo'));
        $this->withHeaders($auth)->deleteJson("http://{$domain}/api/tenant/settings/logo")
            ->assertOk()->assertJsonPath('data.logo', null);
        expect($media->disk()->exists($newPath))->toBeFalse();
    } finally {
        teardownMediaTenants($tenants);
    }
});

it('stores a favicon (ICO) and a profile picture the same way', function () {
    $tenants = [];
    try {
        [, $domain] = provisionMediaTenant($tenants);
        $auth = mediaAuth($this, $domain);
        $media = app(MediaStorage::class);

        // A minimal valid ICO (header + one 1x1 32-bit entry with BMP data).
        $ico = base64_decode('AAABAAEAAQEAAAEAIAAwAAAAFgAAACgAAAABAAAAAgAAAAEAIAAAAAAABAAAAAAAAAAAAAAAAAAAAAAAAAD/AAD/AAAAAA==');
        $fav = $this->withHeaders($auth)->post("http://{$domain}/api/tenant/settings/favicon", [
            'file' => UploadedFile::fake()->createWithContent('favicon.ico', $ico),
        ]);
        $fav->assertOk();
        $favUrl = (string) $fav->json('data.favicon');
        expect($favUrl)->toEndWith('.ico');
        $this->get("http://{$domain}{$favUrl}")->assertOk()->assertHeader('Content-Type', 'image/x-icon');

        $av = $this->withHeaders($auth)->post("http://{$domain}/api/me/avatar", [
            'avatar' => UploadedFile::fake()->image('me.jpg', 256, 256),
        ]);
        $av->assertOk();
        $avUrl = (string) $av->json('data.avatar');
        expect($avUrl)->toContain('/avatar/')
            ->and($media->disk()->exists($media->pathFromUrl($avUrl)))->toBeTrue();
        $this->withHeaders($auth)->getJson("http://{$domain}/api/me")->assertJsonPath('avatar', $avUrl);
    } finally {
        teardownMediaTenants($tenants);
    }
});

it('rejects bad files, non-admins, and cross-tenant or malformed media paths', function () {
    $tenants = [];
    try {
        [$tenantA, $domainA] = provisionMediaTenant($tenants);
        [, $domainB] = provisionMediaTenant($tenants, Roles::READ_ONLY);
        $authA = mediaAuth($this, $domainA);

        // SVG disguised as PNG, and plain text, are refused by content.
        $svg = UploadedFile::fake()->createWithContent('logo.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->withHeaders($authA)->post("http://{$domainA}/api/tenant/settings/logo", ['file' => $svg])->assertStatus(422);
        $this->withHeaders($authA)->post("http://{$domainA}/api/tenant/settings/favicon", [
            'file' => UploadedFile::fake()->createWithContent('f.ico', 'not an icon'),
        ])->assertStatus(422);
        // Too large.
        $this->withHeaders($authA)->post("http://{$domainA}/api/tenant/settings/logo", [
            'file' => UploadedFile::fake()->image('big.png', 500, 500)->size(2048),
        ])->assertStatus(422);

        // A non-admin cannot change branding.
        $authB = mediaAuth($this, $domainB);
        $this->withHeaders($authB)->post("http://{$domainB}/api/tenant/settings/logo", [
            'file' => UploadedFile::fake()->image('logo.png', 100, 100),
        ])->assertForbidden();

        // Tenant A's file is not served on tenant B's domain.
        $url = (string) $this->withHeaders($authA)->post("http://{$domainA}/api/tenant/settings/logo", [
            'file' => UploadedFile::fake()->image('logo.png', 100, 100),
        ])->json('data.logo');
        $this->get("http://{$domainA}{$url}")->assertOk();
        $this->get("http://{$domainB}{$url}")->assertNotFound();

        // Traversal / malformed paths never reach storage.
        foreach ([
            '/api/media/tenants/'.$tenantA->getTenantKey().'/logo/../../../.env',
            '/api/media/tenants/'.$tenantA->getTenantKey().'/logo/abc.png',
            '/api/media/tenants/'.$tenantA->getTenantKey().'/other/'.str_repeat('a', 32).'.png',
            '/api/media/tenants/'.$tenantA->getTenantKey().'/logo/'.str_repeat('a', 32).'.svg',
        ] as $bad) {
            $this->get("http://{$domainA}{$bad}")->assertNotFound();
        }
    } finally {
        teardownMediaTenants($tenants);
    }
});
