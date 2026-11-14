<?php

declare(strict_types=1);

use App\Jobs\CopyTenantStorage;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\ReportExport;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MediaStorage;
use App\Services\TenantIntegrations\ConnectionTester;
use App\Services\TenantIntegrations\TenantExportStorage;
use App\Services\TenantIntegrations\TenantIntegrationStore;
use App\Services\WhiteLabelService;
use App\Support\AuditRecorder;
use App\Support\OutboundHostGuard;
use App\Tenancy\TenantIntegrationsBootstrapper;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Tenant-owned SMTP server and object storage
|--------------------------------------------------------------------------
|
| The default (SQLite) connection plays the tenant database and an in-memory
| SQLite `central` connection plays the registry, so no MySQL is needed.
| Connection tests and host resolution are replaced by developer-defined fakes:
| nothing here reaches the network. Tenancy is initialised without its database
| bootstrappers; the integration bootstrapper is exercised directly.
*/

beforeEach(function () {
    config([
        'database.connections.central' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true],
        'tenancy.bootstrappers' => [],
        'integrations.allow_private_hosts' => false,
        'integrations.allow_insecure_endpoints' => false,
    ]);
    DB::purge('central');

    foreach ([
        ['central', '2019_09_15_000010_create_tenants_table.php'],
        ['central', '2026_10_03_130000_create_plans_table.php'],
        ['central', '2026_10_03_130100_create_plan_limits_table.php'],
        ['central', '2026_10_03_130200_create_subscriptions_table.php'],
        ['central', '2026_10_03_130300_create_plan_features_table.php'],
        [config('database.default'), 'tenant/0001_01_01_000005_create_tenant_settings_table.php'],
        [config('database.default'), 'tenant/0001_01_01_000004_create_audit_logs_table.php'],
        [config('database.default'), 'tenant/0001_01_01_000020_create_report_exports_table.php'],
    ] as [$connection, $file]) {
        Artisan::call('migrate', ['--database' => $connection, '--realpath' => true, '--force' => true, '--path' => [database_path('migrations/'.$file)]]);
    }

    app()->instance(OutboundHostGuard::class, new OutboundHostGuard(fn () => ['93.184.216.34']));
});

afterEach(function () {
    tenancy()->end();
});

function integrationTenant(string $id = 't1'): Tenant
{
    DB::connection('central')->table('tenants')->insert(['id' => $id, 'subdomain' => $id, 'created_at' => now(), 'updated_at' => now()]);

    return Tenant::query()->findOrFail($id);
}

/** @return object{calls: list<array<int, mixed>>, fail: ?string} */
function fakeTester(?string $fail = null): object
{
    $tester = new class extends ConnectionTester
    {
        public array $calls = [];

        public ?string $fail = null;

        public function smtp(array $smtp, ?string $sendTo = null): void
        {
            $this->calls[] = ['smtp', $smtp, $sendTo];
            if ($this->fail !== null) {
                throw new RuntimeException($this->fail);
            }
        }

        public function storage(array $storage, string $tenantId): void
        {
            $this->calls[] = ['storage', $storage, $tenantId];
            if ($this->fail !== null) {
                throw new RuntimeException($this->fail);
            }
        }
    };
    $tester->fail = $fail;
    app()->instance(ConnectionTester::class, $tester);

    return $tester;
}

function smtpPayload(array $overrides = []): array
{
    return array_merge([
        'host' => 'smtp.acme-mail.test',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'mailer@acme.test',
        'password' => 'hunter2-secret',
        'from_address' => 'orders@acme.test',
        'from_name' => 'Acme Orders',
        'enabled' => true,
    ], $overrides);
}

function storagePayload(array $overrides = []): array
{
    return array_merge([
        'provider' => 'minio',
        'endpoint' => 'https://objects.acme-storage.test',
        'region' => 'eu-1',
        'bucket' => 'acme-files',
        'access_key' => 'AKIAACMEKEY1234',
        'secret_key' => 'super-secret-storage-key',
        'path_style' => true,
        'enabled' => true,
    ], $overrides);
}

function integrationStore(): TenantIntegrationStore
{
    return app(TenantIntegrationStore::class);
}

describe('encrypted storage of the settings', function () {
    it('keeps the secret encrypted in the tenant database and reads it back', function () {
        integrationStore()->put('smtp', 't1', smtpPayload());

        $raw = (string) DB::table('tenant_settings')->where('key', 'smtp_config')->value('value');

        expect($raw)->not->toContain('hunter2-secret')->not->toContain('smtp.acme-mail.test')
            ->and(integrationStore()->get('smtp', 't1')['password'])->toBe('hunter2-secret');
    });

    it('refuses a settings row that was copied from another tenant', function () {
        integrationStore()->put('smtp', 't1', smtpPayload());

        expect(integrationStore()->get('smtp', 't2'))->toBeNull();
    });

    it('treats an unreadable row as not configured', function () {
        DB::table('tenant_settings')->insert(['key' => 'smtp_config', 'value' => 'not-encrypted', 'created_at' => now(), 'updated_at' => now()]);

        expect(integrationStore()->get('smtp', 't1'))->toBeNull();
    });

    it('does not expose the setting through the white-label settings surface', function () {
        integrationStore()->put('smtp', 't1', smtpPayload());
        $tenant = integrationTenant();
        tenancy()->initialize($tenant);

        expect(array_keys(app(WhiteLabelService::class)->get()))->not->toContain('smtp_config');
    });
});

describe('applying a tenant\'s settings while its tenancy is active', function () {
    it('sends through the tenant SMTP first and the platform mailer second', function () {
        config(['mail.default' => 'array']);
        integrationStore()->put('smtp', 't1', smtpPayload());

        app(TenantIntegrationsBootstrapper::class)->bootstrap(integrationTenant());

        expect(config('mail.default'))->toBe('tenant_mail')
            ->and(config('mail.mailers.tenant_mail.mailers'))->toBe(['tenant_smtp', 'array'])
            ->and(config('mail.from.address'))->toBe('orders@acme.test')
            ->and(config('mail.from.name'))->toBe('Acme Orders')
            ->and((string) Mail::mailer('tenant_smtp')->getSymfonyTransport())->toContain('smtp.acme-mail.test');
    });

    it('delivers through the platform mailer when the tenant SMTP server is unreachable', function () {
        config(['mail.default' => 'array']);
        integrationStore()->put('smtp', 't1', smtpPayload(['host' => '127.0.0.1', 'port' => 1, 'encryption' => 'none', 'username' => null, 'password' => null]));
        app(TenantIntegrationsBootstrapper::class)->bootstrap(integrationTenant());

        $sent = 0;
        Event::listen(MessageSent::class, function () use (&$sent): void {
            $sent++;
        });

        // The tenant server refuses the connection; the message must still go out.
        Mail::raw('hello', fn ($message) => $message->to('buyer@example.com')->subject('Order'));

        expect($sent)->toBe(1);
    });

    it('restores the platform defaults when tenancy ends', function () {
        config(['mail.default' => 'array', 'mail.from' => ['address' => 'platform@example.com', 'name' => 'Platform']]);
        integrationStore()->put('smtp', 't1', smtpPayload());
        integrationStore()->put('storage', 't1', storagePayload());
        $bootstrapper = app(TenantIntegrationsBootstrapper::class);

        $bootstrapper->bootstrap(integrationTenant());
        $bootstrapper->revert();

        expect(config('mail.default'))->toBe('array')
            ->and(config('mail.from.address'))->toBe('platform@example.com')
            ->and(config('mail.mailers.tenant_smtp'))->toBeNull()
            ->and(config('filesystems.disks.tenant_storage'))->toBeNull()
            ->and(config('integrations.smtp_active'))->toBeFalse();
    });

    it('never lets one tenant\'s mail use another tenant\'s server in the same process', function () {
        config(['mail.default' => 'array']);
        integrationStore()->put('smtp', 'a', smtpPayload(['host' => 'smtp.tenant-a.test']));
        $bootstrapper = app(TenantIntegrationsBootstrapper::class);
        $bootstrapper->bootstrap(integrationTenant('a'));
        expect((string) Mail::mailer('tenant_smtp')->getSymfonyTransport())->toContain('tenant-a.test');
        $bootstrapper->revert();

        DB::table('tenant_settings')->delete();
        integrationStore()->put('smtp', 'b', smtpPayload(['host' => 'smtp.tenant-b.test']));
        $bootstrapper->bootstrap(integrationTenant('b'));

        expect((string) Mail::mailer('tenant_smtp')->getSymfonyTransport())->toContain('tenant-b.test')->not->toContain('tenant-a.test');
    });

    it('changes nothing for a tenant with no settings or with them switched off', function () {
        config(['mail.default' => 'array']);
        $bootstrapper = app(TenantIntegrationsBootstrapper::class);

        $bootstrapper->bootstrap(integrationTenant());
        expect(config('mail.default'))->toBe('array');
        $bootstrapper->revert();

        integrationStore()->put('smtp', 't1', smtpPayload(['enabled' => false]));
        integrationStore()->put('storage', 't1', storagePayload(['enabled' => false]));
        $bootstrapper->bootstrap(Tenant::query()->findOrFail('t1'));

        expect(config('mail.default'))->toBe('array')->and(config('filesystems.disks.tenant_storage'))->toBeNull();
    });

    it('skips quietly when the tenant tables do not exist yet', function () {
        config(['mail.default' => 'array']);
        Schema::drop('tenant_settings');

        app(TenantIntegrationsBootstrapper::class)->bootstrap(integrationTenant());

        expect(config('mail.default'))->toBe('array');
    });

    it('builds an S3 disk from the stored bucket settings', function () {
        integrationStore()->put('storage', 't1', storagePayload());

        app(TenantIntegrationsBootstrapper::class)->bootstrap(integrationTenant());

        expect(config('filesystems.disks.tenant_storage'))->toMatchArray([
            'driver' => 's3', 'bucket' => 'acme-files', 'region' => 'eu-1',
            'endpoint' => 'https://objects.acme-storage.test', 'use_path_style_endpoint' => true,
        ]);
    });

    it('uses the tenant sender instead of the white-label sender while its SMTP is active', function () {
        config(['mail.from' => ['address' => 'platform@example.com', 'name' => 'Platform']]);
        integrationStore()->put('smtp', 't1', smtpPayload());
        DB::table('tenant_settings')->insert(['key' => 'mail_from_address', 'value' => 'brand@acme.test', 'created_at' => now(), 'updated_at' => now()]);
        $tenant = integrationTenant();
        app(TenantIntegrationsBootstrapper::class)->bootstrap($tenant);
        tenancy()->initialize($tenant);

        expect(app(WhiteLabelService::class)->senderIdentity()['address'])->toBe('orders@acme.test');
    });
});

describe('integration endpoints', function () {
    it('restricts the routes to Company_Admin', function () {
        $router = app(Router::class);
        foreach ([['GET', 'api/tenant/integrations'], ['PUT', 'api/tenant/integrations/{type}'], ['POST', 'api/tenant/integrations/{type}/test'], ['DELETE', 'api/tenant/integrations/{type}']] as [$method, $uri]) {
            $route = collect($router->getRoutes())->first(fn ($r) => $r->uri() === $uri && in_array($method, $r->methods(), true));
            expect(array_values($router->gatherRouteMiddleware($route)))->toContain('Spatie\Permission\Middleware\RoleMiddleware:Company_Admin');
        }
    });

    it('saves a tested SMTP config and never returns the password', function () {
        $tester = fakeTester();
        tenancy()->initialize(integrationTenant());

        $response = $this->withoutMiddleware()->putJson('/api/tenant/integrations/smtp', smtpPayload());

        $response->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.has_password', true)
            ->assertJsonPath('data.host', 'smtp.acme-mail.test');
        expect($response->getContent())->not->toContain('hunter2-secret')
            ->and($tester->calls)->toHaveCount(1)
            ->and(integrationStore()->get('smtp', 't1')['password'])->toBe('hunter2-secret');
    });

    it('does not save a config whose connection test fails and reports why with 422', function () {
        fakeTester('Authentication failed for the account');
        tenancy()->initialize(integrationTenant());

        $this->withoutMiddleware()->putJson('/api/tenant/integrations/smtp', smtpPayload())
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields.connection.0', 'Authentication failed for the account');

        expect(integrationStore()->get('smtp', 't1'))->toBeNull();
    });

    it('reports a failed dry-run test without saving or failing the request', function () {
        fakeTester('Connection refused');
        tenancy()->initialize(integrationTenant());

        $this->withoutMiddleware()->postJson('/api/tenant/integrations/smtp/test', smtpPayload())
            ->assertOk()
            ->assertJsonPath('data.ok', false)
            ->assertJsonPath('data.message', 'Connection refused');

        expect(integrationStore()->get('smtp', 't1'))->toBeNull();
    });

    it('sends the test message only to the signed-in admin\'s own address', function () {
        $tester = fakeTester();
        tenancy()->initialize(integrationTenant());
        $admin = new User(['email' => 'admin@acme.test']);

        $this->actingAs($admin)->withoutMiddleware()->postJson('/api/tenant/integrations/smtp/test', smtpPayload(['send_test_email' => true]))
            ->assertOk()->assertJsonPath('data.ok', true);

        expect($tester->calls[0][2])->toBe('admin@acme.test');
    });

    it('keeps the stored password when it is left blank and the server is unchanged', function () {
        fakeTester();
        tenancy()->initialize(integrationTenant());
        $this->withoutMiddleware()->putJson('/api/tenant/integrations/smtp', smtpPayload())->assertOk();

        $this->withoutMiddleware()->putJson('/api/tenant/integrations/smtp', smtpPayload(['password' => '', 'from_name' => 'Renamed']))->assertOk();

        expect(integrationStore()->get('smtp', 't1'))->toMatchArray(['password' => 'hunter2-secret', 'from_name' => 'Renamed']);
    });

    it('requires the password again when the server changes so a stored secret is never sent to a new host', function () {
        fakeTester();
        tenancy()->initialize(integrationTenant());
        $this->withoutMiddleware()->putJson('/api/tenant/integrations/smtp', smtpPayload())->assertOk();

        $this->withoutMiddleware()->putJson('/api/tenant/integrations/smtp', smtpPayload(['host' => 'smtp.elsewhere.test', 'password' => '']))
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['password']]]]);

        expect(integrationStore()->get('smtp', 't1')['host'])->toBe('smtp.acme-mail.test');
    });

    it('rejects hosts on the private network, a disallowed port and invalid values', function (array $override, string $field) {
        $tester = fakeTester();
        tenancy()->initialize(integrationTenant());

        $this->withoutMiddleware()->putJson('/api/tenant/integrations/smtp', smtpPayload($override))
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['fields' => [$field]]]]);

        expect($tester->calls)->toBe([]);
    })->with([
        'loopback IP' => [['host' => '127.0.0.1'], 'host'],
        'cloud metadata IP' => [['host' => '169.254.169.254'], 'host'],
        'port outside the allowed list' => [['port' => 6379], 'port'],
        'not an email sender' => [['from_address' => 'nope'], 'from_address'],
        'unknown encryption' => [['encryption' => 'rot13'], 'encryption'],
        'username without password' => [['password' => null], 'password'],
    ]);

    it('rejects a hostname that resolves to a private address', function () {
        app()->instance(OutboundHostGuard::class, new OutboundHostGuard(fn () => ['10.0.0.8']));
        fakeTester();
        tenancy()->initialize(integrationTenant());

        $this->withoutMiddleware()->putJson('/api/tenant/integrations/smtp', smtpPayload())
            ->assertUnprocessable()->assertJsonPath('error.details.fields.host.0', 'This host is not allowed.');
    });

    it('saves storage settings, masks the access key and queues the one-off copy', function () {
        Queue::fake([CopyTenantStorage::class]);
        fakeTester();
        tenancy()->initialize(integrationTenant());

        $response = $this->withoutMiddleware()->putJson('/api/tenant/integrations/storage', storagePayload());

        $response->assertOk()
            ->assertJsonPath('data.access_key_hint', '••••1234')
            ->assertJsonPath('data.has_secret_key', true)
            ->assertJsonPath('data.copy.status', 'queued');
        expect($response->getContent())->not->toContain('super-secret-storage-key')->not->toContain('AKIAACMEKEY1234');
        Queue::assertPushed(CopyTenantStorage::class, 1);
    });

    it('does not queue another copy when the same bucket is saved again', function () {
        Queue::fake([CopyTenantStorage::class]);
        fakeTester();
        tenancy()->initialize(integrationTenant());
        $this->withoutMiddleware()->putJson('/api/tenant/integrations/storage', storagePayload())->assertOk();

        $this->withoutMiddleware()->putJson('/api/tenant/integrations/storage', storagePayload(['secret_key' => '', 'access_key' => '', 'region' => 'eu-1']))->assertOk();

        Queue::assertPushed(CopyTenantStorage::class, 1);
    });

    it('rejects plain-http and private storage endpoints', function (string $endpoint) {
        fakeTester();
        tenancy()->initialize(integrationTenant());

        $this->withoutMiddleware()->putJson('/api/tenant/integrations/storage', storagePayload(['endpoint' => $endpoint]))
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['endpoint']]]]);
    })->with(['http://objects.acme-storage.test', 'https://10.0.0.5', 'https://169.254.169.254', 'ftp://objects.acme-storage.test', 'https://user:pw@objects.acme-storage.test']);

    it('removes the config and goes back to the platform defaults', function () {
        fakeTester();
        tenancy()->initialize(integrationTenant());
        $this->withoutMiddleware()->putJson('/api/tenant/integrations/smtp', smtpPayload())->assertOk();

        $this->withoutMiddleware()->deleteJson('/api/tenant/integrations/smtp')->assertNoContent();

        expect(integrationStore()->get('smtp', 't1'))->toBeNull();
    });

    it('returns 403 when the plan switches the feature off', function () {
        $tenant = integrationTenant();
        $plan = Plan::query()->create(['name' => 'Basic', 'price' => 0, 'billing_cycle' => 'monthly', 'is_active' => true]);
        $plan->features()->create(['key' => PlanFeature::CUSTOM_SMTP, 'enabled' => false]);
        Subscription::query()->create(['tenant_id' => 't1', 'plan_id' => $plan->getKey(), 'status' => Subscription::STATUS_ACTIVE, 'starts_at' => now()]);
        fakeTester();
        tenancy()->initialize($tenant);

        $this->withoutMiddleware()->putJson('/api/tenant/integrations/smtp', smtpPayload())->assertForbidden();
        $this->withoutMiddleware()->getJson('/api/tenant/integrations')->assertOk()
            ->assertJsonPath('data.smtp.available', false)->assertJsonPath('data.storage.available', true);
    });

    it('records which fields changed in the audit trail but never their values', function () {
        fakeTester();
        $audit = new class extends AuditRecorder
        {
            public array $events = [];

            public function record(string $event, ?User $user = null, array $context = [], ?Request $request = null, ?string $reason = null): void
            {
                $this->events[] = [$event, $context];
            }
        };
        app()->instance(AuditRecorder::class, $audit);
        tenancy()->initialize(integrationTenant());

        $this->withoutMiddleware()->putJson('/api/tenant/integrations/smtp', smtpPayload())->assertOk();

        [$event, $context] = $audit->events[0];
        expect($event)->toBe('integration_smtp_saved')
            ->and($context['changed'])->toContain('host', 'password')
            ->and(json_encode($context))->not->toContain('hunter2-secret')->not->toContain('smtp.acme-mail.test');
    });
});

describe('uploads and exports in the tenant bucket', function () {
    beforeEach(function () {
        $this->platformRoot = sys_get_temp_dir().'/platform-'.uniqid();
        $this->bucketRoot = sys_get_temp_dir().'/bucket-'.uniqid();
        config([
            'filesystems.disks.test_platform' => ['driver' => 'local', 'root' => $this->platformRoot],
            'filesystems.uploads_disk' => 'test_platform',
        ]);
    });

    afterEach(function () {
        File::deleteDirectory($this->platformRoot);
        File::deleteDirectory($this->bucketRoot);
    });

    function useTenantBucket(string $root): void
    {
        config(['filesystems.disks.tenant_storage' => ['driver' => 'local', 'root' => $root]]);
    }

    $logo = fn () => 'tenants/t1/logo/'.str_repeat('a', 32).'.png';

    it('writes uploads to the tenant bucket when the tenant has one, else to the platform disk', function () use ($logo) {
        tenancy()->initialize(integrationTenant());
        expect(app(MediaStorage::class)->usesTenantStorage())->toBeFalse();

        useTenantBucket($this->bucketRoot);
        Storage::disk('tenant_storage')->put($logo(), 'x');

        expect(app(MediaStorage::class)->usesTenantStorage())->toBeTrue()
            ->and(file_exists($this->bucketRoot.'/'.$logo()))->toBeTrue();
    });

    it('serves a file that has not been copied yet from the platform disk', function () use ($logo) {
        tenancy()->initialize(integrationTenant());
        useTenantBucket($this->bucketRoot);
        Storage::disk('test_platform')->put($logo(), 'png-bytes');

        $this->withoutMiddleware()->get('/api/media/'.$logo())->assertOk();
    });

    it('returns 404 for another tenant\'s file even when it exists', function () {
        tenancy()->initialize(integrationTenant());
        useTenantBucket($this->bucketRoot);
        $other = 'tenants/t2/logo/'.str_repeat('b', 32).'.png';
        Storage::disk('tenant_storage')->put($other, 'x');

        $this->withoutMiddleware()->get('/api/media/'.$other)->assertNotFound();
    });

    it('deletes a replaced upload from both the bucket and the platform disk', function () use ($logo) {
        tenancy()->initialize(integrationTenant());
        useTenantBucket($this->bucketRoot);
        Storage::disk('tenant_storage')->put($logo(), 'x');
        Storage::disk('test_platform')->put($logo(), 'x');

        app(MediaStorage::class)->deleteUrl('/api/media/'.$logo());

        expect(Storage::disk('tenant_storage')->exists($logo()))->toBeFalse()
            ->and(Storage::disk('test_platform')->exists($logo()))->toBeFalse();
    });

    it('moves a finished export into the tenant bucket under the tenant prefix', function () {
        tenancy()->initialize(integrationTenant());
        useTenantBucket($this->bucketRoot);
        $local = tempnam(sys_get_temp_dir(), 'exp');
        file_put_contents($local, 'a,b');
        $storage = app(TenantExportStorage::class);

        $key = $storage->publish($local, 'exports/reports/report-1-sales.csv');

        expect($key)->toBe('tenants/t1/exports/reports/report-1-sales.csv')
            ->and(Storage::disk('tenant_storage')->get($key))->toBe('a,b')
            ->and(file_exists($local))->toBeFalse()
            ->and($storage->owns($key))->toBeTrue()
            ->and($storage->owns('tenants/t2/exports/reports/x.csv'))->toBeFalse()
            ->and($storage->owns('tenants/t1/../t2/x.csv'))->toBeFalse()
            ->and($storage->owns('exports/reports/x.csv'))->toBeFalse();
    });

    it('lets the requester download an export from the tenant bucket', function () {
        tenancy()->initialize(integrationTenant());
        useTenantBucket($this->bucketRoot);
        Storage::disk('tenant_storage')->put('tenants/t1/exports/reports/report-1-sales.csv', 'a,b');
        $export = ReportExport::query()->create([
            'report_type' => 'sales', 'format' => 'csv', 'spec' => [], 'status' => 'completed',
            'file_path' => 'tenants/t1/exports/reports/report-1-sales.csv', 'requested_by' => 5,
        ]);
        $user = Mockery::mock(User::class)->makePartial();
        $user->setAttribute('id', 5);

        $response = $this->actingAs($user)->withoutMiddleware()->get('/api/reports/exports/'.$export->id.'/download');

        $response->assertOk()->assertDownload('report-1-sales.csv');
    });

    it('returns 404 when someone else asks for the export and is not an admin', function () {
        tenancy()->initialize(integrationTenant());
        useTenantBucket($this->bucketRoot);
        Storage::disk('tenant_storage')->put('tenants/t1/exports/reports/report-1-sales.csv', 'a,b');
        $export = ReportExport::query()->create([
            'report_type' => 'sales', 'format' => 'csv', 'spec' => [], 'status' => 'completed',
            'file_path' => 'tenants/t1/exports/reports/report-1-sales.csv', 'requested_by' => 5,
        ]);
        $user = Mockery::mock(User::class)->makePartial();
        $user->setAttribute('id', 9);
        $user->shouldReceive('hasRole')->andReturn(false);

        $this->actingAs($user)->withoutMiddleware()->get('/api/reports/exports/'.$export->id.'/download')->assertNotFound();
    });

    it('never serves a path outside the export folder from the local disk', function () {
        tenancy()->initialize(integrationTenant());
        $export = ReportExport::query()->create([
            'report_type' => 'sales', 'format' => 'csv', 'spec' => [], 'status' => 'completed',
            'file_path' => '../../.env', 'requested_by' => 5,
        ]);
        $user = Mockery::mock(User::class)->makePartial();
        $user->setAttribute('id', 5);

        $this->actingAs($user)->withoutMiddleware()->get('/api/reports/exports/'.$export->id.'/download')->assertNotFound();
    });

    it('copies existing media and exports into the new bucket once, leaving the originals', function () use ($logo) {
        Storage::fake('local');
        tenancy()->initialize(integrationTenant());
        integrationStore()->put('storage', 't1', storagePayload() + ['copy' => ['status' => 'queued']]);
        useTenantBucket($this->bucketRoot);
        Storage::disk('test_platform')->put($logo(), 'mine');
        Storage::disk('test_platform')->put('tenants/t2/logo/'.str_repeat('c', 32).'.png', 'theirs');
        Storage::disk('local')->put('exports/reports/report-7-sales.csv', 'a,b');
        $export = ReportExport::query()->create([
            'report_type' => 'sales', 'format' => 'csv', 'spec' => [], 'status' => 'completed',
            'file_path' => 'exports/reports/report-7-sales.csv', 'requested_by' => 5,
        ]);
        $run = fn () => (new CopyTenantStorage)->handle(app(TenantIntegrationStore::class), app(MediaStorage::class), app(TenantExportStorage::class));

        $run();

        $export->refresh();
        expect(Storage::disk('tenant_storage')->get($logo()))->toBe('mine')
            ->and(Storage::disk('tenant_storage')->exists('tenants/t2/logo/'.str_repeat('c', 32).'.png'))->toBeFalse()
            ->and(Storage::disk('test_platform')->exists($logo()))->toBeTrue()
            ->and($export->file_path)->toBe('tenants/t1/exports/reports/report-7-sales.csv')
            ->and($export->download_url)->toBe('/api/reports/exports/'.$export->id.'/download')
            ->and(Storage::disk('tenant_storage')->get($export->file_path))->toBe('a,b')
            ->and(integrationStore()->get('storage', 't1')['copy'])->toMatchArray(['status' => 'completed', 'copied' => 2, 'failed' => 0]);

        $run();

        expect(integrationStore()->get('storage', 't1')['copy'])->toMatchArray(['status' => 'completed', 'copied' => 0, 'skipped' => 1]);
    });
});
