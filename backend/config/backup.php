<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Database backup configuration (R36.4)
|--------------------------------------------------------------------------
|
| The platform takes DAILY backups of the database (R36.4). Because isolation
| is physical — one database per tenant plus a central registry database
| (see config/tenancy.php and config/database.php) — a single logical "backup"
| is actually a backup of the central database PLUS every tenant database.
|
| This configuration drives the self-contained `db:backup` artisan command
| ({@see \App\Console\Commands\BackupDatabases}) and the daily schedule
| registered in routes/console.php. The approach deliberately uses the native
| `mysqldump` client rather than pulling in a heavy backup package: it keeps
| the dependency surface small, works against the exact per-tenant database
| naming scheme stancl/tenancy already uses, and produces plain `.sql` dumps
| that any MySQL restore path can consume.
|
| NOTE ON DEPENDENCIES: a managed backup package (e.g. spatie/laravel-backup)
| could replace this if off-site/compressed/retained archives with
| notifications are later required. That is intentionally NOT added here to
| avoid a heavy dependency; this config documents the lightweight approach the
| design calls for ("per-database daily backup schedule configured").
*/

return [

    /*
    | Master switch. Disabled by default so local/dev/CI never attempt a real
    | dump; enable in production via BACKUP_ENABLED=true. When disabled, the
    | `db:backup` command reports that backups are disabled and exits cleanly
    | without touching the filesystem or shelling out.
    */
    'enabled' => (bool) env('BACKUP_ENABLED', false),

    /*
    | Where dump files are written. A per-run, timestamped subdirectory is
    | created beneath this path by the command. Defaults to storage/app/backups
    | so it inherits the application's existing storage conventions; point this
    | at a mounted volume / durable disk in production.
    */
    'destination' => env('BACKUP_DESTINATION', storage_path('app/backups')),

    /*
    | Retention: delete dated backup directories older than this many days on
    | each run so the destination does not grow without bound. A value of 0
    | disables pruning (keep everything).
    */
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 7),

    /*
    | Path to the `mysqldump` binary. Left as the bare command so it resolves
    | from PATH by default; override with an absolute path where the binary is
    | not on PATH (common on Windows / containerized hosts).
    */
    'mysqldump_binary' => env('BACKUP_MYSQLDUMP_BINARY', 'mysqldump'),

    /*
    | The central connection whose database holds the cross-tenant registry
    | (tenants, domains, plans, plan_limits, subscriptions). Backed up first,
    | before any tenant database, so a restore has the authoritative tenant
    | list available. Mirrors tenancy.database.central_connection.
    */
    'central_connection' => env('TENANCY_CENTRAL_CONNECTION', 'central'),

    /*
    | How each tenant's database name is derived. These MUST match
    | config('tenancy.database.prefix' / '.suffix') so the backup targets the
    | exact databases stancl/tenancy created (prefix + tenant_id + suffix).
    */
    'tenant_database_prefix' => config('tenancy.database.prefix', 'tenant'),
    'tenant_database_suffix' => config('tenancy.database.suffix', ''),

];
