<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled daily database backups (R36.4)
|--------------------------------------------------------------------------
|
| The platform takes DAILY backups of the database. Because isolation is
| physical (one database per tenant plus the central registry database), the
| `db:backup` command dumps the central database plus every tenant database in
| a single daily run — see config/backup.php and
| App\Console\Commands\BackupDatabases.
|
| The schedule is always registered (it is cheap and declarative); the command
| itself no-ops unless BACKUP_ENABLED=true, so local/CI never attempt a real
| dump. withoutOverlapping() prevents a long-running backup from stacking on
| the next day's tick; onOneServer() ensures only one app node runs it when the
| application tier is scaled horizontally (R38.6).
*/
Schedule::command('db:backup')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

/*
|--------------------------------------------------------------------------
| Custom domain DNS verification
|--------------------------------------------------------------------------
| Queues DNS checks for pending custom domains (5-minute cadence for the first
| hour, then hourly) and the daily ownership/routing re-check of active ones.
*/
Schedule::command('domains:verify-pending')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();
