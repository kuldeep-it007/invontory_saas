<?php

declare(strict_types=1);

use App\Auth\Roles;
use App\Exceptions\ImmutableAuditException;
use App\Models\Plan;
use App\Models\PlatformAuditLog;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| Platform Admin console: profile, settings/branding, tenant details,
| plan changes and the central audit trail (DB-gated).
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/** Central Platform_Admin acting through Sanctum. */
function consoleAdmin(string $suffix): User
{
    $guard = config('auth.defaults.guard', 'web');
    Role::findOrCreate(Roles::PLATFORM_ADMIN, $guard);
    $user = new User(['name' => 'Console Admin', 'email' => 'console-'.$suffix.'@example.test', 'password' => 'password123']);
    $user->setConnection('central');
    $user->save();
    $user->assignRole(Roles::PLATFORM_ADMIN);
    Sanctum::actingAs($user, ['*']);

    return $user;
}

it('updates the profile and audits it without leaking the password', function () {
    $suffix = bin2hex(random_bytes(3));
    $user = consoleAdmin($suffix);

    $this->patchJson('/api/admin/profile', ['name' => 'Renamed', 'phone' => '+91 98765 43210'])
        ->assertOk()->assertJsonPath('data.name', 'Renamed');

    $log = PlatformAuditLog::query()->where('event', 'platform.profile_updated')->latest('id')->first();
    expect($log)->not->toBeNull();
    expect(json_encode($log->new_values))->not->toContain('password');

    // Changing the password requires the current one.
    $this->patchJson('/api/admin/profile', [
        'current_password' => 'wrong-password', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1',
    ])->assertStatus(422);

    $user->delete();
});

it('stores platform settings with validation and records the change', function () {
    consoleAdmin(bin2hex(random_bytes(3)));

    $this->patchJson('/api/admin/settings', ['support_email' => 'not-an-email'])->assertStatus(422);
    $this->patchJson('/api/admin/settings', ['platform_name' => ''])->assertStatus(422);

    $this->patchJson('/api/admin/settings', ['platform_name' => 'Acme Cloud', 'default_currency' => 'inr'])
        ->assertOk()
        ->assertJsonPath('data.platform_name', 'Acme Cloud')
        ->assertJsonPath('data.default_currency', 'INR');

    $this->getJson('/api/platform/branding')->assertOk()->assertJsonPath('data.platform_name', 'Acme Cloud');
    expect(PlatformAuditLog::query()->where('event', 'platform.settings_updated')->exists())->toBeTrue();

    PlatformSetting::query()->whereIn('key', ['platform_name', 'default_currency'])->delete();
});

it('rejects a non-image logo upload', function () {
    consoleAdmin(bin2hex(random_bytes(3)));

    $this->post('/api/admin/settings/logo', ['file' => UploadedFile::fake()->create('x.png', 5, 'text/plain')], ['Accept' => 'application/json'])
        ->assertStatus(422);
    $this->post('/api/admin/settings/other', ['file' => UploadedFile::fake()->image('x.png', 32, 32)], ['Accept' => 'application/json'])
        ->assertStatus(404);
});

it('denies the admin endpoints to a user without the Platform_Admin role', function () {
    $user = new User(['name' => 'Nobody', 'email' => 'nobody-'.bin2hex(random_bytes(3)).'@example.test', 'password' => 'password123']);
    $user->setConnection('central');
    $user->save();
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/admin/settings')->assertStatus(403);
    $this->getJson('/api/admin/audit-logs')->assertStatus(403);

    $user->delete();
});

it('edits tenant details, changes plan, and keeps a history', function () {
    consoleAdmin(bin2hex(random_bytes(3)));
    $suffix = strtolower(bin2hex(random_bytes(4)));

    $basic = Plan::create(['name' => 'Basic '.$suffix, 'price' => 10, 'billing_cycle' => 'monthly', 'is_active' => true]);
    $pro = Plan::create(['name' => 'Pro '.$suffix, 'price' => 50, 'billing_cycle' => 'monthly', 'is_active' => true]);
    $tenant = Tenant::create(['id' => 'pc'.$suffix, 'subdomain' => 'pc-'.$suffix]);
    Subscription::create(['tenant_id' => $tenant->getTenantKey(), 'plan_id' => $basic->getKey(), 'status' => 'active', 'starts_at' => now()]);

    try {
        $this->patchJson('/api/admin/tenants/'.$tenant->getTenantKey(), ['billing_email' => 'bad'])->assertStatus(422);
        $this->patchJson('/api/admin/tenants/'.$tenant->getTenantKey(), ['billing_email' => 'bill@example.test', 'tax_id' => 'GST1'])
            ->assertOk()->assertJsonPath('data.details.billing_email', 'bill@example.test');

        // Same plan is rejected; a different active plan is applied as an upgrade.
        $this->postJson("/api/admin/tenants/{$tenant->getTenantKey()}/plan", ['plan_id' => $basic->getKey()])->assertStatus(422);
        $this->postJson("/api/admin/tenants/{$tenant->getTenantKey()}/plan", ['plan_id' => $pro->getKey(), 'reason' => 'Customer request'])
            ->assertOk()->assertJsonPath('data.subscription.plan_id', $pro->getKey());

        // Forcing requires a reason.
        $this->postJson("/api/admin/tenants/{$tenant->getTenantKey()}/plan", ['plan_id' => $basic->getKey(), 'force' => true])->assertStatus(422);

        $history = $this->getJson("/api/admin/tenants/{$tenant->getTenantKey()}/history")->assertOk()->json('data');
        $events = array_column($history, 'event');
        expect($events)->toContain('tenant.updated', 'subscription.plan_changed');
        expect(collect($history)->firstWhere('event', 'subscription.plan_changed')['new_values']['direction'])->toBe('upgrade');
    } finally {
        PlatformAuditLog::query()->where('tenant_id', $tenant->getTenantKey())->get()->each(
            fn ($l) => DB::connection('central')->table('platform_audit_logs')->where('id', $l->id)->delete(),
        );
        Subscription::query()->where('tenant_id', $tenant->getTenantKey())->delete();
        try {
            $tenant->database()->getTenantDatabaseManager()->deleteDatabase($tenant);
        } catch (Throwable $e) {
            // best-effort
        }
        $tenant->domains()->delete();
        $tenant->delete();
        $basic->delete();
        $pro->delete();
    }
});

it('does not allow platform audit rows to be updated or deleted', function () {
    $log = PlatformAuditLog::record('platform.test_event');

    expect(fn () => $log->update(['reason' => 'x']))->toThrow(ImmutableAuditException::class);
    expect(fn () => $log->delete())->toThrow(ImmutableAuditException::class);

    DB::connection('central')->table('platform_audit_logs')->where('id', $log->id)->delete();
});
