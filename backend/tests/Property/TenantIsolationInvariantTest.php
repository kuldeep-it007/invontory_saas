<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Property 1: Tenant isolation invariant (physical database separation)
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 1: Tenant isolation
| invariant (physical database separation)
|
| Validates: Requirements 1.2, 1.3, 1.6
|
| Design reference: "Multi-Tenant Isolation Strategy".
|   - R1.2  Initializing tenancy for a tenant switches the DEFAULT connection
|           to that tenant's own database; all tenant-owned data access routes
|           there.
|   - R1.3  A request initialized for tenant A physically cannot read or write
|           ANY record in tenant B's database (there is no cross-tenant row on
|           the active connection; no tenant_id discriminator).
|   - R1.6  Cross-tenant access is available ONLY on central / provisioning
|           routes that run WITHOUT tenancy initialization (default connection
|           = central). On tenant routes, even a Platform_Admin is confined to
|           the active tenant database; cross-tenant reach is otherwise only via
|           audited impersonation (which itself initializes the target tenant DB
|           — i.e. still a single active tenant connection at a time).
|
| Strategy. We provision TWO real tenant databases once per proof scenario
| group, then let black-box generate 100+ randomized record payloads. For each
| generated payload we exercise the invariant end to end:
|
|   1. Initialized-for-A write lands ONLY in A's database (R1.2).
|   2. From A's context, B's seeded record is invisible and A cannot address
|      B's data (R1.3).
|   3. Switching to B's context sees ONLY B's data, never A's writes (R1.3).
|   4. The central (no-tenancy) context does NOT expose tenant-owned tables and
|      CAN see the cross-tenant registry (tenants/domains) that tenant contexts
|      cannot — the provisioning/central-route capability (R1.6).
|
| DB-GATED. Provisioning real per-tenant MySQL databases needs a reachable
| MySQL server with CREATE DATABASE privileges and the stancl/tenancy MySQL
| manager. When MySQL is unreachable (local WAMP down, CI without a MySQL
| service) the proof skips gracefully via skip_without_mysql('central'); the
| assertions are written to be correct against a real MySQL instance.
*/

/**
 * Provision an isolated tenant and migrate its tenant database. Registered for
 * teardown through the passed-by-reference registry.
 */
function provisionIsolationTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(5)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'iso'.$suffix,
        'subdomain' => 'iso-'.$suffix,
    ]);

    $tenant->domains()->create(['domain' => 'iso-'.$suffix.'.example.test']);

    $tenant->run(function () {
        Illuminate\Support\Facades\Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
            '--force' => true,
        ]);
    });

    $registry[] = $tenant;

    return $tenant;
}

/**
 * Best-effort teardown: drop each provisioned tenant database and its central
 * registry rows so repeated proof runs stay clean.
 *
 * @param array<int, Tenant> $tenants
 */
function teardownIsolationTenants(array $tenants): void
{
    tenancy()->end();

    foreach ($tenants as $t) {
        try {
            $t->database()->getTenantDatabaseManager()->deleteDatabase($t);
        } catch (\Throwable $e) {
            // best-effort cleanup
        }

        try {
            $t->domains()->delete();
            $t->delete();
        } catch (\Throwable $e) {
            // best-effort cleanup
        }
    }
}

it('confines a tenant-initialized request to its own database and reserves cross-tenant reach to central routes (R1.2, R1.3, R1.6)', function () {
    skip_without_mysql('central');

    $tenants = [];

    try {
        $tenantA = provisionIsolationTenant($tenants);
        $tenantB = provisionIsolationTenant($tenants);

        $centralDefault = config('database.default');

        // Seed each tenant database with a single, uniquely identifiable
        // Company row (R1.5: one company per tenant). These act as the
        // cross-tenant "canary" records for the isolation assertions.
        $tenantA->run(function () use ($tenantA) {
            DB::connection('tenant')->table('companies')->insert([
                'name' => 'CompanyA-'.$tenantA->getTenantKey(),
                'gstin' => 'GSTA-'.$tenantA->getTenantKey(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $tenantB->run(function () use ($tenantB) {
            DB::connection('tenant')->table('companies')->insert([
                'name' => 'CompanyB-'.$tenantB->getTenantKey(),
                'gstin' => 'GSTB-'.$tenantB->getTenantKey(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $tenantAKey = $tenantA->getTenantKey();
        $tenantBKey = $tenantB->getTenantKey();
        $dbNameA = config('tenancy.database.prefix').$tenantAKey.config('tenancy.database.suffix');
        $dbNameB = config('tenancy.database.prefix').$tenantBKey.config('tenancy.database.suffix');

        // ------------------------------------------------------------------
        // Generated invariant: for any white-label setting payload, writing it
        // while initialized for A must land ONLY in A's database and must never
        // become visible from B's context or leak A's canary into B.
        // 100+ randomized iterations (BlackBox default scenarii-per-proof).
        // ------------------------------------------------------------------
        static::forAll(
            // A settings key made of safe identifier characters, length 3..40.
            Set::strings()
                ->madeOf(Set::strings()->chars()->alphanumerical())
                ->between(3, 40),
            // A value: any (possibly empty) string drawn from a broad, multi-
            // script unicode space — BUT restricted to the Basic Multilingual
            // Plane (U+0000..U+FFFF, at most 3 UTF-8 bytes per character).
            //
            // `Set::strings()->unicode()` draws from EVERY unicode block,
            // including astral-plane blocks (U+10000+, e.g. emoji / CJK Ext-B)
            // whose characters encode to 4 UTF-8 bytes. The tenant_settings
            // `value` column's charset cannot store 4-byte characters, so an
            // astral codepoint makes the INSERT fail with "Incorrect string
            // value". We keep the value space intentionally wide (Latin,
            // accents, Greek, Cyrillic, Hebrew, Arabic, CJK, kana, punctuation,
            // currency/number symbols) while guaranteeing every generated
            // character is storable, so the isolation invariant — not a charset
            // edge case — is what the proof exercises.
            Set::strings()
                ->madeOf(
                    Set::strings()->unicode()->basicLatin(),
                    Set::strings()->unicode()->latin1Supplement(),
                    Set::strings()->unicode()->latinExtendedA(),
                    Set::strings()->unicode()->greekAndCoptic(),
                    Set::strings()->unicode()->cyrillic(),
                    Set::strings()->unicode()->hebrew(),
                    Set::strings()->unicode()->arabic(),
                    Set::strings()->unicode()->hiragana(),
                    Set::strings()->unicode()->katakana(),
                    Set::strings()->unicode()->cjkUnifiedIdeographs(),
                    Set::strings()->unicode()->generalPunctuation(),
                    Set::strings()->unicode()->currencySymbols(),
                )
                ->atMost(120),
        )->then(function (string $key, string $value) use (
            $tenantA,
            $tenantB,
            $tenantAKey,
            $tenantBKey,
            $dbNameA,
            $dbNameB,
            $centralDefault
        ) {
            // Namespace the key per-iteration so repeated scenarii don't collide
            // on the UNIQUE(key) constraint in tenant_settings.
            $iterKey = $key.'-'.bin2hex(random_bytes(4));

            // --- (R1.2) A request initialized for A writes to A's DB ---------
            tenancy()->initialize($tenantA);

            // The default connection now physically points at A's database.
            expect(DB::connection()->getDatabaseName())->toBe($dbNameA);

            DB::connection('tenant')->table('tenant_settings')->insert([
                'key' => $iterKey,
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // The write is visible within A...
            $inA = DB::connection('tenant')->table('tenant_settings')
                ->where('key', $iterKey)->value('value');
            expect($inA)->toBe($value);

            // ...and from A's context only A's canary company is visible; B's is
            // physically unreachable (no cross-tenant row on this connection).
            $companyNamesInA = DB::connection('tenant')->table('companies')
                ->pluck('name')->all();
            expect($companyNamesInA)->toContain('CompanyA-'.$tenantAKey)
                ->and($companyNamesInA)->not->toContain('CompanyB-'.$tenantBKey);
            expect(DB::connection('tenant')->table('companies')->count())->toBe(1);

            // --- (R1.3) B's context cannot see A's write; isolation holds ----
            tenancy()->initialize($tenantB);
            expect(DB::connection()->getDatabaseName())->toBe($dbNameB);

            // A's just-written setting does NOT exist in B's database.
            $leakedToB = DB::connection('tenant')->table('tenant_settings')
                ->where('key', $iterKey)->exists();
            expect($leakedToB)->toBeFalse();

            // From B's context only B's canary company is visible.
            $companyNamesInB = DB::connection('tenant')->table('companies')
                ->pluck('name')->all();
            expect($companyNamesInB)->toContain('CompanyB-'.$tenantBKey)
                ->and($companyNamesInB)->not->toContain('CompanyA-'.$tenantAKey);
            expect(DB::connection('tenant')->table('companies')->count())->toBe(1);

            // --- (R1.6) Central (no tenancy) = cross-tenant/provisioning ------
            tenancy()->end();

            // Default connection reverts to central after tenancy ends.
            expect(config('database.default'))->toBe($centralDefault);

            // The central database does NOT carry tenant-owned tables — a tenant
            // route's data is physically absent from the central connection.
            expect(Schema::connection('central')->hasTable('companies'))->toBeFalse()
                ->and(Schema::connection('central')->hasTable('tenant_settings'))->toBeFalse();

            // Central/provisioning routes CAN see cross-tenant registry data for
            // BOTH tenants simultaneously (the capability tenant routes lack).
            $registrySubdomains = DB::connection('central')->table('tenants')
                ->pluck('subdomain')->all();
            expect($registrySubdomains)->toContain($tenantA->subdomain)
                ->and($registrySubdomains)->toContain($tenantB->subdomain);
        });
    } finally {
        teardownIsolationTenants($tenants);
    }
});
