<?php

declare(strict_types=1);

use App\Mail\AccountCreatedMail;
use App\Mail\OrderConfirmedMail;
use App\Mail\OrderPlacedMail;
use App\Mail\PaymentRecordedMail;
use App\Models\Company;
use App\Models\Distributor;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderStatus;
use App\Models\Price;
use App\Models\Product;
use App\Models\Prospect;
use App\Models\Stock;
use App\Models\Tenant;
use App\Services\AccountLedgerService;
use App\Services\CartSubmissionService;
use App\Services\CreditEnforcementService;
use App\Services\DistributorService;
use App\Services\InvoiceService;
use App\Services\OrderConfirmationService;
use App\Services\OrderStateMachine;
use App\Services\PaymentService;
use App\Services\ProspectService;
use App\Services\Stock\StockMovement;
use App\Services\StockMovementService;
use App\Services\WhiteLabelService;
use App\Support\AuditRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/*
|--------------------------------------------------------------------------
| Task 15.5 — Email side-effects (R27.1–R27.4)
|--------------------------------------------------------------------------
|
| A focused suite proving the four outbound email side-effects fire for the
| right domain events, that they are QUEUED (never sent synchronously), and
| that they are dispatched only AFTER the originating transaction commits:
|
|   • account-created on prospect approval       (R27.1, R9.4)  — ProspectService
|   • order-placed  on cart submission           (R27.2)        — CartSubmissionService
|   • order-confirmed on order confirmation      (R27.3)        — OrderConfirmationService
|   • payment-recorded on payment recording      (R27.4)        — PaymentService
|
| ## How "queued, not synchronous" is proven
|
| Every service dispatches via `Mail::to(...)->queue(...)`, and each mailable
| implements {@see ShouldQueue}. Under {@see Mail::fake()} that records the
| message as QUEUED, so we assert with {@see Mail::assertQueued()} (never
| {@see Mail::assertSent()}), and additionally assert the fired mailable is a
| ShouldQueue instance so a regression that switched to a synchronous
| `->send(...)` would fail here.
|
| ## How "after the transaction commits" is proven
|
| Each service queues the mail OUTSIDE its `DB::transaction(...)` block, after
| the business write has committed. We prove this by driving an operation that
| ABORTS inside the transaction (an empty cart, an over-allocated payment) and
| asserting that NOTHING is queued — a rolled-back operation never notifies, so
| the dispatch can only happen post-commit.
|
| DB-GATED: these services run against a real provisioned tenant database
| (stancl tenancy + CREATE DATABASE). The suite skips gracefully when MySQL is
| unreachable, mirroring the sibling service suites. We DO NOT edit the
| existing ProspectApprovalFlowTest / CartSubmissionTest / PaymentServiceTest /
| OrderConfirmationServiceTest; this is a new, consolidated side-effect suite.
*/

beforeEach(function () {
    skip_without_mysql('central');
    Mail::fake();
});

/**
 * Provision + migrate an isolated tenant for the email side-effect tests.
 *
 * @param  array<int, Tenant>  $registry
 */
function provisionEmailTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'mail'.$suffix,
        'subdomain' => 'mail-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'mail-'.$suffix.'.example.test']);
    $registry[] = $tenant;

    $tenant->run(function () {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
            '--force' => true,
        ]);

        // The tenant's single Company — seller for distributor orders (R20.1).
        Company::create(['name' => 'Mail Co']);
    });

    return $tenant;
}

/**
 * @param  array<int, Tenant>  $registry
 */
function teardownEmailTenants(array $registry): void
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
 * Seed a priced catalog product inside the active tenant database.
 */
function seedEmailProduct(string $sku, float $price): Product
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

    $product = Product::create([
        'sku' => $sku,
        'name' => 'Product '.$sku,
        'collection_id' => $collectionId,
        'colour_id' => $colourId,
        'tax_type' => 'gst_18',
        'uom' => 'pcs',
    ]);

    Price::create(['level' => Price::LEVEL_PRODUCT, 'ref_id' => $product->id, 'amount' => $price]);

    return $product;
}

/**
 * Build an OrderConfirmationService wired to its real collaborators.
 */
function makeEmailConfirmationService(): OrderConfirmationService
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
 * Build a PaymentService wired to its real collaborators.
 */
function makeEmailPaymentService(): PaymentService
{
    return new PaymentService(
        new AccountLedgerService,
        new WhiteLabelService(new AuditRecorder),
    );
}

/*
|--------------------------------------------------------------------------
| R27.1 — account-created on prospect approval
|--------------------------------------------------------------------------
*/

it('queues (not sends) an account-created email when a prospect is approved (R27.1, R9.4)', function () {
    $registry = [];
    $tenant = provisionEmailTenant($registry);

    try {
        $tenant->run(function () {
            $service = app(ProspectService::class);

            $prospect = $service->signUp([
                'name' => 'Acme Prospect',
                'contact_email' => 'acme@example.test',
                'intended_buyer_type' => Prospect::TYPE_DISTRIBUTOR,
            ]);

            $service->approve($prospect);

            // Queued to the prospect's contact, carrying the created account's
            // identity (R27.1, R9.4).
            Mail::assertQueued(AccountCreatedMail::class, function (AccountCreatedMail $mail) {
                return $mail->hasTo('acme@example.test')
                    && $mail->accountName === 'Acme Prospect'
                    && $mail->buyerType === Prospect::TYPE_DISTRIBUTOR
                    && $mail instanceof ShouldQueue;
            });

            // Queued exactly once, and NEVER sent synchronously.
            Mail::assertQueued(AccountCreatedMail::class, 1);
            Mail::assertNotSent(AccountCreatedMail::class);
        });
    } finally {
        teardownEmailTenants($registry);
    }
});

it('queues NO account-created email when the approval attempt aborts before commit (post-commit dispatch, R27.1)', function () {
    $registry = [];
    $tenant = provisionEmailTenant($registry);

    try {
        $tenant->run(function () {
            $service = app(ProspectService::class);

            $prospect = $service->signUp([
                'name' => 'Rejected First',
                'contact_email' => 'nope@example.test',
                'intended_buyer_type' => Prospect::TYPE_DISTRIBUTOR,
            ]);

            // Reject first, then a second decision on the now-non-pending
            // prospect is refused before any approval write commits.
            $service->reject($prospect);

            try {
                $service->approve($prospect);
            } catch (Throwable $e) {
                // expected: approval refused for a non-pending prospect
            }

            // No approval committed → no account-created email dispatched.
            Mail::assertNotQueued(AccountCreatedMail::class);
        });
    } finally {
        teardownEmailTenants($registry);
    }
});

/*
|--------------------------------------------------------------------------
| R27.2 — order-placed on cart submission
|--------------------------------------------------------------------------
*/

it('queues (not sends) an order-placed email after a cart is submitted (R27.2)', function () {
    $registry = [];
    $tenant = provisionEmailTenant($registry);

    try {
        $tenant->run(function () {
            $distributor = app(DistributorService::class)->create([
                'name' => 'Placing Distributor',
                'contact_email' => 'buyer@example.test',
            ]);
            $product = seedEmailProduct('SKU-PLACED', 20.0);

            $order = app(CartSubmissionService::class)->submit($distributor, [
                ['product_id' => $product->id, 'quantity' => '2'],
            ]);

            // Queued to the buyer, referencing the placed order (R27.2).
            Mail::assertQueued(OrderPlacedMail::class, function (OrderPlacedMail $mail) use ($order) {
                return $mail->hasTo('buyer@example.test')
                    && $mail->orderId === (int) $order->getKey()
                    && $mail instanceof ShouldQueue;
            });

            Mail::assertQueued(OrderPlacedMail::class, 1);
            Mail::assertNotSent(OrderPlacedMail::class);
        });
    } finally {
        teardownEmailTenants($registry);
    }
});

it('queues NO order-placed email when the submission aborts before commit (post-commit dispatch, R27.2)', function () {
    $registry = [];
    $tenant = provisionEmailTenant($registry);

    try {
        $tenant->run(function () {
            $distributor = app(DistributorService::class)->create([
                'name' => 'Empty Cart Distributor',
                'contact_email' => 'buyer@example.test',
            ]);

            // An empty cart is rejected before any order is committed.
            try {
                app(CartSubmissionService::class)->submit($distributor, []);
            } catch (Throwable $e) {
                // expected: empty cart rejected
            }

            expect(Order::query()->count())->toBe(0);
            Mail::assertNotQueued(OrderPlacedMail::class);
        });
    } finally {
        teardownEmailTenants($registry);
    }
});

/*
|--------------------------------------------------------------------------
| R27.3 — order-confirmed on confirmation
|--------------------------------------------------------------------------
*/

it('queues (not sends) an order-confirmed email after an order is confirmed (R27.3)', function () {
    $registry = [];
    $tenant = provisionEmailTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::query()->firstOrFail();
            $companyPoint = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $distributor = Distributor::create([
                'company_id' => $company->getKey(),
                'name' => 'Confirm Distributor',
                'contact_email' => 'dist@example.test',
            ]);
            $product = seedEmailProduct('SKU-CONF', 25.0);

            // Seed company stock so the confirmation decrement succeeds.
            (new StockMovementService)->apply([
                StockMovement::forOwner($company, $product->id, $companyPoint->id, '100.000', 'opening'),
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
                'product_id' => $product->id,
                'quantity' => '10.000',
                'unit_price' => '25.00',
                'discount' => '0.00',
            ]);

            makeEmailConfirmationService()->confirm($order->load('lines'));

            // Queued to the buyer, referencing the confirmed order (R27.3).
            Mail::assertQueued(OrderConfirmedMail::class, function (OrderConfirmedMail $mail) use ($order) {
                return $mail->hasTo('dist@example.test')
                    && $mail->orderId === (int) $order->getKey()
                    && $mail instanceof ShouldQueue;
            });

            Mail::assertQueued(OrderConfirmedMail::class, 1);
            Mail::assertNotSent(OrderConfirmedMail::class);
        });
    } finally {
        teardownEmailTenants($registry);
    }
});

it('queues NO order-confirmed email when the confirmation rolls back (post-commit dispatch, R27.3)', function () {
    $registry = [];
    $tenant = provisionEmailTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::query()->firstOrFail();
            $companyPoint = $company->stockPoints()->create(['name' => 'Main', 'sort_order' => 0]);
            $distributor = Distributor::create([
                'company_id' => $company->getKey(),
                'name' => 'Oversell Distributor',
                'contact_email' => 'dist@example.test',
            ]);
            $product = seedEmailProduct('SKU-ROLLBACK', 10.0);

            // Only 5 on hand; the order demands 9 → oversell with backorders off
            // rolls the whole confirmation back before any post-commit dispatch.
            (new StockMovementService)->apply([
                StockMovement::forOwner($company, $product->id, $companyPoint->id, '5.000', 'opening'),
            ]);

            $order = Order::create([
                'buyer_type' => $distributor->getMorphClass(),
                'buyer_id' => $distributor->getKey(),
                'seller_type' => $company->getMorphClass(),
                'seller_id' => $company->getKey(),
                'status' => OrderStatus::PLACED,
                'order_type' => 'standard',
                'total' => '90.00',
            ]);
            OrderLine::create([
                'order_id' => $order->getKey(),
                'product_id' => $product->id,
                'quantity' => '9.000',
                'unit_price' => '10.00',
                'discount' => '0.00',
            ]);

            try {
                makeEmailConfirmationService()->confirm($order->load('lines'));
            } catch (Throwable $e) {
                // expected: insufficient stock, confirmation rolled back
            }

            // Order never left Placed → no confirmation committed → no email.
            expect($order->fresh()->status)->toBe(OrderStatus::PLACED);
            Mail::assertNotQueued(OrderConfirmedMail::class);
        });
    } finally {
        teardownEmailTenants($registry);
    }
});

/*
|--------------------------------------------------------------------------
| R27.4 — payment-recorded on payment recording
|--------------------------------------------------------------------------
*/

it('queues (not sends) a payment-recorded email after a payment is recorded (R27.4)', function () {
    $registry = [];
    $tenant = provisionEmailTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::query()->firstOrFail();
            $buyer = Distributor::create([
                'company_id' => $company->getKey(),
                'name' => 'Paying Distributor',
                'contact_email' => 'buyer@example.test',
            ]);

            makeEmailPaymentService()->record($buyer, [
                'amount' => '150.00',
                'method' => 'bank_transfer',
                'reference' => 'TXN-9',
                'paid_at' => '2026-01-15',
                'recorded_by' => 7,
            ]);

            // Queued to the buyer, carrying the recorded amount (R27.4).
            Mail::assertQueued(PaymentRecordedMail::class, function (PaymentRecordedMail $mail) {
                return $mail->hasTo('buyer@example.test')
                    && $mail->amount === '150.00'
                    && $mail instanceof ShouldQueue;
            });

            Mail::assertQueued(PaymentRecordedMail::class, 1);
            Mail::assertNotSent(PaymentRecordedMail::class);
        });
    } finally {
        teardownEmailTenants($registry);
    }
});

it('queues NO payment-recorded email when the payment aborts before commit (post-commit dispatch, R27.4)', function () {
    $registry = [];
    $tenant = provisionEmailTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::query()->firstOrFail();
            $buyer = Distributor::create([
                'company_id' => $company->getKey(),
                'name' => 'Over-allocating Distributor',
                'contact_email' => 'buyer@example.test',
            ]);

            // Allocations (60 + 60 = 120) exceed the payment amount (100) → the
            // payment is rejected before any ledger write commits (R24.3).
            try {
                makeEmailPaymentService()->record($buyer, [
                    'amount' => '100.00',
                    'method' => 'cash',
                    'allocations' => [
                        ['invoice_id' => 1, 'amount' => '60.00'],
                        ['invoice_id' => 2, 'amount' => '60.00'],
                    ],
                ]);
            } catch (UnprocessableEntityHttpException $e) {
                // expected: allocation ceiling violated, nothing committed
            }

            Mail::assertNotQueued(PaymentRecordedMail::class);
        });
    } finally {
        teardownEmailTenants($registry);
    }
});

/*
|--------------------------------------------------------------------------
| Cross-cutting: the four side-effects are independent
|--------------------------------------------------------------------------
*/

it('does not cross-fire: a cart submission queues only the order-placed email (R27.1–R27.4)', function () {
    $registry = [];
    $tenant = provisionEmailTenant($registry);

    try {
        $tenant->run(function () {
            $distributor = app(DistributorService::class)->create([
                'name' => 'Lone Event Distributor',
                'contact_email' => 'buyer@example.test',
            ]);
            $product = seedEmailProduct('SKU-LONE', 15.0);

            app(CartSubmissionService::class)->submit($distributor, [
                ['product_id' => $product->id, 'quantity' => '1'],
            ]);

            Mail::assertQueued(OrderPlacedMail::class, 1);
            Mail::assertNotQueued(AccountCreatedMail::class);
            Mail::assertNotQueued(OrderConfirmedMail::class);
            Mail::assertNotQueued(PaymentRecordedMail::class);
        });
    } finally {
        teardownEmailTenants($registry);
    }
});
