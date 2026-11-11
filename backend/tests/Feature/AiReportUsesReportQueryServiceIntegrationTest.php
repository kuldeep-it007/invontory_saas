<?php

declare(strict_types=1);

use App\Auth\Roles;
use App\Models\Company;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Reports\Definitions\StockOnHandReportDefinition;
use App\Reports\ReportAccessContext;
use App\Reports\ReportResult;
use App\Services\Ai\AiReportService;
use App\Services\Ai\LlmClient;
use App\Services\ReportQueryService;
use App\Services\SubscriptionGateService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Task 13.5 — Integration: the AI path runs through the SHARED
| ReportQueryService and NEVER executes raw SQL (DB-gated, end-to-end)
|--------------------------------------------------------------------------
|
| Validates: Requirements 31.3, 31.4
|
| Where the task 13.3 pure-logic suite (tests/Unit/AiReportServiceTest) proves
| the wiring with a *spying* ReportQueryService whose DB aggregation is replaced,
| this suite proves the same two guarantees END-TO-END against a REAL provisioned
| tenant database, with a REAL ReportQueryService + a REAL StockOnHandReport
| definition executing live SQL:
|
|   • R31.3 — the AI layer hands its validated ReportQuerySpec to the SAME shared
|     ReportQueryService standard reports use. We prove this by (a) observing the
|     shared service is actually reached and returns the seeded tenant data —
|     i.e. the AI result equals what a direct ReportQueryService.run() produces
|     for the equivalent spec — and (b) capturing every statement the tenant
|     connection executes and asserting they are the engine's own aggregation
|     (a grouped SELECT over `stock`), not something the model dictated.
|
|   • R31.4 — raw SQL produced by the model is NEVER executed. The fake LlmClient
|     returns a spec whose JSON ALSO carries a dangerous `sql` key
|     ("DROP TABLE stock; ..."). We attach a query listener to the tenant
|     connection and assert that across the whole generate() call NO statement
|     resembling the model's raw SQL is ever run (no DROP/DELETE/TRUNCATE, and the
|     exact dangerous string never appears), and — the ultimate proof — the data
|     the model tried to destroy is still fully present and correctly aggregated
|     afterwards. Only the validated structured spec ever reaches the query layer.
|
| The AI gate is driven exactly as production does it: a central plan with the
| `ai_reports` flag ON + an active subscription (R30.7) and the platform
| `AI_REPORTS_ENABLED` master switch ON (R30.6), asserted through the REAL
| SubscriptionGateService.
|
| DB-GATED: needs MySQL + CREATE DATABASE for stancl tenancy; skips gracefully
| when MySQL is unreachable, mirroring the sibling report suites.
|
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * A fake LlmClient that returns a canned completion for every prompt — standing
 * in for a model that both maps the request to a structured spec AND tries to
 * smuggle raw SQL alongside it.
 */
function aiIntegrationLlm(string $completion): LlmClient
{
    return new class($completion) implements LlmClient
    {
        public function __construct(private readonly string $completion) {}

        public function complete(string $prompt): string
        {
            return $this->completion;
        }
    };
}

/**
 * Provision + migrate an isolated tenant for the AI integration test, then seed
 * a central plan with the `ai_reports` flag ON and an active subscription so the
 * REAL SubscriptionGateService permits AI reporting for this tenant (R30.7).
 *
 * @param  array<int, Tenant>  $registry
 */
function provisionAiReportTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'air'.$suffix,
        'subdomain' => 'air-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'air-'.$suffix.'.example.test']);
    $registry[] = $tenant;

    $tenant->run(function () {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
            '--force' => true,
        ]);
    });

    // Central plan (ai_reports ON) + active subscription so the real gate opens
    // for this tenant (R30.7). Seeded on the central connection; the plan/sub
    // live centrally while report data lives in the tenant database.
    $plan = Plan::create([
        'name' => 'AI Plan '.$suffix,
        'price' => 0,
        'billing_cycle' => 'monthly',
        'is_active' => true,
    ]);
    $plan->features()->create(['key' => PlanFeature::AI_REPORTS, 'enabled' => true]);

    Subscription::create([
        'tenant_id' => $tenant->getKey(),
        'plan_id' => $plan->getKey(),
        'status' => Subscription::STATUS_ACTIVE,
        'starts_at' => now(),
    ]);

    return $tenant;
}

function aiReportCompanyAdminContext(): ReportAccessContext
{
    return new ReportAccessContext(roles: [Roles::COMPANY_ADMIN], canViewReports: true);
}

/**
 * Seed one catalog product inside the active tenant database and return its id.
 */
function seedAiReportProduct(string $sku): int
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
 * Build the AI service wired for a true end-to-end run: the REAL
 * SubscriptionGateService (reads the seeded central plan/subscription), the
 * fake LlmClient, and a REAL ReportQueryService over the REAL stock-on-hand
 * definition — the SAME engine standard reports use (R31.3).
 */
function makeIntegrationAiService(LlmClient $llm): AiReportService
{
    return new AiReportService(
        gate: app(SubscriptionGateService::class),
        llm: $llm,
        reports: new ReportQueryService([new StockOnHandReportDefinition]),
    );
}

/**
 * @param  array<int, Tenant>  $registry
 */
function teardownAiReportTenants(array $registry): void
{
    tenancy()->end();

    foreach ($registry as $t) {
        try {
            // Child-before-parent on the central side: subscription first, then
            // the plan's children + plan, so no FK blocks the tenant deletion.
            $subscriptions = Subscription::query()->where('tenant_id', $t->getKey())->get();
            foreach ($subscriptions as $subscription) {
                $planId = $subscription->plan_id;
                $subscription->delete();
                if ($planId !== null) {
                    DB::connection('central')->table('plan_features')->where('plan_id', $planId)->delete();
                    DB::connection('central')->table('plan_limits')->where('plan_id', $planId)->delete();
                    DB::connection('central')->table('plans')->where('id', $planId)->delete();
                }
            }
        } catch (Throwable $e) {
            // best-effort
        }
        try {
            $t->database()->getTenantDatabaseManager()->deleteDatabase($t);
        } catch (Throwable $e) {
            // best-effort
        }
        try {
            $t->domains()->delete();
            $t->delete();
        } catch (Throwable $e) {
            // best-effort
        }
    }
}

/**
 * Seed Acme + a stock point and two stock rows (products A=10, B=4.5) inside the
 * active tenant database, returning a today-covering window so the stock-on-hand
 * snapshot includes the current-dated rows (the default previous-month window
 * would otherwise exclude them, per R29.2).
 *
 * @return array{from:string,to:string}
 */
function seedAiStockOnHand(): array
{
    $company = Company::create(['name' => 'Acme']);
    $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
    $productA = seedAiReportProduct('SKU-A');
    $productB = seedAiReportProduct('SKU-B');

    $now = now();
    DB::table('stock')->insert([
        [
            'stockable_type' => $company->getMorphClass(), 'stockable_id' => $company->getKey(),
            'product_id' => $productA, 'stock_point_id' => $point->getKey(),
            'quantity' => '10.000', 'created_at' => $now, 'updated_at' => $now,
        ],
        [
            'stockable_type' => $company->getMorphClass(), 'stockable_id' => $company->getKey(),
            'product_id' => $productB, 'stock_point_id' => $point->getKey(),
            'quantity' => '4.500', 'created_at' => $now, 'updated_at' => $now,
        ],
    ]);

    return [
        'from' => now()->startOfDay()->toDateTimeString(),
        'to' => now()->endOfDay()->toDateTimeString(),
    ];
}

it('runs the AI-mapped spec through the SAME shared ReportQueryService and returns the live tenant data (R31.3)', function () {
    $registry = [];
    $tenant = provisionAiReportTenant($registry);
    config(['services.ai.reports_enabled' => true]); // master switch ON (R30.6)

    try {
        $tenant->run(function () {
            $window = seedAiStockOnHand();

            // The model maps the NL request to a structured spec. No SQL here —
            // just the allow-listed structured fields the engine accepts. The
            // explicit today-window keeps the snapshot inclusive of seeded rows.
            $llm = aiIntegrationLlm(json_encode([
                'type' => 'stock_on_hand',
                'filters' => $window,
                'sort' => [['column' => 'product', 'dir' => 'asc']],
            ]));

            $service = makeIntegrationAiService($llm);

            $result = $service->generate('stock on hand by product', aiReportCompanyAdminContext());

            // It came back through the standard pipeline (R31.6) as a real
            // ReportResult carrying the live tenant aggregates.
            expect($result)->toBeInstanceOf(ReportResult::class);
            expect($result->count())->toBe(2);
            $byProduct = collect($result->rows)->keyBy('product_sku');
            expect((float) $byProduct['SKU-A']['quantity_on_hand'])->toBe(10.0);
            expect((float) $byProduct['SKU-B']['quantity_on_hand'])->toBe(4.5);

            // Equivalence proof (R31.3): running the SAME spec directly through a
            // fresh ReportQueryService yields the identical rows — the AI path is
            // not a separate query path, it is the shared one.
            $direct = (new ReportQueryService([new StockOnHandReportDefinition]))->run(
                \App\Reports\ReportQuerySpec::fromArray([
                    'type' => 'stock_on_hand',
                    'filters' => $window,
                    'sort' => [['column' => 'product', 'dir' => 'asc']],
                ]),
                aiReportCompanyAdminContext(),
            );
            expect($result->rows)->toEqual($direct->rows);
        });
    } finally {
        teardownAiReportTenants($registry);
    }
});

it('never executes raw SQL the model emits — the dangerous `sql` key is ignored and the data survives (R31.4)', function () {
    $registry = [];
    $tenant = provisionAiReportTenant($registry);
    config(['services.ai.reports_enabled' => true]); // master switch ON (R30.6)

    try {
        $tenant->run(function () {
            $window = seedAiStockOnHand();

            // The model returns a VALID structured spec but ALSO smuggles a
            // dangerous raw-SQL string under a `sql` key. The AI layer must read
            // only the allow-listed structured fields and never execute this.
            $dangerousSql = 'DROP TABLE stock; DELETE FROM products; SELECT * FROM users';
            $llm = aiIntegrationLlm(json_encode([
                'type' => 'stock_on_hand',
                'filters' => $window,
                'sql' => $dangerousSql,
                'query' => 'TRUNCATE TABLE stock',
            ]));

            // Capture EVERY statement the tenant connection runs during the AI
            // call, so we can prove the model's SQL never reached the database.
            /** @var list<string> $executed */
            $executed = [];
            DB::connection(ReportQueryService::CONNECTION)->listen(function ($query) use (&$executed) {
                $executed[] = $query->sql;
            });

            $service = makeIntegrationAiService($llm);
            $result = $service->generate('give me everything', aiReportCompanyAdminContext());

            DB::connection(ReportQueryService::CONNECTION)->flushQueryLog();

            // (a) The exact dangerous string never appears in any executed SQL,
            //     and no destructive verb the model tried ever ran.
            foreach ($executed as $sql) {
                expect($sql)->not->toContain($dangerousSql);
                expect(strtolower($sql))->not->toContain('drop table');
                expect(strtolower($sql))->not->toContain('truncate');
                expect(strtolower($sql))->not->toContain('delete from');
            }

            // (b) The query layer WAS reached — a grouped SELECT over `stock`
            //     (the engine's own aggregation), proving execution flowed
            //     through ReportQueryService, not the model's SQL (R31.3).
            $aggregations = array_filter(
                $executed,
                static fn (string $sql): bool => str_contains(strtolower($sql), 'from `stock`')
                    && str_contains(strtolower($sql), 'group by'),
            );
            expect($aggregations)->not->toBeEmpty();

            // (c) The ultimate proof the raw SQL never ran: the data the model
            //     tried to DROP/DELETE/TRUNCATE is still fully present and
            //     correctly aggregated.
            expect($result->count())->toBe(2);
            $byProduct = collect($result->rows)->keyBy('product_sku');
            expect((float) $byProduct['SKU-A']['quantity_on_hand'])->toBe(10.0);
            expect((float) $byProduct['SKU-B']['quantity_on_hand'])->toBe(4.5);

            // The tables the model targeted are untouched.
            expect(DB::table('stock')->count())->toBe(2);
            expect(DB::table('products')->count())->toBe(2);
        });
    } finally {
        teardownAiReportTenants($registry);
    }
});
