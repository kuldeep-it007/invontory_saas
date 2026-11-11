<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;

/*
|--------------------------------------------------------------------------
| Smoke: daily per-database backup configuration (R36.4)
|--------------------------------------------------------------------------
|
| Task 15.8 — "confirm ... daily backup schedule configuration".
|
| The platform takes DAILY backups of the database (R36.4). Because isolation
| is physical (one database per tenant plus the central registry database), a
| backup run dumps the central database plus every tenant database. These
| checks are UNCONDITIONAL (no MySQL required): they assert the backup config
| defaults, the `db:backup` command registration, its disabled-state no-op, and
| that the daily schedule entry exists. The actual mysqldump execution is a
| production-only, env-gated side effect and is intentionally not exercised
| here.
|
| Requirements: 36.4 (daily database backups).
*/

it('defaults backups to OFF so local/CI never attempt a real dump (R36.4)', function () {
    // The command no-ops unless BACKUP_ENABLED=true; the default must be false
    // so booting with zero optional configuration never shells out to mysqldump.
    expect((bool) config('backup.enabled'))->toBeFalse();
});

it('derives tenant backup database names from the tenancy naming scheme (R36.4)', function () {
    // Per-tenant DB name = prefix + tenant id + suffix — the backup target must
    // match exactly what stancl/tenancy created.
    expect(config('backup.tenant_database_prefix'))->toBe(config('tenancy.database.prefix'));
    expect(config('backup.tenant_database_suffix'))->toBe(config('tenancy.database.suffix'));

    // The central database is backed up first; its connection must agree with
    // the tenancy central connection.
    expect(config('backup.central_connection'))->toBe(config('tenancy.database.central_connection'));
});

it('exposes a destination and a retention window for pruning old backups (R36.4)', function () {
    expect(config('backup.destination'))->not->toBeEmpty();
    expect(config('backup.retention_days'))->toBeInt();
});

it('registers the db:backup artisan command (R36.4)', function () {
    $commands = array_keys(app(\Illuminate\Contracts\Console\Kernel::class)->all());

    expect($commands)->toContain('db:backup');
});

it('no-ops cleanly when backups are disabled (R36.4)', function () {
    config()->set('backup.enabled', false);

    $this->artisan('db:backup')
        ->expectsOutputToContain('Backups are disabled')
        ->assertSuccessful();
});

it('schedules the db:backup command to run daily (R36.4)', function () {
    $schedule = app(Schedule::class);

    $events = collect($schedule->events())
        ->filter(fn ($event) => str_contains((string) $event->command, 'db:backup'));

    expect($events)->not->toBeEmpty();

    // The single daily backup event runs at a fixed daily time (02:00) with
    // overlap protection — assert the cron expression is a daily one.
    $event = $events->first();
    expect($event->expression)->toBe('0 2 * * *');
});
