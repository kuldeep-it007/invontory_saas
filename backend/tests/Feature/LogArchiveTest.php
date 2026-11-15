<?php

declare(strict_types=1);

use App\Exceptions\ImmutableAuditException;
use App\Http\Controllers\Admin\TelescopeAccessController;
use App\Http\Middleware\AssignRequestContext;
use App\Http\Middleware\EnforceHttps;
use App\Http\Middleware\OnlyCentralDomains;
use App\Logging\ArchiveLogHandler;
use App\Logging\MaskSensitiveData;
use App\Models\LogArchive;
use App\Models\User;
use App\Services\Logging\LogArchiver;
use App\Services\MediaStorage;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Monolog\Level;
use Monolog\LogRecord;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
|--------------------------------------------------------------------------
| Centralized logging, archive to the master bucket, retention, access
|--------------------------------------------------------------------------
|
| An in-memory SQLite `central` connection holds tenants, log_archives and the
| platform audit log; a fake disk plays the master bucket. Nothing touches the
| network or a real bucket.
*/

beforeEach(function () {
    config([
        'database.connections.central' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true],
        'tenancy.bootstrappers' => [],
        'tenancy.central_domains' => ['platform.test'],
        'filesystems.uploads_disk' => 'master',
        'logging.archive.path' => $this->archivePath = sys_get_temp_dir().'/log-archive-test-'.uniqid(),
        'logging.archive.retention_days' => 400,
        'logging.archive.alert_email' => null,
    ]);
    DB::purge('central');
    Storage::fake('master');

    foreach (['2019_09_15_000010_create_tenants_table.php', '2026_11_27_000000_create_log_archives_table.php'] as $file) {
        Artisan::call('migrate', ['--database' => 'central', '--realpath' => true, '--force' => true, '--path' => [database_path('migrations/'.$file)]]);
    }
    Artisan::call('migrate', ['--database' => config('database.default'), '--realpath' => true, '--force' => true,
        '--path' => [database_path('migrations/tenant/0001_01_01_000004_create_audit_logs_table.php')]]);

    Schema::connection('central')->create('platform_audit_logs', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('actor_id')->nullable();
        $table->string('actor_email')->nullable();
        $table->string('event', 64);
        $table->string('tenant_id')->nullable();
        $table->json('old_values')->nullable();
        $table->json('new_values')->nullable();
        $table->text('reason')->nullable();
        $table->string('ip_address', 45)->nullable();
        $table->timestamp('created_at')->useCurrent();
    });

    DB::connection('central')->table('tenants')->insert([
        'id' => 't-123', 'subdomain' => 'acme', 'data' => json_encode(['company_name' => 'Acme Traders Pvt Ltd']),
        'created_at' => now(), 'updated_at' => now(),
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->archivePath);
    tenancy()->end();
});

function writeArchiveLine(string $base, ?string $tenantId, string $message, array $context = [], ?DateTimeImmutable $at = null): void
{
    $handler = new ArchiveLogHandler($base, 'Asia/Kolkata', Level::Info);
    $handler->pushProcessor(new MaskSensitiveData);
    $handler->handle(new LogRecord(
        datetime: $at ?? new DateTimeImmutable('now', new DateTimeZone('UTC')),
        channel: 'archive',
        level: Level::Info,
        message: $message,
        context: $context,
        extra: array_filter(['tenant_id' => $tenantId, 'request_id' => 'req-12345678', 'user_id' => 7]),
    ));
}

function auditEvents(): array
{
    return DB::connection('central')->table('platform_audit_logs')->pluck('event')->all();
}

describe('masking', function () {
    it('masks credential keys at any depth and secrets inside free text', function () {
        $masker = new MaskSensitiveData;

        $masked = $masker->maskArray([
            'password' => 'hunter2',
            'nested' => ['api_key' => 'k-123', 'Authorization' => 'Bearer abc.def.ghi', 'email' => 'buyer@example.com'],
            'note' => 'login with password=hunter2 and token: 12|AbCdEfGhIjKlMnOpQrStUvWxYz0123456789abcdefgh',
            'tokenable_id' => 5,
        ]);

        expect($masked['password'])->toBe('[MASKED]')
            ->and($masked['nested']['api_key'])->toBe('[MASKED]')
            ->and($masked['nested']['Authorization'])->toBe('[MASKED]')
            ->and($masked['nested']['email'])->toBe('buyer@example.com')
            ->and($masked['note'])->not->toContain('hunter2')->not->toContain('AbCdEfGh')
            ->and($masked['tokenable_id'])->toBe(5);
        expect($masker->maskString('Authorization: Bearer eyJhbGciOiJIUzI1NiJ9.payload.sig'))->toBe('Authorization: Bearer [MASKED]');
    });
});

describe('request correlation', function () {
    it('returns a generated X-Request-Id and reuses a valid incoming one', function () {
        $middleware = new AssignRequestContext;

        $generated = $middleware->handle(Request::create('/api/me'), fn () => response('ok'));
        $reused = $middleware->handle(tap(Request::create('/api/me'), fn ($r) => $r->headers->set('X-Request-Id', 'abc-12345678')), fn () => response('ok'));
        $rejected = $middleware->handle(tap(Request::create('/api/me'), fn ($r) => $r->headers->set('X-Request-Id', "bad\nid")), fn () => response('ok'));

        expect($generated->headers->get('X-Request-Id'))->toMatch('/^[0-9a-f-]{36}$/')
            ->and($reused->headers->get('X-Request-Id'))->toBe('abc-12345678')
            ->and($rejected->headers->get('X-Request-Id'))->not->toContain("\n")->toMatch('/^[0-9a-f-]{36}$/');
    });

    it('runs before every other middleware', function () {
        $global = app(Kernel::class)->getGlobalMiddleware();

        expect($global[0])->toBe(AssignRequestContext::class);
    });
});

describe('tenant-segregated archive files', function () {
    it('writes tenant and platform lines to separate daily files with IST timestamps', function () {
        // 20:00 UTC = 01:30 next day IST: the file and timestamp must use the IST date.
        $at = new DateTimeImmutable('2026-11-14 20:00:00', new DateTimeZone('UTC'));

        writeArchiveLine($this->archivePath, 't-123', 'audit.login', ['event' => 'audit.login', 'password' => 'hunter2'], $at);
        writeArchiveLine($this->archivePath, null, 'platform_audit.plan.updated', [], $at);

        $tenantFile = $this->archivePath.'/tenants/t-123/2026-11-15.jsonl';
        $line = json_decode((string) file_get_contents($tenantFile), true);

        expect(file_exists($this->archivePath.'/platform/2026-11-15.jsonl'))->toBeTrue()
            ->and($line['ts'])->toBe('2026-11-15T01:30:00.000+05:30')
            ->and($line['tenant_id'])->toBe('t-123')
            ->and($line['user_id'])->toBe(7)
            ->and($line['request_id'])->toBe('req-12345678')
            ->and($line['event'])->toBe('audit.login')
            ->and($line['context']['password'])->toBe('[MASKED]');
    });

    it('never uses an unsafe tenant id as a folder name', function () {
        writeArchiveLine($this->archivePath, '../../etc', 'x');

        expect(glob($this->archivePath.'/platform/*.jsonl'))->toHaveCount(1)
            ->and(is_dir($this->archivePath.'/etc'))->toBeFalse();
    });
});

describe('shipping to the master bucket', function () {
    it('uploads finished days under tenant id and name, indexes them and removes the local copy', function () {
        $yesterday = CarbonImmutable::now('Asia/Kolkata')->subDay();
        writeArchiveLine($this->archivePath, 't-123', 'audit.login', [], $yesterday->setTime(12, 0)->utc()->toDateTimeImmutable());
        writeArchiveLine($this->archivePath, 't-123', 'still.today');

        $result = app(LogArchiver::class)->shipLocalFiles();

        $path = sprintf('tenant-logs/t-123/acme-traders-pvt-ltd/%s/app-%s.jsonl.gz', $yesterday->format('Y/m/d'), $yesterday->toDateString());
        $archive = LogArchive::query()->where('path', $path)->firstOrFail();

        expect($result)->toBe(['uploaded' => 1, 'failed' => 0, 'skipped' => 0])
            ->and(gzdecode(Storage::disk('master')->get($path)))->toContain('audit.login')
            ->and($archive->sha256)->toBe(hash('sha256', Storage::disk('master')->get($path)))
            ->and($archive->tenant_slug)->toBe('acme-traders-pvt-ltd')
            ->and(glob($this->archivePath.'/tenants/t-123/*.jsonl'))->toHaveCount(1); // today's file stays
    });

    it('retries a failing upload, keeps the local file and reports the failure', function () {
        Sleep::fake();
        $broken = Mockery::mock(Filesystem::class);
        $broken->shouldReceive('exists')->andReturn(false);
        $broken->shouldReceive('put')->andThrow(new RuntimeException('bucket unreachable'));
        $archiver = new class(app(MediaStorage::class), $broken) extends LogArchiver
        {
            public function __construct(MediaStorage $media, private readonly Filesystem $fake)
            {
                parent::__construct($media);
            }

            public function disk(): Filesystem
            {
                return $this->fake;
            }
        };
        $yesterday = CarbonImmutable::now('Asia/Kolkata')->subDay();
        writeArchiveLine($this->archivePath, null, 'x', [], $yesterday->setTime(12, 0)->utc()->toDateTimeImmutable());

        $result = $archiver->shipLocalFiles();

        expect($result['failed'])->toBe(1)
            ->and(glob($this->archivePath.'/platform/*.jsonl'))->toHaveCount(1)
            ->and(LogArchive::query()->count())->toBe(0)
            ->and(auditEvents())->toContain('logs.archive_failed');
        Sleep::assertSleptTimes(2);
    });

    it('exports a day of platform and tenant audit rows once, skipping it on the next run', function () {
        $day = CarbonImmutable::now('Asia/Kolkata')->subDay()->startOfDay();
        DB::connection('central')->table('platform_audit_logs')->insert(['event' => 'plan.updated', 'created_at' => $day->setTime(10, 0)->utc()]);
        DB::table('audit_logs')->insert([
            'event' => 'login', 'auditable_type' => 'App\Models\User', 'auditable_id' => 1,
            'new_values' => json_encode(['password' => 'hunter2']), 'created_at' => $day->setTime(11, 0)->utc(), 'updated_at' => now(),
        ]);

        $first = app(LogArchiver::class)->exportAuditTables($day);
        $second = app(LogArchiver::class)->exportAuditTables($day);

        $tenantAudit = LogArchive::query()->where('tenant_id', 't-123')->where('kind', 'audit')->firstOrFail();
        expect($first['uploaded'])->toBe(2)
            ->and($second)->toMatchArray(['uploaded' => 0, 'skipped' => 2])
            ->and(gzdecode(Storage::disk('master')->get($tenantAudit->path)))->toContain('"event":"login"')->not->toContain('hunter2');
    });
});

describe('retention and immutability', function () {
    it('prunes only archives older than the retention period and records it', function () {
        $old = CarbonImmutable::now('Asia/Kolkata')->subDays(401)->toDateString();
        $recent = CarbonImmutable::now('Asia/Kolkata')->subDays(300)->toDateString();
        foreach ([$old, $recent] as $date) {
            Storage::disk('master')->put("platform-logs/{$date}.gz", 'x');
            LogArchive::query()->create(['scope' => 'platform', 'kind' => 'app', 'log_date' => $date, 'path' => "platform-logs/{$date}.gz", 'size' => 1, 'sha256' => str_repeat('a', 64)]);
        }

        $deleted = app(LogArchiver::class)->prune();

        expect($deleted)->toBe(1)
            ->and(Storage::disk('master')->exists("platform-logs/{$old}.gz"))->toBeFalse()
            ->and(Storage::disk('master')->exists("platform-logs/{$recent}.gz"))->toBeTrue()
            ->and(auditEvents())->toContain('logs.pruned');
    });

    it('cannot edit or delete an archive index row outside the retention job', function () {
        $row = LogArchive::query()->create(['scope' => 'platform', 'kind' => 'app', 'log_date' => now()->toDateString(), 'path' => 'p.gz', 'size' => 1, 'sha256' => str_repeat('a', 64)]);

        expect(fn () => $row->update(['size' => 2]))->toThrow(ImmutableAuditException::class)
            ->and(fn () => $row->delete())->toThrow(ImmutableAuditException::class);
    });

    it('never keeps archives for less than 365 days, whatever the setting', function () {
        putenv('LOG_ARCHIVE_RETENTION_DAYS=30');
        try {
            $config = require config_path('logging.php');
        } finally {
            putenv('LOG_ARCHIVE_RETENTION_DAYS');
        }

        expect($config['archive']['retention_days'])->toBe(365);
    });
});

describe('platform-admin access to logs', function () {
    beforeEach(function () {
        $this->withoutMiddleware([EnforceHttps::class, Authenticate::class, RoleMiddleware::class]);
        $this->day = CarbonImmutable::now('Asia/Kolkata')->subDay();
        writeArchiveLine($this->archivePath, 't-123', 'audit.login_failed', ['event' => 'audit.login_failed', 'email' => 'x@acme.test'], $this->day->setTime(9, 0)->utc()->toDateTimeImmutable());
        writeArchiveLine($this->archivePath, 't-123', 'audit.login', ['event' => 'audit.login'], $this->day->setTime(9, 5)->utc()->toDateTimeImmutable());
        app(LogArchiver::class)->shipLocalFiles();
    });

    it('searches archived entries and records the search', function () {
        $d = $this->day->toDateString();

        $response = $this->getJson("/api/admin/log-archives/search?from={$d}&to={$d}&tenant_id=t-123&event=audit.login_failed");

        $response->assertOk()->assertJsonPath('data.files_scanned', 1);
        expect($response->json('data.matches'))->toHaveCount(1)
            ->and($response->json('data.matches.0.entry.event'))->toBe('audit.login_failed')
            ->and(auditEvents())->toContain('logs.searched');
    });

    it('exports a zip with a verified checksum manifest and records the export', function () {
        $d = $this->day->toDateString();

        $response = $this->get("/api/admin/log-archives/export?from={$d}&to={$d}&tenant_id=t-123");

        $response->assertOk()->assertDownload();
        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        expect($manifest['files'][0]['sha256_verified'])->toBeTrue()
            ->and(auditEvents())->toContain('logs.exported');
    });

    it('rejects a search over more than 31 days with 422', function () {
        $this->getJson('/api/admin/log-archives/search?from=2026-01-01&to=2026-03-01')
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['to']]]]);
    });

    it('limits every log route to Platform_Admin and gives tenants none', function () {
        $router = app(Router::class);
        $routes = collect($router->getRoutes());

        foreach ($routes->filter(fn ($r) => str_contains($r->uri(), 'log-archives') || $r->uri() === 'api/admin/telescope/session') as $route) {
            expect(array_values($router->gatherRouteMiddleware($route)))->toContain('Spatie\Permission\Middleware\RoleMiddleware:Platform_Admin');
        }
        expect($routes->filter(fn ($r) => str_starts_with($r->uri(), 'api/tenant') && preg_match('/log|telescope/', $r->uri())))->toBeEmpty();
    });
});

describe('telescope access', function () {
    it('lets only a Platform_Admin view Telescope', function () {
        $admin = Mockery::mock(User::class)->makePartial();
        $admin->shouldReceive('hasRole')->with('Platform_Admin')->andReturn(true);
        $tenantAdmin = Mockery::mock(User::class)->makePartial();
        $tenantAdmin->shouldReceive('hasRole')->andReturn(false);

        // The ability itself (Spatie's permission hook on Gate is not under test here).
        $viewTelescope = Gate::abilities()['viewTelescope'];

        expect($viewTelescope($admin))->toBeTrue()
            ->and($viewTelescope($tenantAdmin))->toBeFalse()
            ->and($viewTelescope(null))->toBeFalse();
    });

    it('serves platform tools on the platform domain only', function () {
        $middleware = new OnlyCentralDomains;

        expect($middleware->handle(Request::create('https://platform.test/api/telescope'), fn () => response('ok'))->getContent())->toBe('ok');
        expect(fn () => $middleware->handle(Request::create('https://acme.platform.test/api/telescope'), fn () => response('ok')))
            ->toThrow(HttpException::class);
    });

    it('refuses a one-time link that is unknown or already used with 403', function () {
        Cache::put('telescope-link:used-nonce', 99, 60);
        Cache::forget('telescope-link:used-nonce');

        try {
            app(TelescopeAccessController::class)->enter(Request::create('/api/telescope-enter?nonce=used-nonce'));
            $this->fail('Expected a 403.');
        } catch (HttpException $e) {
            expect($e->getStatusCode())->toBe(403);
        }
    });
});
