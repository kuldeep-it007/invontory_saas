<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Profile picture (POST/DELETE /api/me/avatar) — DB-gated
|--------------------------------------------------------------------------
|
| Runs against a real provisioned tenant: logs in for a real Sanctum token,
| uploads real image files, and checks the server's own validation (it never
| trusts the browser's resize), that the picture reaches /me, and that the
| blob is kept out of the audit log.
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/** @return array{0: Tenant, 1: string} the tenant and its domain */
function provisionAvatarTenant(array &$registry): array
{
    $suffix = strtolower(bin2hex(random_bytes(4)));
    $domain = 'av-'.$suffix.'.example.test';

    /** @var Tenant $tenant */
    $tenant = Tenant::create(['id' => 'av'.$suffix, 'subdomain' => 'av-'.$suffix]);
    $tenant->domains()->create(['domain' => $domain]);
    $registry[] = $tenant;

    $tenant->run(function () {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
            '--force' => true,
        ]);
        User::query()->create(['name' => 'Asha Patel', 'email' => 'asha@example.test', 'password' => 'secret-pass-1']);
    });

    return [$tenant, $domain];
}

function teardownAvatarTenants(array $registry): void
{
    foreach ($registry as $t) {
        try {
            app(\App\Services\MediaStorage::class)->disk()->deleteDirectory('tenants/'.$t->getTenantKey());
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

function avatarToken($test, string $domain): string
{
    $res = $test->postJson("http://{$domain}/api/login", ['login' => 'asha@example.test', 'password' => 'secret-pass-1']);
    $res->assertOk();

    return (string) $res->json('token');
}

it('uploads a picture, returns it on /me, and removes it', function () {
    $tenants = [];
    try {
        [, $domain] = provisionAvatarTenant($tenants);
        $auth = ['Authorization' => 'Bearer '.avatarToken($this, $domain)];

        $file = UploadedFile::fake()->image('me.png', 256, 256);
        $up = $this->withHeaders($auth)->post("http://{$domain}/api/me/avatar", ['avatar' => $file], ['Accept' => 'application/json']);
        $up->assertOk();
        // Stored on the uploads disk; only a same-origin media URL is kept.
        expect((string) $up->json('data.avatar'))->toMatch('#^/api/media/tenants/[A-Za-z0-9_-]+/avatar/[A-Za-z0-9]{32}\.png$#');

        $this->withHeaders($auth)->getJson("http://{$domain}/api/me")
            ->assertOk()
            ->assertJsonPath('avatar', $up->json('data.avatar'));

        $this->withHeaders($auth)->deleteJson("http://{$domain}/api/me/avatar")
            ->assertOk()
            ->assertJsonPath('data.avatar', null);

        $this->withHeaders($auth)->getJson("http://{$domain}/api/me")->assertJsonPath('avatar', null);
    } finally {
        teardownAvatarTenants($tenants);
    }
});

it('rejects files that are not real JPEG/PNG/WebP images, are too big, or too small', function () {
    $tenants = [];
    try {
        [, $domain] = provisionAvatarTenant($tenants);
        $auth = ['Authorization' => 'Bearer '.avatarToken($this, $domain), 'Accept' => 'application/json'];
        $post = fn (UploadedFile $f) => $this->withHeaders($auth)->post("http://{$domain}/api/me/avatar", ['avatar' => $f]);

        // An SVG (can carry script) renamed to .png is detected by content.
        $svg = UploadedFile::fake()->createWithContent('evil.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $post($svg)->assertStatus(422);

        // Plain text pretending to be an image.
        $post(UploadedFile::fake()->createWithContent('notes.jpg', 'just some text'))->assertStatus(422);

        // Over the 512 KB cap.
        $post(UploadedFile::fake()->image('big.png', 512, 512)->size(600))->assertStatus(422);

        // Below the 32px minimum.
        $post(UploadedFile::fake()->image('tiny.png', 16, 16))->assertStatus(422);
    } finally {
        teardownAvatarTenants($tenants);
    }
});

it('requires authentication and keeps the picture out of the audit log', function () {
    $tenants = [];
    try {
        [$tenant, $domain] = provisionAvatarTenant($tenants);

        $this->post("http://{$domain}/api/me/avatar", ['avatar' => UploadedFile::fake()->image('me.png', 64, 64)], ['Accept' => 'application/json'])
            ->assertUnauthorized();

        $auth = ['Authorization' => 'Bearer '.avatarToken($this, $domain), 'Accept' => 'application/json'];
        $this->withHeaders($auth)->post("http://{$domain}/api/me/avatar", ['avatar' => UploadedFile::fake()->image('me.png', 64, 64)])
            ->assertOk();

        $tenant->run(function () {
            $logged = AuditLog::query()->get()->map(fn ($row) => json_encode($row->toArray()))->implode("\n");
            expect($logged)->not->toContain('base64');
        });
    } finally {
        teardownAvatarTenants($tenants);
    }
});
