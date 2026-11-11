<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderStatus;
use App\Models\Stock;
use App\Models\Tenant;
use App\Models\TransactionLedger;
use App\Services\AccountLedgerService;
use App\Services\CreditEnforcementService;
use App\Services\InvoiceService;
use App\Services\OrderConfirmationService;
use App\Services\OrderStateMachine;
use App\Services\Stock\StockMovement;
use App\Services\StockMovementService;
use App\Services\WhiteLabelService;
use App\Support\AuditRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| Task 9.17 — Authorized override of a blocked action (DB-gated)
|--------------------------------------------------------------------------
|
| R26.5: WHERE an authorized administrator records a temporary limit override
| with a reason, THE Platform SHALL permit the otherwise-blocked action and
| record the override in the Audit_Log.
| R34.3: WHEN a limit override occurs, THE Platform SHALL record the action and
| its reason in the Audit_Log.
|
| Where task 9.14's suite proves the override seam on CreditEnforcementService
| in isolation, this focused unit test proves the override end-to-end:
|   • a confirmation that WOULD be blocked by the credit limit (O + T > L) is
|     PERMITTED when an authorized `credit_override_reason` is supplied through
|     OrderConfirmationService::confirm(), and the confirmation completes
|     (status Confirmed, invoice + ledger written) (R26.5);
|   • the override is recorded in the Audit_Log with its reason (R26.5, R34.3);
|   • an overdue-balance block is likewise lifted by an authorized override and
|     audited with its reason (R26.5, R34.3);
|   • a blank/absent reason is NOT an override, so the block stays in force and
|     nothing is audited (negative control).
|
| DB-GATED: needs MySQL + CREATE DATABASE for stancl tenancy; skips gracefully
| when MySQL is unreachable, mirroring the sibling credit/confirmation suites.
|
| NOTE: top-level helpers are file-uniquely named (…Override917) so Pest can
| load this file alongside CreditEnforcementServiceTest and
| OrderConfirmationServiceTest without redeclaration collisions.
|
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Provision + migrate an isolated tenant for override tests.
 *
 * @param  array<int, Tenant>  $registry
 */
function provisionOverride917Tenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'ovr'.$suffix,
        'subdomain' => 'ovr-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'ovr-'.$suffix.'.example.test']);
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
function teardownOverride917Tenants(array $registry): void
{
    foreach ($registry as $t) {
        try {
            $t->database()->getTenantDatabaseManager()->deleteDatabase($t);
        } catch (Throwable $e) {
            // best-effort
        }
        $t->domains()->delete();
        $t->delete();
    }
}

/**
 * Build OrderConfirmationService wired to real collaborators.
 */
function makeOverride917ConfirmationService(): OrderConfirmationService
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
 * Build the CreditEnforcementService wired to real collaborators.
 */
function makeOverride917CreditService(): CreditEnforcementService
{
    return new CreditEnforcementService(new AccountLedgerService, new AuditRecorder);
}

/**
 * Create a stock point owned by the given owner via the polymorphic owner
 * association (file-unique so it never collides with sibling suites).
 */
function seedOverride917StockPoint(Illuminate\Database\Eloquent\Model $owner, string $name, int $sortOrder = 0): App\Models\StockPoint
{
    $point = new App\Models\StockPoint(['name' => $name, 'sort_order' => $sortOrder]);
    $point->owner()->associate($owner);
    $point->save();

    return $point;
}

/**
 * Seed one catalog product inside the active tenant database and return its id.
 */
function seedOverride917Product(string $sku = 'SKU-OVR'): int
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
 * A Distributor buyer carrying the given Payment_Terms.
 *
 * @param  array<string, mixed>  $terms
 */
function makeOverride917Buyer(Company $company, array $terms = []): Distributor
{
    return Distributor::create([
        'company_id' => $company->getKey(),
        'name' => 'Dist '.bin2hex(random_bytes(2)),
        'contact_email' => 'dist@example.test',
        'payment_terms' => $terms,
    ]);
}

/**
 * Create an overdue invoice for a buyer with a backdated issue date, routed
 * through an Order so the credit service can resolve it by buyer.
 */
function makeOverride917Invoice(Company $company, Distributor $buyer, string $amount, Carbon $issuedAt): Invoice
{
    $order = Order::create([
        'buyer_type' => $buyer->getMorphClass(),
        'buyer_id' => $buyer->getKey(),
        'seller_type' => $company->getMorphClass(),
        'seller_id' => $company->getKey(),
        'status' => 'confirmed',
        'order_type' => 'standard',
        'total' => $amount,
    ]);

    $invoice = new Invoice([
        'order_id' => $order->id,
        'series' => 'COMPANY',
        'number' => (Invoice::max('number') ?? 0) + 1,
        'amount' => $amount,
        'overdue' => false,
    ]);
    $invoice->seller()->associate($company);
    $invoice->save();

    $invoice->forceFill(['created_at' => $issuedAt])->saveQuietly();

    return $invoice->fresh();
}

// -------- R26.5 / R34.3: credit-limit override permits confirmation + audits --------

it('permits an otherwise-blocked confirmation when an authorized credit override reason is supplied, and audits it (R26.5, R34.3)', function () {
    Mail::fake();
    $registry = [];
    $tenant = provisionOverride917Tenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $companyPoint = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);

            // Credit limit 100; order total 150 → O(0) + T(150) > L(100): blocked
            // without an override.
            $buyer = makeOverride917Buyer($company, ['credit_limit' => '100.00']);
            seedOverride917StockPoint($buyer, 'Dist Main');
            $productId = seedOverride917Product('SKU-OVR-CREDIT');

            (new StockMovementService)->apply([
                StockMovement::forOwner($company, $productId, $companyPoint->id, '100.000', 'opening'),
            ]);

            $order = Order::create([
                'buyer_type' => $buyer->getMorphClass(),
                'buyer_id' => $buyer->getKey(),
                'seller_type' => $company->getMorphClass(),
                'seller_id' => $company->getKey(),
                'status' => OrderStatus::PLACED,
                'order_type' => 'standard',
                'total' => '150.00',
            ]);
            OrderLine::create([
                'order_id' => $order->getKey(),
                'product_id' => $productId,
                'quantity' => '10.000',
                'unit_price' => '15.00',
                'discount' => '0.00',
            ]);

            // With the authorized override reason, the blocked confirmation is
            // permitted and completes (R26.5).
            $confirmed = makeOverride917ConfirmationService()->confirm(
                $order->load('lines'),
                ['credit_override_reason' => 'Approved by CFO for strategic account'],
            );

            // Confirmation went through end-to-end despite O + T > L.
            expect($confirmed->status)->toBe(OrderStatus::CONFIRMED);
            expect(Invoice::count())->toBe(1);
            expect(TransactionLedger::where('buyer_type', $buyer->getMorphClass())
                ->where('buyer_id', $buyer->getKey())->count())->toBe(1);

            // The override was recorded in the Audit_Log with its reason
            // (R26.5, R34.3).
            $audit = AuditLog::where('event', 'credit_limit_override')->latest('id')->first();
            expect($audit)->not->toBeNull();
            expect($audit->reason)->toBe('Approved by CFO for strategic account');
            expect($audit->new_values['check'] ?? null)->toBe('credit_limit');
            expect((int) ($audit->new_values['buyer_id'] ?? 0))->toBe((int) $buyer->getKey());
        });
    } finally {
        teardownOverride917Tenants($registry);
    }
});

// -------- R26.5 / R34.3: overdue-block override permits a new order + audits --------

it('permits an otherwise-blocked new order when an authorized overdue override reason is supplied, and audits it (R26.5, R34.3)', function () {
    $registry = [];
    $tenant = provisionOverride917Tenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $buyer = makeOverride917Buyer($company, [
                'overdue_hold_days' => '30',
                'overdue_balance_limit' => '150.00',
            ]);
            $service = makeOverride917CreditService();

            // Overdue balance 200 > 150 limit: new order would be blocked.
            makeOverride917Invoice($company, $buyer, '200.00', Carbon::now()->subDays(45));
            $service->flagOverdueInvoices($buyer);
            expect((string) $service->overdueBalance($buyer))->toBe('200.00');

            // With the authorized override reason, the block is lifted and the
            // action is permitted (no exception) (R26.5).
            $service->assertNotOverdueBlocked($buyer, 'Temporary waiver authorized by Company_Admin');

            $audit = AuditLog::where('event', 'overdue_block_override')->latest('id')->first();
            expect($audit)->not->toBeNull();
            expect($audit->reason)->toBe('Temporary waiver authorized by Company_Admin');
            expect($audit->new_values['check'] ?? null)->toBe('overdue_balance');
        });
    } finally {
        teardownOverride917Tenants($registry);
    }
});

// -------- Negative control: a blank reason is not an override --------

it('does not treat a blank override reason as authorized, so the block stays in force and nothing is audited (R26.5)', function () {
    $registry = [];
    $tenant = provisionOverride917Tenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $buyer = makeOverride917Buyer($company, ['credit_limit' => '100.00']);
            $service = makeOverride917CreditService();

            (new AccountLedgerService)->appendPurchase($buyer, '100.00');

            // Projected 150 > 100 would block; a blank reason is not an override.
            expect(fn () => $service->assertCreditWithinLimit($buyer, '50.00', '   '))
                ->toThrow(App\Exceptions\CreditLimitExceededException::class);

            // No override was recorded.
            expect(AuditLog::where('event', 'credit_limit_override')->count())->toBe(0);
        });
    } finally {
        teardownOverride917Tenants($registry);
    }
});
