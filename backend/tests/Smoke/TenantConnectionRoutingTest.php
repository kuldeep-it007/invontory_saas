<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Smoke (DB-gated): connection routing, provisioning, live index existence
|--------------------------------------------------------------------------
|
| Task 1.3 bullets:
|  - Central migrations run on the central connection; tenant migrations run
|    on the tenant connection; provisioning a tenant creates the tenant schema
|    in a SEPARATE database (R1.1).
|  - Tenant identification/initialization switches the default connection to
|    the resolved tenant's database (R1.2); central routes run without it.
|  - Required indexes exist in both the central and tenant live schemas
|    (R38.2–R38.4).
|
| EVERY test in this file is DB-GATED: it requires a reachable MySQL server
| (central connection) and the stancl/tenancy MySQL database manager, which
| needs privileges to CREATE DATABASE. When MySQL is NOT reachable (e.g. the
| local WAMP server is down, or CI without a MySQL service), each test calls
| skip_without_mysql() and is reported as SKIPPED, never failed. The
| assertions are written to be correct against a real MySQL instance.
|
| Requirements: 1.1, 1.2, 38.2, 38.3, 38.4.
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Provision an isolated tenant for a test and migrate its database, returning
 * the tenant. Cleans itself up via the afterEach teardown list.
 */
function provisionSmokeTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'smoke'.$suffix,
        'subdomain' => 'smoke-'.$suffix,
    ]);

    $tenant->domains()->create(['domain' => 'smoke-'.$suffix.'.example.test']);

    $registry[] = $tenant;

    return $tenant;
}

it('runs central migrations on the central connection (tenants/domains exist centrally) (R1.1)', function () {
    // The central schema must carry the central registry tables.
    expect(Schema::connection('central')->hasTable('tenants'))->toBeTrue();
    expect(Schema::connection('central')->hasTable('domains'))->toBeTrue();

    // Tenant-owned tables must NOT exist in the central database (physical
    // separation): companies/tenant_settings live only in tenant databases.
    expect(Schema::connection('central')->hasTable('companies'))->toBeFalse();
    expect(Schema::connection('central')->hasTable('tenant_settings'))->toBeFalse();
});

it('provisions a tenant into a SEPARATE database and runs tenant migrations there (R1.1)', function () {
    $tenants = [];

    try {
        $tenant = provisionSmokeTenant($tenants);

        // stancl/tenancy creates the tenant DB named prefix + id (+ suffix).
        $expectedDbName = config('tenancy.database.prefix').$tenant->getTenantKey().config('tenancy.database.suffix');
        expect($tenant->database()->getName())->toBe($expectedDbName);

        // Run the tenant migration set against the new tenant database.
        $tenant->run(function () {
            Illuminate\Support\Facades\Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--realpath' => false,
                '--force' => true,
            ]);
        });

        // The tenant schema now contains the tenant-owned tables...
        $tenant->run(function () {
            expect(Schema::connection('tenant')->hasTable('companies'))->toBeTrue();
            expect(Schema::connection('tenant')->hasTable('tenant_settings'))->toBeTrue();
            expect(Schema::connection('tenant')->hasTable('audit_logs'))->toBeTrue();
        });

        // ...and those tables remain absent from the central database.
        expect(Schema::connection('central')->hasTable('companies'))->toBeFalse();
    } finally {
        foreach ($tenants as $t) {
            try {
                $t->database()->getTenantDatabaseManager()->deleteDatabase($t);
            } catch (\Throwable $e) {
                // best-effort cleanup
            }
            $t->domains()->delete();
            $t->delete();
        }
    }
});

it('switches the default connection to the tenant database on initialization and restores it on end (R1.2)', function () {
    $tenants = [];

    try {
        $tenant = provisionSmokeTenant($tenants);

        $centralDefault = config('database.default');

        tenancy()->initialize($tenant);

        // While initialized, the DEFAULT connection points at the tenant DB.
        $activeDbName = DB::connection()->getDatabaseName();
        $expectedDbName = config('tenancy.database.prefix').$tenant->getTenantKey().config('tenancy.database.suffix');
        expect($activeDbName)->toBe($expectedDbName);

        tenancy()->end();

        // After ending tenancy the default connection reverts to central.
        expect(config('database.default'))->toBe($centralDefault);
    } finally {
        tenancy()->end();
        foreach ($tenants as $t) {
            try {
                $t->database()->getTenantDatabaseManager()->deleteDatabase($t);
            } catch (\Throwable $e) {
            }
            $t->domains()->delete();
            $t->delete();
        }
    }
});

it('has required indexes in the central schema: tenants.subdomain + domains.domain (R38.4)', function () {
    $sm = Schema::connection('central');

    // Column-level presence is a prerequisite for the indexes.
    expect($sm->hasColumn('tenants', 'subdomain'))->toBeTrue();
    expect($sm->hasColumn('domains', 'domain'))->toBeTrue();

    // Assert the live indexes exist. stancl/tenancy's doctrine schema listing
    // may not be available, so we query information_schema directly.
    expect(mysqlIndexExistsOnColumn('central', 'tenants', 'subdomain'))->toBeTrue();
    expect(mysqlIndexExistsOnColumn('central', 'domains', 'domain'))->toBeTrue();
});

it('has required indexes in the tenant schema: audit_logs + tenant_settings (R38.2, R38.3)', function () {
    $tenants = [];

    try {
        $tenant = provisionSmokeTenant($tenants);

        $tenant->run(function () {
            Illuminate\Support\Facades\Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--realpath' => false,
                '--force' => true,
            ]);
        });

        $dbName = config('tenancy.database.prefix').$tenant->getTenantKey().config('tenancy.database.suffix');

        // Named indexes created by the tenant migrations must be present.
        expect(mysqlNamedIndexExists('central', $dbName, 'audit_logs', 'audit_logs_auditable_created_index'))->toBeTrue();
        expect(mysqlNamedIndexExists('central', $dbName, 'tenant_settings', 'tenant_settings_key_unique'))->toBeTrue();
    } finally {
        foreach ($tenants as $t) {
            try {
                $t->database()->getTenantDatabaseManager()->deleteDatabase($t);
            } catch (\Throwable $e) {
            }
            $t->domains()->delete();
            $t->delete();
        }
    }
});

/**
 * True if any index on the given table covers the given column.
 */
function mysqlIndexExistsOnColumn(string $connection, string $table, string $column): bool
{
    $db = DB::connection($connection)->getDatabaseName();

    $rows = DB::connection($connection)->select(
        'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
        [$db, $table, $column]
    );

    return count($rows) > 0;
}

/**
 * True if a named index exists on the given schema/table.
 */
function mysqlNamedIndexExists(string $connection, string $schema, string $table, string $indexName): bool
{
    $rows = DB::connection($connection)->select(
        'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
        [$schema, $table, $indexName]
    );

    return count($rows) > 0;
}
