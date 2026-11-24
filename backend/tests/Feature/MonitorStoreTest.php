<?php

declare(strict_types=1);

use App\Auth\Roles;
use App\Models\User;
use App\Services\Monitoring\MonitorStore;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| Monitoring dashboard: the store that reads the host agent's files, and the
| Platform_Admin endpoints on top of it. File-based, so no database is needed
| except for the authenticated endpoint tests (DB-gated).
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/monitor-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($this->root.'/data/deployments');
    File::ensureDirectoryExists($this->root.'/config');
    config(['monitoring.path' => $this->root]);
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

function writeSeries(string $root, string $file, array $rows): void
{
    file_put_contents("{$root}/data/{$file}", implode("\n", array_map('json_encode', $rows))."\n");
}

function writeDeployment(string $root, string $id, array $record, array $extra = []): void
{
    File::ensureDirectoryExists("{$root}/data/deployments/{$id}");
    file_put_contents("{$root}/data/deployments/{$id}/record.json", json_encode($record + ['id' => $id]));
    foreach ($extra as $name => $content) {
        file_put_contents("{$root}/data/deployments/{$id}/{$name}", $content);
    }
}

it('reports no data when the agent has not written anything', function () {
    $current = (new MonitorStore)->current();

    expect($current['available'])->toBeFalse()
        ->and($current['data'])->toBeNull();
});

it('flags the agent snapshot as stale when it stops updating', function () {
    file_put_contents($this->root.'/data/current.json', json_encode(['sample' => ['cpu_pct' => 5]]));

    expect((new MonitorStore)->current()['stale'])->toBeFalse();

    touch($this->root.'/data/current.json', time() - 600);
    clearstatcache();

    expect((new MonitorStore)->current())->toMatchArray(['available' => true, 'stale' => true]);
});

it('buckets a range into averaged points, sums counters and ignores samples outside the range', function () {
    $now = 1_800_000_000;
    writeSeries($this->root, 'series-1m.jsonl', [
        ['ts' => $now - 7200, 'cpu_pct' => 99, 'req' => 1000],
        ['ts' => $now - 120, 'cpu_pct' => 10, 'req' => 5, 'api_up' => true],
        ['ts' => $now - 118, 'cpu_pct' => 30, 'req' => 7, 'api_up' => false],
    ]);

    $series = (new MonitorStore)->series('1h', $now);

    expect($series['resolution'])->toBe('1m')
        ->and($series['points'])->toHaveCount(1)
        ->and($series['points'][0]['cpu_pct'])->toBe(20.0)
        ->and($series['points'][0]['req'])->toBe(12)
        ->and($series['points'][0]['api_up'])->toBeFalse();
});

it('uses the five minute rollup for week and month ranges', function () {
    expect((new MonitorStore)->series('7d')['resolution'])->toBe('5m')
        ->and((new MonitorStore)->series('30d')['resolution'])->toBe('5m')
        ->and((new MonitorStore)->series('24h')['resolution'])->toBe('1m');
});

it('rejects an unknown range', function () {
    (new MonitorStore)->series('90d');
})->throws(InvalidArgumentException::class);

it('returns default thresholds and applies saved ones', function () {
    $store = new MonitorStore;

    expect($store->thresholds()['cpu_pct'])->toMatchArray(['warning' => 80.0, 'critical' => 95.0]);

    $saved = $store->saveThresholds(['cpu_pct' => ['warning' => 60, 'critical' => 70]]);

    expect($saved['cpu_pct'])->toMatchArray(['warning' => 60.0, 'critical' => 70.0])
        ->and(json_decode((string) file_get_contents($this->root.'/config/thresholds.json'), true)['cpu_pct'])->toEqual([60, 70]);
});

it('rejects thresholds that are inverted, out of range or for an unknown metric', function (string $metric, array $pair) {
    (new MonitorStore)->saveThresholds([$metric => $pair]);
})->with([
    'critical below warning' => ['cpu_pct', ['warning' => 90, 'critical' => 80]],
    'above the maximum' => ['mem_pct', ['warning' => 90, 'critical' => 120]],
    'ssl must count down' => ['ssl_days_left', ['warning' => 7, 'critical' => 21]],
    'unknown metric' => ['made_up', ['warning' => 1, 'critical' => 2]],
])->throws(InvalidArgumentException::class);

it('lists deployments newest first and ignores folders that are not deployment ids', function () {
    writeDeployment($this->root, '20260101T100000Z-aaaaaa', ['status' => 'success']);
    writeDeployment($this->root, '20260102T100000Z-bbbbbb', ['status' => 'blocked']);
    File::ensureDirectoryExists($this->root.'/data/deployments/not-an-id');

    $ids = array_column((new MonitorStore)->deployments(), 'id');

    expect($ids)->toBe(['20260102T100000Z-bbbbbb', '20260101T100000Z-aaaaaa']);
});

it('redacts credentials in deployment records and logs', function () {
    $id = '20260103T100000Z-cccccc';
    writeDeployment($this->root, $id, ['status' => 'failed', 'reason' => 'clone https://x-access-token:ghp_SECRET123@github.com failed'], [
        'deploy.log' => "DB_PASSWORD=hunter2\nAPP_KEY=base64:".str_repeat('A', 44)."\nok line\n",
        'gate.log' => 'Authorization: Bearer abcdefghijklmnop1234',
    ]);

    $json = json_encode((new MonitorStore)->deployment($id));

    expect($json)->not->toContain('ghp_SECRET123')
        ->and($json)->not->toContain('hunter2')
        ->and($json)->not->toContain(str_repeat('A', 44))
        ->and($json)->not->toContain('abcdefghijklmnop1234')
        ->and($json)->toContain('ok line');
});

it('refuses deployment ids that could escape the history folder', function () {
    expect((new MonitorStore)->deployment('../../etc/passwd'))->toBeNull()
        ->and((new MonitorStore)->deployment('20260101T100000Z-zzzzzz'))->toBeNull();
});

it('extracts recent application errors without secrets', function () {
    $log = $this->root.'/laravel.log';
    file_put_contents($log, implode("\n", [
        '[2026-01-01 10:00:00] production.INFO: all good',
        '[2026-01-01 10:01:00] production.ERROR: Payment failed token=abc123secret for order 5',
        '[2026-01-01 10:02:00] production.CRITICAL: Database down',
    ])."\n");

    $errors = (new MonitorStore)->recentErrors(10, $log);

    expect($errors)->toHaveCount(2)
        ->and($errors[0]['level'])->toBe('CRITICAL')
        ->and(json_encode($errors))->not->toContain('abc123secret');
});

it('lets only an authenticated Platform_Admin read monitoring data', function () {
    $this->getJson('/api/admin/monitoring/overview')->assertUnauthorized();
    $this->putJson('/api/admin/monitoring/thresholds', [])->assertUnauthorized();
});

it('serves the dashboard endpoints to a Platform_Admin and rejects invalid input', function () {
    skip_without_mysql('central');
    User::disableAuditing();

    $guard = config('auth.defaults.guard', 'web');
    Role::findOrCreate(Roles::PLATFORM_ADMIN, $guard);
    $admin = new User(['name' => 'Mon Admin', 'email' => 'mon-'.bin2hex(random_bytes(3)).'@example.test', 'password' => 'password123']);
    $admin->setConnection('central');
    $admin->save();
    $admin->assignRole(Roles::PLATFORM_ADMIN);
    Sanctum::actingAs($admin, ['*']);

    try {
        $this->getJson('/api/admin/monitoring/overview')->assertOk()->assertJsonPath('data.current.available', false);
        $this->getJson('/api/admin/monitoring/metrics?range=24h')->assertOk()->assertJsonPath('data.range', '24h');
        $this->getJson('/api/admin/monitoring/metrics?range=1y')->assertStatus(422);
        $this->getJson('/api/admin/monitoring/deployments/20260101T100000Z-aaaaaa')->assertNotFound();
        $this->putJson('/api/admin/monitoring/thresholds', ['thresholds' => ['cpu_pct' => ['warning' => 90, 'critical' => 50]]])->assertStatus(422);
        $this->putJson('/api/admin/monitoring/thresholds', ['thresholds' => ['cpu_pct' => ['warning' => 70, 'critical' => 90]]])
            ->assertOk()->assertJsonPath('data.cpu_pct.warning', 70);
    } finally {
        $admin->delete();
        User::enableAuditing();
    }
});
