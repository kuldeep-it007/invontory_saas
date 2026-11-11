<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Services\WhiteLabelService;
use App\Support\AuditRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Task 3.4 — WhiteLabelService + tenant_settings (DB-gated)
|--------------------------------------------------------------------------
|
| Exercises the white-label settings service against a real provisioned tenant
| database:
|   • read returns a coherent settings object with every managed key present,
|     null when unset (R3.1);
|   • update persists recognised keys and ignores unknown ones, returning the
|     refreshed object (R3.1);
|   • the update APPLIES even when the best-effort audit write fails (R3.5);
|   • senderIdentity() resolves the per-tenant outbound `from`, falling back to
|     the global mail.from when unset (R3.3).
|
| Every test is DB-GATED (needs MySQL + CREATE DATABASE for stancl tenancy) and
| skips gracefully when MySQL is unreachable, mirroring the audit suite.
|
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Provision + migrate an isolated tenant for white-label tests, returning it.
 */
function provisionWhiteLabelTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'wl'.$suffix,
        'subdomain' => 'wl-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'wl-'.$suffix.'.example.test']);
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

function teardownWhiteLabelTenants(array $registry): void
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

it('reads a coherent settings object with every managed key present and null when unset (R3.1)', function () {
    $tenants = [];
    try {
        $tenant = provisionWhiteLabelTenant($tenants);

        $tenant->run(function () {
            $settings = app(WhiteLabelService::class)->get();

            expect($settings)->toHaveKeys([
                'logo', 'brand_name', 'color_theme', 'favicon',
                'custom_domain', 'mail_from_address', 'mail_from_name',
            ]);

            foreach ($settings as $value) {
                expect($value)->toBeNull();
            }
        });
    } finally {
        teardownWhiteLabelTenants($tenants);
    }
});

it('persists recognised keys, ignores unknown keys, and returns the refreshed object (R3.1)', function () {
    $tenants = [];
    try {
        $tenant = provisionWhiteLabelTenant($tenants);

        $tenant->run(function () {
            $service = app(WhiteLabelService::class);

            $result = $service->update([
                'brand_name' => 'Acme Distribution',
                'color_theme' => '{"primary":"#0055ff"}',
                'logo' => 'https://cdn.example.test/logo.png',
                // Unknown key must be ignored, never persisted.
                'not_a_setting' => 'should-be-ignored',
            ]);

            expect($result['brand_name'])->toBe('Acme Distribution')
                ->and($result['color_theme'])->toBe('{"primary":"#0055ff"}')
                ->and($result['logo'])->toBe('https://cdn.example.test/logo.png')
                ->and($result['favicon'])->toBeNull();

            // The unknown key never created a row.
            expect(TenantSetting::query()->where('key', 'not_a_setting')->exists())->toBeFalse();
            // Only the three recognised rows exist.
            expect(TenantSetting::query()->count())->toBe(3);

            // A re-read reflects the persisted values (durable).
            $reread = $service->get();
            expect($reread['brand_name'])->toBe('Acme Distribution');

            // A null value clears a previously-set key.
            $cleared = $service->update(['brand_name' => null]);
            expect($cleared['brand_name'])->toBeNull();
        });
    } finally {
        teardownWhiteLabelTenants($tenants);
    }
});

it('applies the update even when the best-effort audit write fails (R3.5)', function () {
    $tenants = [];
    try {
        $tenant = provisionWhiteLabelTenant($tenants);

        $tenant->run(function () {
            // A recorder whose record() always throws simulates a failing audit
            // write. The update MUST still apply (R3.5).
            $throwingRecorder = new class extends AuditRecorder
            {
                public function record(string $event, ?User $user = null, array $context = [], ?Request $request = null, ?string $reason = null): void
                {
                    throw new RuntimeException('audit backend unavailable');
                }
            };

            $service = new WhiteLabelService($throwingRecorder);

            $result = $service->update([
                'brand_name' => 'Resilient Brand',
                'favicon' => 'https://cdn.example.test/favicon.ico',
            ]);

            // Update applied and returned despite the audit write throwing.
            expect($result['brand_name'])->toBe('Resilient Brand')
                ->and($result['favicon'])->toBe('https://cdn.example.test/favicon.ico');

            // The settings are durably persisted in the tenant database.
            expect(TenantSetting::query()->where('key', 'brand_name')->value('value'))
                ->toBe('Resilient Brand');
        });
    } finally {
        teardownWhiteLabelTenants($tenants);
    }
});

it('records a best-effort audit entry when the audit write succeeds (R3.5)', function () {
    $tenants = [];
    try {
        $tenant = provisionWhiteLabelTenant($tenants);

        $tenant->run(function () {
            app(WhiteLabelService::class)->update(['brand_name' => 'Audited Brand']);

            expect(AuditLog::query()->where('event', 'white_label_updated')->count())
                ->toBeGreaterThanOrEqual(1);
        });
    } finally {
        teardownWhiteLabelTenants($tenants);
    }
});

it('resolves the per-tenant outbound sender identity and falls back to global config (R3.3)', function () {
    $tenants = [];
    try {
        $tenant = provisionWhiteLabelTenant($tenants);

        $tenant->run(function () {
            $service = app(WhiteLabelService::class);

            // Unset → falls back to the application's global mail.from config.
            $fallback = $service->senderIdentity();
            expect($fallback['address'])->toBe(config('mail.from.address'))
                ->and($fallback['name'])->toBe(config('mail.from.name'));

            // Configured → uses the tenant's own identity.
            $service->update([
                'mail_from_address' => 'noreply@acme.test',
                'mail_from_name' => 'Acme Mailer',
            ]);

            $identity = $service->senderIdentity();
            expect($identity['address'])->toBe('noreply@acme.test')
                ->and($identity['name'])->toBe('Acme Mailer');
        });
    } finally {
        teardownWhiteLabelTenants($tenants);
    }
});
