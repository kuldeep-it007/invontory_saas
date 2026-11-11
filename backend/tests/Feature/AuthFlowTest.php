<?php

declare(strict_types=1);

use App\Auth\Roles;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| Task 1.7 — Authentication + RBAC (DB-gated)
|--------------------------------------------------------------------------
|
| Exercises the auth domain against a real provisioned tenant database:
|   • login issues a Sanctum token on valid credentials (R7.1);
|   • invalid credentials → audited failed attempt (R7.2);
|   • passwords stored as a bcrypt/argon2 hash, never plaintext (R7.3);
|   • the six spatie roles seed with their abilities, Read-only view-only (R8.1, R8.4);
|   • identity/roles/abilities payload shape for GET /api/me (R8.5).
|
| Every test is DB-GATED (needs MySQL + CREATE DATABASE privilege for stancl
| tenancy) and skips gracefully when MySQL is unreachable, mirroring the
| existing smoke suite.
|
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Provision + migrate + seed an isolated tenant, returning it. Registers the
 * tenant for teardown via the passed registry.
 */
function provisionAuthTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'auth'.$suffix,
        'subdomain' => 'auth-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'auth-'.$suffix.'.example.test']);
    $registry[] = $tenant;

    $tenant->run(function () {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
            '--force' => true,
        ]);
        (new RoleSeeder)->run();
    });

    return $tenant;
}

/**
 * Tear down every provisioned tenant (drop DB + central rows).
 */
function teardownAuthTenants(array $registry): void
{
    foreach ($registry as $t) {
        try {
            $t->database()->getTenantDatabaseManager()->deleteDatabase($t);
        } catch (Throwable $e) {
            // best-effort
        }
        $t->domains()->delete();
        $t->delete();
    }
}

it('seeds the six roles with their abilities; Read-only is view-only (R8.1, R8.4)', function () {
    $tenants = [];
    try {
        $tenant = provisionAuthTenant($tenants);

        $tenant->run(function () {
            $roleNames = Role::query()->pluck('name')->all();
            expect($roleNames)->toEqualCanonicalizing(Roles::all());

            $readOnly = Role::findByName(Roles::READ_ONLY);
            $readOnlyAbilities = $readOnly->permissions->pluck('name')->all();

            // Read-only holds exactly the view abilities, no mutations (R8.4).
            expect($readOnlyAbilities)->toEqualCanonicalizing(Roles::viewAbilities());
            foreach (Roles::mutationAbilities() as $mutation) {
                expect($readOnlyAbilities)->not->toContain($mutation);
            }

            // Company_Admin holds the full catalogue.
            $admin = Role::findByName(Roles::COMPANY_ADMIN);
            expect($admin->permissions->pluck('name')->all())
                ->toEqualCanonicalizing(Roles::allAbilities());
        });
    } finally {
        teardownAuthTenants($tenants);
    }
});

it('stores the password as a hash that is never plaintext and verifies (R7.3)', function () {
    $tenants = [];
    try {
        $tenant = provisionAuthTenant($tenants);

        $tenant->run(function () {
            $user = User::create([
                'name' => 'Hash User',
                'email' => 'hash@example.test',
                'password' => 'super-secret-123',
            ]);

            $stored = $user->fresh()->getAuthPassword();

            expect($stored)->not->toBe('super-secret-123')
                ->and(Hash::check('super-secret-123', $stored))->toBeTrue()
                ->and(password_get_info($stored)['algo'])->not->toBe(0);
        });
    } finally {
        teardownAuthTenants($tenants);
    }
});

it('logs in with valid credentials and issues a token + identity payload (R7.1, R8.5)', function () {
    $tenants = [];
    try {
        $tenant = provisionAuthTenant($tenants);
        $domain = 'auth-'.substr($tenant->id, 4).'.example.test';

        $tenant->run(function () {
            $user = User::create([
                'name' => 'Login User',
                'email' => 'login@example.test',
                'password' => 'correct-horse',
            ]);
            $user->assignRole(Roles::COMPANY_ADMIN);
        });

        $response = $this->postJson('https://'.$domain.'/api/login', [
            'login' => 'login@example.test',
            'password' => 'correct-horse',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'token_type',
                'user' => ['id', 'name', 'email', 'roles', 'abilities'],
            ])
            ->assertJsonPath('user.roles', [Roles::COMPANY_ADMIN])
            ->assertJsonPath('user.email', 'login@example.test');

        expect($response->json('token'))->toBeString()->not->toBeEmpty();
    } finally {
        teardownAuthTenants($tenants);
    }
});

it('rejects invalid credentials with 401 and records a failed attempt (R7.2)', function () {
    $tenants = [];
    try {
        $tenant = provisionAuthTenant($tenants);
        $domain = 'auth-'.substr($tenant->id, 4).'.example.test';

        $tenant->run(function () {
            User::create([
                'name' => 'Real User',
                'email' => 'real@example.test',
                'password' => 'right-password',
            ]);
        });

        $response = $this->postJson('https://'.$domain.'/api/login', [
            'login' => 'real@example.test',
            'password' => 'WRONG-password',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        // The failed attempt is audited in the tenant database (R7.2).
        $tenant->run(function () {
            $failed = AuditLog::query()->where('event', 'login_failed')->count();
            expect($failed)->toBeGreaterThanOrEqual(1);
        });
    } finally {
        teardownAuthTenants($tenants);
    }
});

it('returns identity/roles/abilities from GET /api/me for an authenticated user (R8.5)', function () {
    $tenants = [];
    try {
        $tenant = provisionAuthTenant($tenants);
        $domain = 'auth-'.substr($tenant->id, 4).'.example.test';

        $tenant->run(function () {
            $user = User::create([
                'name' => 'Me User',
                'email' => 'me@example.test',
                'password' => 'me-password',
            ]);
            $user->assignRole(Roles::READ_ONLY);
        });

        $login = $this->postJson('https://'.$domain.'/api/login', [
            'login' => 'me@example.test',
            'password' => 'me-password',
        ]);
        $token = $login->json('token');

        $me = $this->getJson('https://'.$domain.'/api/me', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $me->assertOk()
            ->assertJsonPath('email', 'me@example.test')
            ->assertJsonPath('roles', [Roles::READ_ONLY]);

        // Read-only abilities are exactly the view set (R8.4, R8.5).
        expect($me->json('abilities'))->toEqualCanonicalizing(Roles::viewAbilities());
    } finally {
        teardownAuthTenants($tenants);
    }
});
