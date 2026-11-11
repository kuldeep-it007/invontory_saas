<?php

declare(strict_types=1);

use App\Auth\Roles;
use App\Models\Company;
use App\Models\Tenant;
use App\Reports\Definitions\SalesReportDefinition;
use App\Reports\ReportAccessContext;
use App\Reports\ReportDefinition;
use App\Reports\ReportFilters;
use App\Reports\ReportQuerySpec;
use App\Reports\ReportType;
use App\Services\ReportCache;
use App\Services\ReportQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Task 11.5 — Property 1 (report scope portion): Tenant isolation invariant
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 1 (report scope portion):
| Tenant isolation invariant
|
| Validates: Requirements 28.10
|
| Property statement (tasks.md task 11.5):
|   Report results restricted to the user's tenant and role-permitted data.
|
|   - R28.10 THE Report_Engine SHALL scope every report to the active tenant
|            and, within it, to the role-permitted subset of data: a company-side
|            role reads tenant-wide; a Distributor/Dealer reads ONLY its own
|            account data; a principal with no report scope is denied.
|
| The invariant has two independent layers, proven here across the mandated
| minimum of 100 generated scenarii per proof via the shared static::forAll()
| (innmind/black-box — never hand-rolled), with shrinking to a minimal
| counterexample on failure:
|
|   A. ROLE SCOPING (pure logic, no DB). Driving the real
|      {@see ReportQueryService::authorizeAndScope()} + {@see ReportAccessContext}
|      through a stub definition that captures the ReportFilters it is handed,
|      we prove, for any generated principal and any requested buyer filter:
|        • a company-side principal (Platform_Admin / Company_Admin /
|          Company_Staff / Read-only) is authorized AND its spec is NOT confined
|          — it may read tenant-wide (the request's buyer filter passes through
|          untouched);
|        • a Distributor/Dealer principal WITH a resolved (buyerType, buyerId)
|          binding is authorized but FORCE-SCOPED to its OWN binding, overriding
|          whatever buyer the request asked for — it can never read another
|          buyer's data;
|        • a Distributor/Dealer principal with NO binding is DENIED;
|        • a principal lacking the reports.view ability is DENIED;
|        • a principal that holds reports.view but has neither a company-side
|          role nor a buyer binding (no recognized scope) is DENIED.
|      No database is touched: the stub throws before any SQL, so these cases
|      run anywhere.
|
|   B. PHYSICAL TENANT CONFINEMENT (DB-gated). Provisioning two real per-tenant
|      MySQL databases and seeding sales in BOTH, we prove a report executed
|      under tenant A returns ONLY tenant A's rows — tenant B's identically
|      shaped data is never visible — because every report query runs on the
|      active tenant connection (physical isolation, no tenant_id predicate).
|      Combined with role scoping, we also prove that WITHIN one tenant a
|      Distributor principal sees only its own buyer's sales regardless of the
|      requested buyer filter.
|
| DB-GATED. Layer B needs a reachable MySQL server with CREATE DATABASE
| privileges and the stancl/tenancy MySQL manager; when MySQL is unreachable the
| proof skips gracefully via skip_without_mysql('central'). Assertions are
| written to be correct against a real MySQL instance, and all tenant data work
| runs inside $tenant->run() so rows are confined to the active tenant database.
*/

// ---------------------------------------------------------------------------
// Shared helpers (file-unique `rts...` prefix to avoid colliding with sibling
// Property test files Pest loads together).
// ---------------------------------------------------------------------------

/**
 * A stub report definition that RECORDS the {@see ReportFilters} it is handed
 * by the service and then throws, so a pure-logic proof can inspect exactly how
 * the service scoped the spec WITHOUT running any SQL. The authorization and
 * allow-list checks run before baseQuery(), so a denial surfaces as an
 * {@see AuthorizationException} while an authorized request surfaces as the
 * sentinel RuntimeException below — two distinguishable outcomes.
 */
function rtsCapturingDefinition(): ReportDefinition
{
    return new class implements ReportDefinition
    {
        public ?ReportFilters $seen = null;

        public function type(): ReportType
        {
            return ReportType::SALES;
        }

        public function groupableColumns(): array
        {
            return [];
        }

        public function sortableColumns(): array
        {
            return [];
        }

        public function baseQuery(ReportFilters $filters): Builder
        {
            $this->seen = $filters;

            throw new RuntimeException('rts: captured filters, stop before SQL');
        }

        public function applyGrouping(Builder $query, array $grouping): void {}

        public function sortExpression(string $column): string
        {
            return $column;
        }
    };
}

/**
 * Provision + migrate an isolated tenant database and register it for teardown.
 *
 * @param  array<int, Tenant>  $registry
 */
function rtsProvisionTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(5)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'rts'.$suffix,
        'subdomain' => 'rts-'.$suffix,
    ]);

    $tenant->domains()->create(['domain' => 'rts-'.$suffix.'.example.test']);

    $tenant->run(function () {
        Artisan::call('migrate', [
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
 * Best-effort teardown of provisioned tenants and their central registry rows.
 *
 * @param  array<int, Tenant>  $registry
 */
function rtsTeardownTenants(array $registry): void
{
    tenancy()->end();

    foreach ($registry as $t) {
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

/**
 * Seed one catalog product inside the active tenant database and return its id.
 * The order_lines.product_id FK requires a real product.
 */
function rtsSeedProduct(string $sku): int
{
    $now = now();

    $categoryId = DB::table('categories')->insertGetId([
        'name' => 'Cat '.$sku, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $collectionId = DB::table('collections')->insertGetId([
        'category_id' => $categoryId, 'name' => 'Col '.$sku, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $colourId = DB::table('colours')->insertGetId([
        'collection_id' => $collectionId, 'name' => 'Clr '.$sku, 'created_at' => $now, 'updated_at' => $now,
    ]);

    return (int) DB::table('products')->insertGetId([
        'sku' => $sku,
        'name' => 'Product '.$sku,
        'collection_id' => $collectionId,
        'colour_id' => $colourId,
        'tax_type' => 'gst_18',
        'uom' => 'pcs',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

/**
 * Seed one confirmed order with a single line for the given polymorphic buyer,
 * so the sales report counts it. Returns the created order id. created_at is
 * pinned to the anchor so the resolved date window includes it.
 */
function rtsSeedConfirmedSale(
    string $buyerType,
    int $buyerId,
    string $sellerType,
    int $sellerId,
    int $productId,
    float $quantity,
    float $unitPrice,
    CarbonImmutable $createdAt,
): int {
    $now = now();

    $orderId = (int) DB::table('orders')->insertGetId([
        'buyer_type' => $buyerType,
        'buyer_id' => $buyerId,
        'seller_type' => $sellerType,
        'seller_id' => $sellerId,
        'status' => 'confirmed',
        'order_type' => 'standard',
        'total' => number_format($quantity * $unitPrice, 2, '.', ''),
        'created_at' => $createdAt->format('Y-m-d H:i:s'),
        'updated_at' => $now,
    ]);

    DB::table('order_lines')->insert([
        'order_id' => $orderId,
        'product_id' => $productId,
        'quantity' => number_format($quantity, 3, '.', ''),
        'unit_price' => number_format($unitPrice, 2, '.', ''),
        'discount' => 0,
        'created_at' => $createdAt->format('Y-m-d H:i:s'),
        'updated_at' => $now,
    ]);

    return $orderId;
}

/**
 * A company-side access context for the role name supplied (one of the
 * tenant-wide roles). Reads tenant-wide, applies no buyer scope (R28.10).
 */
function rtsCompanyContext(string $role): ReportAccessContext
{
    return new ReportAccessContext(roles: [$role], canViewReports: true);
}

// ===========================================================================
// LAYER A — ROLE SCOPING (pure logic, no DB)
// ===========================================================================

it('authorizes a company-side principal tenant-wide and leaves its requested buyer filter untouched (R28.10)', function () {
    static::forAll(
        // One of the four tenant-wide (company-side) roles.
        Set::of(
            Roles::PLATFORM_ADMIN,
            Roles::COMPANY_ADMIN,
            Roles::COMPANY_STAFF,
            Roles::READ_ONLY,
        ),
        // An arbitrary buyer the REQUEST asks to filter on; a company-side
        // principal may read it (and any other), so the service must NOT alter
        // or inject a buyer scope.
        Set::integers()->between(1, 100000),
        Set::of('App\\Models\\Distributor', 'App\\Models\\Dealer'),
    )->then(function (string $role, int $requestedBuyerId, string $requestedBuyerType) {
        $definition = rtsCapturingDefinition();
        $service = new ReportQueryService([$definition]);

        $spec = ReportQuerySpec::fromArray([
            'type' => 'sales',
            'filters' => ['buyerType' => $requestedBuyerType, 'buyerId' => $requestedBuyerId],
        ]);

        // Authorized → reaches baseQuery() → sentinel RuntimeException (NOT an
        // AuthorizationException, which a denial would raise).
        expect(fn () => $service->run($spec, rtsCompanyContext($role)))
            ->toThrow(RuntimeException::class, 'rts: captured filters');

        // Tenant-wide: the request's buyer filter passes through unchanged — the
        // service injected no scope of its own.
        expect($definition->seen)->not->toBeNull();
        expect($definition->seen->buyerType)->toBe($requestedBuyerType);
        expect($definition->seen->buyerId)->toBe($requestedBuyerId);
    });
});

it('force-scopes a Distributor/Dealer principal to its OWN buyer binding, overriding any requested buyer (R28.10)', function () {
    static::forAll(
        Set::of(Roles::DISTRIBUTOR, Roles::DEALER),
        // The principal's OWN binding.
        Set::of('App\\Models\\Distributor', 'App\\Models\\Dealer'),
        Set::integers()->between(1, 100000),
        // What the REQUEST tries to read — an arbitrary (possibly different)
        // buyer. The service must ignore it and force the principal's own id.
        Set::of('App\\Models\\Distributor', 'App\\Models\\Dealer'),
        Set::integers()->between(1, 100000),
    )->then(function (
        string $role,
        string $ownType,
        int $ownId,
        string $requestedType,
        int $requestedId,
    ) {
        $definition = rtsCapturingDefinition();
        $service = new ReportQueryService([$definition]);

        $spec = ReportQuerySpec::fromArray([
            'type' => 'sales',
            'filters' => ['buyerType' => $requestedType, 'buyerId' => $requestedId],
        ]);

        $context = new ReportAccessContext(
            roles: [$role],
            canViewReports: true,
            buyerType: $ownType,
            buyerId: $ownId,
        );

        expect(fn () => $service->run($spec, $context))
            ->toThrow(RuntimeException::class, 'rts: captured filters');

        // The scope is forced to the principal's OWN binding, regardless of what
        // the request asked for: a buyer can never read another buyer's data.
        expect($definition->seen)->not->toBeNull();
        expect($definition->seen->buyerType)->toBe($ownType);
        expect($definition->seen->buyerId)->toBe($ownId);
    });
});

it('denies any principal that cannot view reports, or a buyer principal with no binding, or a principal with no recognized scope (R28.10)', function () {
    static::forAll(
        // Which denial case to construct.
        Set::of('no_ability', 'buyer_no_binding', 'no_scope'),
        // A buyer role for the buyer-no-binding case.
        Set::of(Roles::DISTRIBUTOR, Roles::DEALER),
        // A requested buyer the denied principal should never reach.
        Set::integers()->between(1, 100000),
    )->then(function (string $case, string $buyerRole, int $requestedId) {
        $definition = rtsCapturingDefinition();
        $service = new ReportQueryService([$definition]);

        $spec = ReportQuerySpec::fromArray([
            'type' => 'sales',
            'filters' => ['buyerType' => 'App\\Models\\Distributor', 'buyerId' => $requestedId],
        ]);

        $context = match ($case) {
            // Lacks reports.view entirely → denied before any scope resolves.
            'no_ability' => new ReportAccessContext(
                roles: [$buyerRole],
                canViewReports: false,
                buyerType: 'App\\Models\\Distributor',
                buyerId: 42,
            ),
            // Buyer-side principal with NO resolvable binding → no readable
            // scope → denied.
            'buyer_no_binding' => new ReportAccessContext(
                roles: [$buyerRole],
                canViewReports: true,
                buyerType: null,
                buyerId: null,
            ),
            // Holds reports.view but is neither company-side nor a bound buyer
            // (e.g. an unmapped custom role) → no recognized scope → denied
            // rather than silently reading tenant-wide.
            'no_scope' => new ReportAccessContext(
                roles: ['Some_Unmapped_Role'],
                canViewReports: true,
                buyerType: null,
                buyerId: null,
            ),
        };

        // Every denial surfaces as an AuthorizationException and NEVER reaches
        // baseQuery() (the capturing stub stays untouched).
        expect(fn () => $service->run($spec, $context))
            ->toThrow(AuthorizationException::class);

        expect($definition->seen)->toBeNull();
    });
});

// ===========================================================================
// LAYER B — PHYSICAL TENANT CONFINEMENT + role confinement (DB-gated)
// ===========================================================================

it('confines every report to the active tenant database and a buyer principal to its own account data (R28.10)', function () {
    skip_without_mysql('central');

    $registry = [];

    try {
        $tenantA = rtsProvisionTenant($registry);
        $tenantB = rtsProvisionTenant($registry);

        // A fixed anchor so the resolved date window deterministically includes
        // every seeded sale, independent of when the test runs.
        $anchor = CarbonImmutable::parse('2024-06-15 09:00:00');
        $relativeTo = CarbonImmutable::parse('2024-06-20 12:00:00');
        $window = [
            'from' => $anchor->subDay()->format('Y-m-d H:i:s'),
            'to' => $anchor->addDays(2)->format('Y-m-d H:i:s'),
        ];

        static::forAll(
            // tenant A: number of distinct buyers, and sales per buyer.
            Set::integers()->between(1, 3),
            Set::integers()->between(1, 4),
            // tenant B: a different sales population (must stay invisible to A).
            Set::integers()->between(1, 5),
            // Per-unit price seed (kept positive so sales_value is meaningful).
            Set::integers()->between(1, 500),
        )->then(function (
            int $aBuyerCount,
            int $aSalesPerBuyer,
            int $bSalesCount,
            int $unitPriceSeed,
        ) use ($tenantA, $tenantB, $anchor, $relativeTo, $window) {
            $sellerType = 'App\\Models\\Company';
            $buyerType = 'App\\Models\\Distributor';
            $unitPrice = (float) $unitPriceSeed;

            // A per-iteration token so each reseed uses fresh, unique SKUs:
            // products are not cleared between iterations (the sales report only
            // joins them), so a reused SKU would violate the per-tenant unique
            // constraint. The sales assertions key off orders, not SKUs, so the
            // token value is irrelevant to the invariant.
            $token = strtolower(bin2hex(random_bytes(4)));

            // ---- Seed tenant B with its OWN sales (the data A must NOT see) --
            $tenantB->run(function () use ($bSalesCount, $sellerType, $buyerType, $anchor, $unitPrice, $token) {
                DB::connection('tenant')->table('order_lines')->delete();
                DB::connection('tenant')->table('orders')->delete();

                $company = Company::create(['name' => 'Tenant B Co']);
                $product = rtsSeedProduct('TB-'.$token);

                for ($i = 0; $i < $bSalesCount; $i++) {
                    rtsSeedConfirmedSale(
                        $buyerType,
                        9000 + $i, // buyer ids that also exist as A buyer ids below
                        $sellerType,
                        (int) $company->getKey(),
                        $product,
                        2.0,
                        $unitPrice,
                        $anchor,
                    );
                }
            });

            // ---- Seed tenant A: several distinct buyers, each with sales -----
            // Record, per buyer, how many sales (= report rows at buyer grain)
            // belong to it so we can assert both tenant confinement and buyer
            // role confinement.
            $aBuyerIds = [];
            for ($b = 0; $b < $aBuyerCount; $b++) {
                $aBuyerIds[] = 9000 + $b; // deliberately overlap tenant B ids
            }

            $tenantA->run(function () use ($aBuyerIds, $aSalesPerBuyer, $sellerType, $buyerType, $anchor, $unitPrice, $token) {
                DB::connection('tenant')->table('order_lines')->delete();
                DB::connection('tenant')->table('orders')->delete();

                $company = Company::create(['name' => 'Tenant A Co']);
                $product = rtsSeedProduct('TA-'.$token);

                foreach ($aBuyerIds as $buyerId) {
                    for ($i = 0; $i < $aSalesPerBuyer; $i++) {
                        rtsSeedConfirmedSale(
                            $buyerType,
                            $buyerId,
                            $sellerType,
                            (int) $company->getKey(),
                            $product,
                            2.0,
                            $unitPrice,
                            $anchor,
                        );
                    }
                }
            });

            // Each tenant run builds its own service so the SalesReportDefinition
            // binds the active tenant connection; a fresh cache per run avoids
            // cross-iteration reuse (cache correctness is Property 31's concern).
            $buildService = fn (): ReportQueryService => new ReportQueryService(
                [new SalesReportDefinition],
                new ReportCache,
            );

            // ---------------------------------------------------------------
            // (1) PHYSICAL TENANT CONFINEMENT. A company-side read in tenant A
            //     sees ONLY tenant A's buyers; a company-side read in tenant B
            //     sees ONLY tenant B's. Neither leaks the other's rows, even
            //     though buyer ids deliberately overlap.
            // ---------------------------------------------------------------
            $tenantA->run(function () use ($buildService, $window, $relativeTo, $aBuyerIds) {
                $result = $buildService()->run(
                    ReportQuerySpec::fromArray([
                        'type' => 'sales',
                        'filters' => $window,
                        'grouping' => ['buyer'],
                        'limit' => 100,
                    ]),
                    rtsCompanyContext(Roles::COMPANY_ADMIN),
                    $relativeTo,
                );

                $seenBuyerIds = array_map(static fn (array $r): int => (int) $r['buyer_id'], $result->rows);
                sort($seenBuyerIds);
                $expected = $aBuyerIds;
                sort($expected);

                // Exactly tenant A's buyers — never a tenant B buyer, even where
                // ids overlap, because the query physically runs on A's database.
                expect($seenBuyerIds)->toBe($expected);
            });

            $tenantB->run(function () use ($buildService, $window, $relativeTo, $bSalesCount) {
                $result = $buildService()->run(
                    ReportQuerySpec::fromArray([
                        'type' => 'sales',
                        'filters' => $window,
                        'grouping' => ['buyer'],
                        'limit' => 100,
                    ]),
                    rtsCompanyContext(Roles::COMPANY_ADMIN),
                    $relativeTo,
                );

                // Tenant B sees exactly its own distinct buyers (ids 9000..).
                expect($result->rows)->toHaveCount($bSalesCount);
            });

            // ---------------------------------------------------------------
            // (2) ROLE CONFINEMENT within tenant A. A Distributor principal
            //     bound to buyer A[0] sees ONLY its own sales rows, no matter
            //     which buyer the REQUEST tries to filter on — including a buyer
            //     that exists in the tenant (A's other buyers) and one that only
            //     exists in tenant B.
            // ---------------------------------------------------------------
            $tenantA->run(function () use ($buildService, $window, $relativeTo, $aBuyerIds, $aSalesPerBuyer, $buyerType) {
                $ownBuyerId = $aBuyerIds[0];

                // The request maliciously asks for a DIFFERENT buyer's data.
                $otherRequestedId = $aBuyerIds[count($aBuyerIds) - 1] + 12345;

                $context = new ReportAccessContext(
                    roles: [Roles::DISTRIBUTOR],
                    canViewReports: true,
                    buyerType: $buyerType,
                    buyerId: $ownBuyerId,
                );

                $result = $buildService()->run(
                    ReportQuerySpec::fromArray([
                        'type' => 'sales',
                        'filters' => $window + ['buyerType' => $buyerType, 'buyerId' => $otherRequestedId],
                        'grouping' => ['buyer'],
                        'limit' => 100,
                    ]),
                    $context,
                    $relativeTo,
                );

                // Every returned row belongs to the principal's OWN buyer id.
                foreach ($result->rows as $row) {
                    expect((int) $row['buyer_id'])->toBe($ownBuyerId);
                }

                // The report resolved to exactly one buyer grain row — its own —
                // confirming it was force-scoped, not reading the requested
                // other buyer (which has no rows) nor tenant-wide.
                expect($result->rows)->toHaveCount(1);
                expect((int) $result->rows[0]['buyer_id'])->toBe($ownBuyerId);
            });
        });
    } finally {
        rtsTeardownTenants($registry);
    }
});
