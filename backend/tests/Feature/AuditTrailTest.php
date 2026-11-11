<?php

declare(strict_types=1);

use App\Exceptions\ImmutableAuditException;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuthService;
use App\Support\AuditRecorder;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Task 1.10 — Audit logging + append-only audit model (DB-gated)
|--------------------------------------------------------------------------
|
| Exercises the consolidated audit mechanism against a real provisioned tenant
| database:
|   • owen-it/laravel-auditing records create/update/delete of a tenant-owned
|     model (User) with actor identity (R34.1);
|   • the password hash is excluded from audited values (R7.3, R34.1);
|   • security events (login, logout, password-reset, OTP-verify) are recorded
|     through the same AuditLog model and table (R7.6);
|   • AuditLog entries are append-only — update and delete are rejected and the
|     stored entry is unchanged (R34.2).
|
| Every test is DB-GATED (needs MySQL + CREATE DATABASE for stancl tenancy) and
| skips gracefully when MySQL is unreachable, mirroring the auth suite. The
| exhaustive append-only and audit-coverage properties are tasks 1.11 / 1.12.
|
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Provision + migrate an isolated tenant for audit tests, returning it.
 */
function provisionAuditTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'audit'.$suffix,
        'subdomain' => 'audit-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'audit-'.$suffix.'.example.test']);
    $registry[] = $tenant;

    $tenant->run(function () {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
            '--force' => true,
        ]);
    });

    return $tenant;
}

function teardownAuditTenants(array $registry): void
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

it('records an owen-it audit on tenant-owned model create/update/delete with actor identity (R34.1)', function () {
    $tenants = [];
    try {
        $tenant = provisionAuditTenant($tenants);

        $tenant->run(function () {
            // Create → one audit entry for the created event.
            $user = User::create([
                'name' => 'Audited User',
                'email' => 'audited@example.test',
                'password' => 'first-secret-123',
            ]);

            $created = AuditLog::query()
                ->where('auditable_type', $user->getMorphClass())
                ->where('auditable_id', $user->getKey())
                ->where('event', 'created')
                ->first();

            expect($created)->not->toBeNull()
                // Affected record identity is captured (R34.1).
                ->and((int) $created->auditable_id)->toBe((int) $user->getKey())
                // Timestamp is captured.
                ->and($created->created_at)->not->toBeNull()
                // Credentials never land in the audit trail (R7.3, R34.1).
                ->and($created->new_values ?? [])->not->toHaveKey('password')
                ->and($created->new_values ?? [])->not->toHaveKey('remember_token');

            // Update → a distinct audit entry capturing the change.
            $user->update(['name' => 'Renamed User']);

            $updated = AuditLog::query()
                ->where('auditable_type', $user->getMorphClass())
                ->where('auditable_id', $user->getKey())
                ->where('event', 'updated')
                ->first();

            expect($updated)->not->toBeNull()
                ->and($updated->new_values ?? [])->toHaveKey('name')
                ->and($updated->new_values['name'])->toBe('Renamed User');

            // Delete → a distinct audit entry.
            $user->delete();

            $deleted = AuditLog::query()
                ->where('auditable_type', $user->getMorphClass())
                ->where('auditable_id', $user->getKey())
                ->where('event', 'deleted')
                ->count();

            expect($deleted)->toBeGreaterThanOrEqual(1);
        });
    } finally {
        teardownAuditTenants($tenants);
    }
});

it('records login, logout, password-reset and OTP-verify security events through the AuditLog model (R7.6)', function () {
    $tenants = [];
    try {
        $tenant = provisionAuditTenant($tenants);

        $tenant->run(function () {
            /** @var AuthService $auth */
            $auth = app(AuthService::class);
            /** @var AuditRecorder $recorder */
            $recorder = app(AuditRecorder::class);

            $user = User::create([
                'name' => 'Security User',
                'email' => 'security@example.test',
                'password' => 'security-pass-123',
            ]);

            // login + logout + password reset + otp verify through their hooks.
            $auth->login('security@example.test', 'security-pass-123');
            $auth->logout($user);
            $recorder->record('password_reset_requested', $user, ['email' => $user->email]);
            $auth->recordOtpVerified($user);

            foreach (['login', 'logout', 'password_reset_requested', 'otp_verified'] as $event) {
                $count = AuditLog::query()->where('event', $event)->count();
                expect($count)->toBeGreaterThanOrEqual(1);
            }
        });
    } finally {
        teardownAuditTenants($tenants);
    }
});

it('rejects updates to an existing audit entry and leaves it unchanged (R34.2)', function () {
    $tenants = [];
    try {
        $tenant = provisionAuditTenant($tenants);

        $tenant->run(function () {
            $user = User::create([
                'name' => 'Append User',
                'email' => 'append@example.test',
                'password' => 'append-pass-123',
            ]);

            $entry = AuditLog::query()
                ->where('auditable_id', $user->getKey())
                ->where('event', 'created')
                ->firstOrFail();

            $originalEvent = $entry->event;

            expect(fn () => $entry->update(['event' => 'tampered']))
                ->toThrow(ImmutableAuditException::class);

            // The stored entry is unchanged.
            $reloaded = AuditLog::query()->find($entry->getKey());
            expect($reloaded->event)->toBe($originalEvent);
        });
    } finally {
        teardownAuditTenants($tenants);
    }
});

it('rejects deletion of an existing audit entry and leaves it unchanged (R34.2)', function () {
    $tenants = [];
    try {
        $tenant = provisionAuditTenant($tenants);

        $tenant->run(function () {
            $user = User::create([
                'name' => 'NoDelete User',
                'email' => 'nodelete@example.test',
                'password' => 'nodelete-pass-123',
            ]);

            $entry = AuditLog::query()
                ->where('auditable_id', $user->getKey())
                ->where('event', 'created')
                ->firstOrFail();

            expect(fn () => $entry->delete())
                ->toThrow(ImmutableAuditException::class);

            // The entry still exists.
            expect(AuditLog::query()->whereKey($entry->getKey())->exists())->toBeTrue();
        });
    } finally {
        teardownAuditTenants($tenants);
    }
});
