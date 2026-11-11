<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Daily per-database backup command (R36.4).
 *
 * The platform takes daily backups of the database. Because isolation is
 * physical — one database per tenant plus a central registry database — a
 * single backup run dumps the CENTRAL database first (so a restore has the
 * authoritative tenant list) and then every TENANT database in turn
 * (prefix + tenant_id + suffix, matching stancl/tenancy's naming).
 *
 * Each run writes plain `.sql` dumps produced by the native `mysqldump` client
 * into a timestamped subdirectory beneath the configured destination, then
 * prunes dated directories older than the configured retention window. The
 * command is self-contained (no heavy backup package) and is wired into the
 * scheduler to run daily in routes/console.php.
 *
 * All connection values are passed to `mysqldump` as discrete process
 * arguments (never interpolated into a shell string), so database names,
 * usernames, and hosts cannot break out into shell injection.
 */
class BackupDatabases extends Command
{
    protected $signature = 'db:backup
        {--tenant= : Back up only the tenant with this id (central is always included unless --skip-central)}
        {--skip-central : Skip the central database and back up tenant databases only}';

    protected $description = 'Take a backup of the central database and every tenant database (R36.4).';

    public function handle(): int
    {
        if (! (bool) Config::get('backup.enabled', false)) {
            $this->warn('Backups are disabled (set BACKUP_ENABLED=true to enable). Nothing to do.');

            return self::SUCCESS;
        }

        $root = (string) Config::get('backup.destination', storage_path('app/backups'));
        $runDir = rtrim($root, '/\\').DIRECTORY_SEPARATOR.Carbon::now()->format('Y-m-d_His');
        File::ensureDirectoryExists($runDir);

        $failures = 0;

        // 1) Central database first — the authoritative cross-tenant registry.
        if (! $this->option('skip-central')) {
            $centralConnection = (string) Config::get('backup.central_connection', 'central');
            $centralDb = (string) Config::get("database.connections.{$centralConnection}.database");

            if ($centralDb === '') {
                $this->error("Central connection [{$centralConnection}] has no database configured.");
                $failures++;
            } elseif (! $this->dump($centralConnection, $centralDb, $runDir.DIRECTORY_SEPARATOR."central-{$centralDb}.sql")) {
                $failures++;
            }
        }

        // 2) Every tenant database (or a single one when --tenant is given).
        $template = (string) Config::get('tenancy.database.template_tenant_connection', 'tenant_template');
        $prefix = (string) Config::get('backup.tenant_database_prefix', 'tenant');
        $suffix = (string) Config::get('backup.tenant_database_suffix', '');

        $tenants = $this->tenants();

        foreach ($tenants as $tenant) {
            $tenantId = (string) $tenant->getTenantKey();
            $database = $prefix.$tenantId.$suffix;

            if (! $this->dump($template, $database, $runDir.DIRECTORY_SEPARATOR."tenant-{$tenantId}.sql")) {
                $failures++;
            }
        }

        $this->pruneOldBackups($root);

        if ($failures > 0) {
            $this->error("Backup completed with {$failures} failure(s). See output above.");

            return self::FAILURE;
        }

        $this->info("Backup completed successfully → {$runDir}");

        return self::SUCCESS;
    }

    /**
     * The tenants to back up: a single one when --tenant is given, else all.
     *
     * @return iterable<int, Tenant>
     */
    private function tenants(): iterable
    {
        if ($id = $this->option('tenant')) {
            $tenant = Tenant::find($id);

            if ($tenant === null) {
                $this->error("Tenant [{$id}] not found; skipping tenant backups.");

                return [];
            }

            return [$tenant];
        }

        return Tenant::cursor();
    }

    /**
     * Dump a single database using the native `mysqldump` client. Connection
     * credentials come from the given connection's config and are passed as
     * discrete process arguments (no shell interpolation), so values cannot
     * inject shell commands. The password is supplied via the environment
     * (MYSQL_PWD) so it never appears in the process argument list / ps output.
     */
    private function dump(string $connection, string $database, string $outputFile): bool
    {
        $config = (array) Config::get("database.connections.{$connection}", []);

        $binary = (string) Config::get('backup.mysqldump_binary', 'mysqldump');
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (string) ($config['port'] ?? '3306');
        $username = (string) ($config['username'] ?? 'root');
        $password = (string) ($config['password'] ?? '');

        $arguments = [
            $binary,
            '--host='.$host,
            '--port='.$port,
            '--user='.$username,
            '--single-transaction',
            '--quick',
            '--no-tablespaces',
            '--default-character-set='.(string) ($config['charset'] ?? 'utf8mb4'),
            '--result-file='.$outputFile,
            $database,
        ];

        $process = new Process($arguments, null, ['MYSQL_PWD' => $password]);
        $process->setTimeout(null);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error("Failed to back up [{$database}]: ".trim($process->getErrorOutput()));

            return false;
        }

        $this->line("  ✓ {$database} → ".basename($outputFile));

        return true;
    }

    /**
     * Delete dated backup directories older than the configured retention
     * window. A retention of 0 disables pruning.
     */
    private function pruneOldBackups(string $root): void
    {
        $retentionDays = (int) Config::get('backup.retention_days', 7);

        if ($retentionDays <= 0 || ! File::isDirectory($root)) {
            return;
        }

        $cutoff = Carbon::now()->subDays($retentionDays)->getTimestamp();

        foreach (File::directories($root) as $directory) {
            if (File::lastModified($directory) < $cutoff) {
                File::deleteDirectory($directory);
                $this->line('  ⌫ pruned old backup '.basename($directory));
            }
        }
    }
}
