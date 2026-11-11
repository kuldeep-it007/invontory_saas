<?php

declare(strict_types=1);

use App\Auth\Roles;
use App\Jobs\GenerateReportExport;
use App\Mail\AccountCreatedMail;
use App\Mail\OrderConfirmedMail;
use App\Mail\OrderPlacedMail;
use App\Mail\PaymentRecordedMail;
use App\Models\Company;
use App\Models\Distributor;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderStatus;
use App\Models\Tenant;
use App\Reports\ExportFormat;
use App\Reports\ExportStatus;
use App\Reports\ReportAccessContext;
use App\Reports\ReportQuerySpec;
use App\Services\AccountLedgerService;
use App\Services\CreditEnforcementService;
use App\Services\DashboardService;
use App\Services\InventoryViewService;
use App\Services\InvoiceService;
use App\Services\OrderConfirmationService;
use App\Services\OrderStateMachine;
use App\Services\QuotaService;
use App\Services\ReportExportService;
use App\Services\ReportQueryService;
use App\Services\StockMovementService;
use App\Services\Stock\StockMovement;
use App\Services\WhiteLabelService;
use App\Support\AuditRecorder;
use App\Reports\Definitions\LowStockReportDefinition;
use App\Reports\Definitions\OrderStatusReportDefinition;
use App\Reports\Definitions\OutstandingAgeingReportDefinition;
use App\Reports\Definitions\PaymentsReportDefinition;
use App\Reports\Definitions\PlanUsageReportDefinition;
use App\Reports\Definitions\PurchasesReportDefinition;
use App\Reports\Definitions\SalesReportDefinition;
use App\Reports\Definitions\StockLedgerReportDefinition;
use App\Reports\Definitions\StockOnHandReportDefinition;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| Task 15.9 — Integration tests: performance & queueing
|--------------------------------------------------------------------------
|
| Two non-functional guarantees of the platform (R37):
|
|   • QUEUEING (R37.2): heavy work is processed through the asynchronous queue,
|     never inline on the request. The deployment default (.env) routes the
|     queue at `redis`. This suite proves the three heavy workloads actually
|     dispatch to the queue instead of running synchronously:
|       - the four transactional notification emails (account-created,
|         order-placed, order-confirmed, payment-recorded) are `ShouldQueue`
|         mailables pushed onto the queue after their action commits;
|       - a LARGE report export is enqueued as a `GenerateReportExport` job
|         rather than streamed inline, leaving the request a bounded, cheap
|         "accepted / pending" response (the streaming happens on the worker).
|
|   • PERFORMANCE (R37.1 + the R38 efficiency rules that make it achievable):
|     key interactive reads return within the 2-second budget under a normal
|     seeded load AND are structurally cheap — every list read is paginated to a
|     bounded page (R38.1), runs a bounded (non-N+1) number of queries, and never
|     materialises an unbounded result set. We assert BOTH the wall-clock budget
|     and the structural bounds, because a wall-clock number alone is flaky; the
|     structural bounds are what actually keep the page under budget as data
|     grows.
|
| These are the INTEGRATION angles the per-feature unit suites do not cover:
| they exercise the real services end-to-end against a provisioned tenant
| database and assert the queueing/efficiency CONTRACT rather than any single
| feature's output.
|
| DB-GATED: needs MySQL + CREATE DATABASE for stancl tenancy; skips gracefully
| when MySQL is unreachable, mirroring the sibling DB-gated suites.
|
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Provision + migrate an isolated tenant for the performance/queueing tests.
 *
 * @param  array<int, Tenant>  $registry
 */
function provisionPerfTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'perf'.$suffix,
        'subdomain' => 'perf-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'perf-'.$suffix.'.example.test']);
    $registry[] = $tenant;

    $tenant->run(function () {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
            '--force' => true,
        ]);
    });

    return $tenant;
}

/**
 * @param  array<int, Tenant>  $registry
 */
function teardownPerfTenants(array $registry): void
{
    tenancy()->end();

    foreach ($registry as $t) {
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

function perfAdminContext(): ReportAccessContext
{
    return new ReportAccessContext(roles: [Roles::COMPANY_ADMIN], canViewReports: true);
}

function makePerfReportService(): ReportQueryService
{
    return new ReportQueryService([
        new StockOnHandReportDefinition,
        new StockLedgerReportDefinition,
        new SalesReportDefinition,
        new PurchasesReportDefinition,
        new OutstandingAgeingReportDefinition,
        new PaymentsReportDefinition,
        new OrderStatusReportDefinition,
        new LowStockReportDefinition,
        new PlanUsageReportDefinition,
    ]);
}

function makePerfConfirmationService(): OrderConfirmationService
{
    $ledger = new AccountLedgerService;

    return new OrderConfirmationService(
        new OrderStateMachine,
        new StockMovementService,
        new CreditEnforcementService($ledger, new AuditRecorder),
        new InvoiceService,
        $ledger,
        app(WhiteLabelService::class),
    );
}

/**
 * Seed one catalog product (with its category/collection/colour) and return its
 * id, mirroring the sibling suites.
 */
function seedPerfProduct(string $sku): int
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
 * Seed `$count` confirmed payments dated within the previous calendar month so
 * a payments report over the DEFAULT range (previous month — R29.2) returns
 * rows. Mirrors the async-export suite's generator.
 */
function seedPerfPreviousMonthPayments(int $count): void
{
    $paidAt = now()->subMonthNoOverflow()->startOfMonth()->addDays(2)->toDateString();
    $now = now();

    $rows = [];
    for ($i = 0; $i < $count; $i++) {
        $rows[] = [
            'buyer_type' => 'App\\Models\\Distributor',
            'buyer_id' => ($i % 25) + 1,
            'amount' => '10.00',
            'method' => 'cash',
            'paid_at' => $paidAt,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    foreach (array_chunk($rows, 200) as $chunk) {
        DB::table('payments')->insert($chunk);
    }
}

/*
|--------------------------------------------------------------------------
| QUEUEING (R37.2): heavy tasks go through the asynchronous queue
|--------------------------------------------------------------------------
*/

it('declares every heavy workload as a queued (ShouldQueue) task so it never runs inline (R37.2)', function () {
    // The four transactional notification emails and the report-export job are
    // the platform's heavy workloads. Each must be a queued task so the request
    // thread is never blocked on mail delivery or a large export stream (R37.2).
    $heavy = [
        AccountCreatedMail::class,
        OrderPlacedMail::class,
        OrderConfirmedMail::class,
        PaymentRecordedMail::class,
        GenerateReportExport::class,
    ];

    foreach ($heavy as $class) {
        expect(is_subclass_of($class, ShouldQueue::class))
            ->toBeTrue("{$class} must implement ShouldQueue so it is processed on the async queue (R37.2)");
    }
});

it('routes the deployment queue through Redis so heavy tasks are offloaded to a durable backend (R37.2)', function () {
    // The shipped environment configures the queue at `redis` (.env), i.e. heavy
    // tasks are enqueued onto a durable, out-of-process backend rather than run
    // synchronously. We read the raw .env value (the test harness overrides the
    // runtime connection to `sync` for speed) to assert the DEPLOYMENT contract.
    $envPath = base_path('.env');
    expect(is_file($envPath))->toBeTrue();

    $env = (string) file_get_contents($envPath);
    expect($env)->toMatch('/^\s*QUEUE_CONNECTION\s*=\s*redis\s*$/m');

    // And the `redis` connection is a real redis-driver queue, not an alias.
    expect(config('queue.connections.redis.driver'))->toBe('redis');
});

it('enqueues the order-confirmed email as a queued job instead of sending it inline (R37.2, R27.3)', function () {
    $registry = [];
    $tenant = provisionPerfTenant($registry);

    try {
        // Fake only MAIL: the confirmation itself runs for real against the
        // tenant DB; we assert the slow email is QUEUED (not sent inline).
        Mail::fake();

        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $companyPoint = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $distributor = Distributor::create([
                'company_id' => $company->getKey(),
                'name' => 'Dist One',
                'contact_email' => 'dist@example.test',
            ]);
            $distPoint = new App\Models\StockPoint(['name' => 'Dist Main', 'sort_order' => 0]);
            $distPoint->owner()->associate($distributor);
            $distPoint->save();

            $productId = seedPerfProduct('SKU-QUEUE');

            (new StockMovementService)->apply([
                StockMovement::forOwner($company, $productId, $companyPoint->id, '100.000', 'opening'),
            ]);

            $order = Order::create([
                'buyer_type' => $distributor->getMorphClass(),
                'buyer_id' => $distributor->getKey(),
                'seller_type' => $company->getMorphClass(),
                'seller_id' => $company->getKey(),
                'status' => OrderStatus::PLACED,
                'order_type' => 'standard',
                'total' => '250.00',
            ]);
            OrderLine::create([
                'order_id' => $order->getKey(),
                'product_id' => $productId,
                'quantity' => '10.000',
                'unit_price' => '25.00',
                'discount' => '0.00',
            ]);

            makePerfConfirmationService()->confirm($order->load('lines'));
        });

        // The email was pushed onto the QUEUE (queued), not sent synchronously
        // on the request thread (R37.2). assertQueued proves it rode the queue;
        // assertNothingSent proves nothing bypassed it to send inline.
        Mail::assertQueued(OrderConfirmedMail::class);
        Mail::assertNothingSent();
    } finally {
        teardownPerfTenants($registry);
    }
});

it('enqueues a LARGE report export as a queued streaming job rather than producing it inline (R37.2, R29.5)', function () {
    $registry = [];
    $tenant = provisionPerfTenant($registry);

    try {
        $tenant->run(function () {
            // A low threshold makes the seeded result "large" so the export MUST
            // be offloaded to the queue (the production path for a big report).
            config(['reports.export.async_threshold' => 5]);
            Company::create(['name' => 'Acme']);
            seedPerfPreviousMonthPayments(30);

            // Capture the dispatched job WITHOUT running it — exactly what a
            // worker would find waiting on the queue.
            Queue::fake();

            $context = perfAdminContext();
            $service = app(ReportExportService::class);

            $export = $service->export(
                spec: ReportQuerySpec::fromArray(['type' => 'payments']),
                context: $context,
                format: ExportFormat::CSV,
            );

            // The request returned a cheap, bounded "pending" handle — the heavy
            // streaming has NOT run inline (R37.2): no file, no completion yet.
            expect($export->status)->toBe(ExportStatus::PENDING->value);
            expect($export->download_url)->toBeNull();
            expect($export->row_count)->toBe(0);

            // Exactly one queued streaming job was pushed for THIS export.
            Queue::assertPushed(
                GenerateReportExport::class,
                fn (GenerateReportExport $job) => $job->exportId === $export->id,
            );
        });
    } finally {
        teardownPerfTenants($registry);
    }
});

it('keeps a SMALL export inline (no queued job) so only LARGE work is offloaded — the queueing boundary (R37.2, R29.5)', function () {
    $registry = [];
    $tenant = provisionPerfTenant($registry);

    try {
        $tenant->run(function () {
            // A high threshold keeps the small seeded result under "large", so
            // it is produced inline and NO job is queued. This pins the boundary:
            // only heavy work is offloaded (R37.2), everything else stays cheap.
            config(['reports.export.async_threshold' => 1000]);
            Company::create(['name' => 'Acme']);
            seedPerfPreviousMonthPayments(3);

            Queue::fake();

            $service = app(ReportExportService::class);
            $export = $service->export(
                spec: ReportQuerySpec::fromArray(['type' => 'payments']),
                context: perfAdminContext(),
                format: ExportFormat::CSV,
            );

            // Small export completed inline; no streaming job was queued.
            expect($export->status)->toBe(ExportStatus::COMPLETED->value);
            Queue::assertNotPushed(GenerateReportExport::class);
        });
    } finally {
        teardownPerfTenants($registry);
    }
});

/*
|--------------------------------------------------------------------------
| PERFORMANCE (R37.1): key interactive reads are fast AND structurally bounded
|--------------------------------------------------------------------------
|
| R37.1 (under 2s) is only achievable because of the R38 efficiency rules:
| pagination (bounded page), indexed SQL-side aggregation, eager loading (no
| N+1), and selecting only needed columns. A bare wall-clock number is flaky, so
| each test asserts BOTH:
|   • a generous wall-clock budget (well under 2s on a seeded tenant), and
|   • the structural bounds that keep it fast as data grows — a bounded result
|     set and a bounded (non-N+1) query count.
*/

/** The interactive-page budget (R37.1). Generous vs. the 2s SLA to stay stable. */
const PERF_PAGE_BUDGET_SECONDS = 2.0;

/**
 * Run `$fn` on the active tenant connection with the query log enabled and
 * return [result, queryCount, elapsedSeconds] so a test can assert the page is
 * both fast (R37.1) and structurally cheap (bounded, non-N+1 — R38.1).
 *
 * @template T
 * @param  callable():T  $fn
 * @return array{0: T, 1: int, 2: float}
 */
function perfMeasure(callable $fn): array
{
    $connection = DB::connection(ReportQueryService::CONNECTION);
    $connection->flushQueryLog();
    $connection->enableQueryLog();

    $start = microtime(true);
    $result = $fn();
    $elapsed = microtime(true) - $start;

    $queryCount = count($connection->getQueryLog());
    $connection->disableQueryLog();

    return [$result, $queryCount, $elapsed];
}

it('serves the company master dashboard within budget with a bounded, non-N+1 query count (R37.1, R38.1)', function () {
    $registry = [];
    $tenant = provisionPerfTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);

            // A realistic "normal load": many products with stock, and a spread
            // of orders/payments across statuses. The dashboard must summarise
            // all of it with a HANDFUL of aggregate queries, not one-per-row.
            $now = now();
            for ($i = 0; $i < 60; $i++) {
                $productId = seedPerfProduct('SKU-DASH-'.$i);
                DB::table('stock')->insert([
                    'stockable_type' => $company->getMorphClass(),
                    'stockable_id' => $company->getKey(),
                    'product_id' => $productId,
                    'stock_point_id' => $point->getKey(),
                    'quantity' => '10.000',
                    'low_stock_threshold' => $i % 10 === 0 ? '20.000' : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $orderRows = [];
            foreach (['placed', 'confirmed', 'delivered', 'cancelled'] as $status) {
                for ($j = 0; $j < 25; $j++) {
                    $orderRows[] = [
                        'buyer_type' => 'App\\Models\\Distributor', 'buyer_id' => 1,
                        'seller_type' => $company->getMorphClass(), 'seller_id' => $company->getKey(),
                        'status' => $status, 'order_type' => 'standard', 'total' => '10.00',
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                }
            }
            foreach (array_chunk($orderRows, 200) as $chunk) {
                DB::table('orders')->insert($chunk);
            }

            $dashboard = new DashboardService(app(QuotaService::class));

            [$snapshot, $queryCount, $elapsed] = perfMeasure(fn () => $dashboard->snapshot());

            // Correctness sanity: the snapshot reflects the seeded load.
            expect($snapshot['orders']['total'])->toBe(100);
            expect($snapshot['inventory']['products'])->toBe(60);

            // Under the interactive budget (R37.1).
            expect($elapsed)->toBeLessThan(PERF_PAGE_BUDGET_SECONDS);

            // Bounded, non-N+1 (R38.1): the dashboard is a small fixed set of
            // headline metrics, so it must stay a small CONSTANT number of
            // aggregate queries regardless of the 60 products / 100 orders — it
            // never scales with row count. The ceiling is generous (one aggregate
            // per headline block plus quota lookups) but constant.
            expect($queryCount)->toBeLessThanOrEqual(25);
        });
    } finally {
        teardownPerfTenants($registry);
    }
});

it('serves a paginated inventory view as a bounded page within budget with a bounded query count (R37.1, R38.1)', function () {
    $registry = [];
    $tenant = provisionPerfTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $point = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);

            // 200 distinct products with stock. A page MUST NOT return them all.
            $now = now();
            $stockRows = [];
            for ($i = 0; $i < 200; $i++) {
                $productId = seedPerfProduct('SKU-INV-'.$i);
                $stockRows[] = [
                    'stockable_type' => $company->getMorphClass(),
                    'stockable_id' => $company->getKey(),
                    'product_id' => $productId,
                    'stock_point_id' => $point->getKey(),
                    'quantity' => '5.000',
                    'low_stock_threshold' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($stockRows, 200) as $chunk) {
                DB::table('stock')->insert($chunk);
            }

            $service = new InventoryViewService(makePerfReportService());

            // Explicit full date range (from..to) so the point-in-time snapshot
            // is NOT narrowed by the previous-month default (R29.2) — the view
            // reflects "stock as of now".
            $filters = [
                'from' => now()->subYear()->toDateTimeString(),
                'to' => now()->endOfDay()->toDateTimeString(),
            ];

            [$result, $queryCount, $elapsed] = perfMeasure(
                fn () => $service->run(
                    InventoryViewService::VIEW_STOCK_ON_HAND,
                    perfAdminContext(),
                    $filters,
                    limit: 25,
                ),
            );

            // BOUNDED RESULT SET (R38.1): a single page of 25, never all 200,
            // with the total surfaced for the caller to page through the rest.
            expect($result->count())->toBe(25);
            expect($result->perPage)->toBe(25);
            expect($result->total)->toBe(200);
            expect($result->hasMorePages())->toBeTrue();

            // Fast (R37.1) and bounded query count: a paginated aggregate is one
            // page query + one count query (and never a per-row query — no N+1).
            expect($elapsed)->toBeLessThan(PERF_PAGE_BUDGET_SECONDS);
            expect($queryCount)->toBeLessThanOrEqual(4);
        });
    } finally {
        teardownPerfTenants($registry);
    }
});

it('serves a paginated report as a bounded page within budget with a bounded (non-N+1) query count (R37.1, R38.1)', function () {
    $registry = [];
    $tenant = provisionPerfTenant($registry);

    try {
        $tenant->run(function () {
            Company::create(['name' => 'Acme']);

            // 300 payments across 25 buyers in the previous month; the payments
            // report aggregates them SQL-side and returns a bounded page.
            seedPerfPreviousMonthPayments(300);

            $reports = makePerfReportService();

            [$result, $queryCount, $elapsed] = perfMeasure(
                fn () => $reports->run(
                    // No date range supplied → defaults to previous month
                    // (R29.2), which is where the payments were seeded.
                    ReportQuerySpec::fromArray(['type' => 'payments', 'limit' => 20]),
                    perfAdminContext(),
                ),
            );

            // The report returns a BOUNDED page (<= requested limit), never the
            // whole aggregated set (R38.1).
            expect($result->count())->toBeLessThanOrEqual(20);
            expect($result->perPage)->toBe(20);
            expect($result->total)->toBeGreaterThan(0);

            // Fast (R37.1) and non-N+1: SQL-side aggregation means a small,
            // constant number of queries (page + grouped count), independent of
            // the 300 underlying payments.
            expect($elapsed)->toBeLessThan(PERF_PAGE_BUDGET_SECONDS);
            expect($queryCount)->toBeLessThanOrEqual(4);
        });
    } finally {
        teardownPerfTenants($registry);
    }
});
