<?php

declare(strict_types=1);

use App\Exceptions\ImmutableLedgerException;
use App\Mail\PaymentRecordedMail;
use App\Models\Company;
use App\Models\Distributor;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Tenant;
use App\Models\TransactionLedger;
use App\Services\AccountLedgerService;
use App\Services\PaymentService;
use App\Services\WhiteLabelService;
use App\Support\AuditRecorder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/*
|--------------------------------------------------------------------------
| Task 9.10 — Manual payments & immutable transaction ledger (DB-gated)
|--------------------------------------------------------------------------
|
| Exercises the payments/accounts domain against a real provisioned tenant
| database:
|   • payments / payment_allocations / transaction_ledger migrate without a
|     tenant_id discriminator (R38.3, R38.4);
|   • PaymentService.record persists a payment (amount/method/reference/date/
|     recorded-by), appends a transaction_ledger entry with
|     balance_after = previous + signed amount, and queues a payment-recorded
|     email (R24.1, R24.2, R24.4);
|   • partial payments and allocation to one or more invoices, with the sum of
|     allocations never exceeding the payment amount (R24.3);
|   • Outstanding_Balance equals the latest balance_after (R25.1, R25.2);
|   • the transaction ledger rejects update and delete (R25.3).
|
| DB-GATED: needs MySQL + CREATE DATABASE for stancl tenancy; skips gracefully
| when MySQL is unreachable, mirroring StockMovementServiceTest.
|
*/

beforeEach(function () {
    skip_without_mysql('central');
    Mail::fake();
});

/**
 * Provision + migrate an isolated tenant for payment tests.
 *
 * @param  array<int, Tenant>  $registry
 */
function provisionPaymentTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'pay'.$suffix,
        'subdomain' => 'pay-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'pay-'.$suffix.'.example.test']);
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
function teardownPaymentTenants(array $registry): void
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
 * Build a PaymentService wired to real collaborators.
 */
function makePaymentService(): PaymentService
{
    return new PaymentService(
        new AccountLedgerService,
        new WhiteLabelService(new AuditRecorder),
    );
}

/**
 * Seed a Distributor buyer inside the active tenant database.
 */
function seedPaymentBuyer(string $email = 'buyer@example.test'): Distributor
{
    $company = Company::create(['name' => 'Acme']);

    return Distributor::create([
        'company_id' => $company->getKey(),
        'name' => 'Dist One',
        'contact_email' => $email,
    ]);
}

/**
 * Seed an invoice row inside the active tenant database so payment allocations
 * reference a real invoice (the `invoices` table — task 9.8 — enforces the FK).
 * `order_id` is unique but has no FK while the `orders` table (task 9.1) is
 * absent, so an arbitrary unique id is sufficient here.
 */
function seedPaymentInvoice(Company $seller, string $series, int $number, string $amount): int
{
    $now = now();

    return (int) DB::table('invoices')->insertGetId([
        'order_id' => $number, // unique synthetic id (no orders table yet)
        'seller_type' => $seller->getMorphClass(),
        'seller_id' => $seller->getKey(),
        'series' => $series,
        'number' => $number,
        'amount' => $amount,
        'overdue' => false,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

it('migrates payments, payment_allocations and transaction_ledger without a tenant_id discriminator', function () {
    $registry = [];
    $tenant = provisionPaymentTenant($registry);

    try {
        $tenant->run(function () {
            expect(Schema::hasTable('payments'))->toBeTrue();
            expect(Schema::hasTable('payment_allocations'))->toBeTrue();
            expect(Schema::hasTable('transaction_ledger'))->toBeTrue();

            expect(Schema::hasColumns('payments', [
                'id', 'buyer_type', 'buyer_id', 'amount', 'method', 'reference', 'paid_at', 'recorded_by',
            ]))->toBeTrue();
            expect(Schema::hasColumns('payment_allocations', [
                'id', 'payment_id', 'invoice_id', 'amount',
            ]))->toBeTrue();
            expect(Schema::hasColumns('transaction_ledger', [
                'id', 'buyer_type', 'buyer_id', 'entry_type', 'amount', 'balance_after', 'payment_id', 'reference', 'actor_id',
            ]))->toBeTrue();

            expect(Schema::hasColumn('payments', 'tenant_id'))->toBeFalse();
            expect(Schema::hasColumn('payment_allocations', 'tenant_id'))->toBeFalse();
            expect(Schema::hasColumn('transaction_ledger', 'tenant_id'))->toBeFalse();
        });
    } finally {
        teardownPaymentTenants($registry);
    }
});

it('records a payment with a ledger entry and queues a payment-recorded email', function () {
    $registry = [];
    $tenant = provisionPaymentTenant($registry);

    try {
        $tenant->run(function () {
            $buyer = seedPaymentBuyer();
            $service = makePaymentService();

            $payment = $service->record($buyer, [
                'amount' => '150.00',
                'method' => 'bank_transfer',
                'reference' => 'TXN-9',
                'paid_at' => '2026-01-15',
                'recorded_by' => 7,
            ]);

            expect($payment)->toBeInstanceOf(Payment::class);
            expect((string) $payment->amount)->toBe('150.00');
            expect($payment->method)->toBe('bank_transfer');
            expect($payment->reference)->toBe('TXN-9');
            expect($payment->paid_at->toDateString())->toBe('2026-01-15');
            expect($payment->recorded_by)->toBe(7);

            // One transaction_ledger entry appended for the payment (R24.2),
            // recorded as a negative amount reducing the balance.
            $entry = TransactionLedger::firstOrFail();
            expect($entry->entry_type)->toBe(TransactionLedger::ENTRY_PAYMENT);
            expect((string) $entry->amount)->toBe('-150.00');
            expect((string) $entry->balance_after)->toBe('-150.00');
            expect($entry->payment_id)->toBe((int) $payment->getKey());

            // Payment-recorded email queued (R24.4).
            Mail::assertQueued(PaymentRecordedMail::class, function (PaymentRecordedMail $mail) use ($buyer) {
                return $mail->hasTo($buyer->contact_email)
                    && $mail->amount === '150.00'
                    && $mail->outstandingBalance === '-150.00';
            });
        });
    } finally {
        teardownPaymentTenants($registry);
    }
});

it('chains balance_after as previous + signed amount across purchases and payments', function () {
    $registry = [];
    $tenant = provisionPaymentTenant($registry);

    try {
        $tenant->run(function () {
            $buyer = seedPaymentBuyer();
            $ledger = new AccountLedgerService;
            $service = makePaymentService();

            // A confirmed purchase charges the buyer (+1000).
            $ledger->appendPurchase($buyer, '1000.00', ['reference' => 'INV-1']);
            expect($ledger->outstandingBalance($buyer))->toBe('1000.00');

            // A partial payment reduces it (-400).
            $service->record($buyer, ['amount' => '400.00', 'method' => 'cash']);
            expect($ledger->outstandingBalance($buyer))->toBe('600.00');

            // Another payment (-250).
            $service->record($buyer, ['amount' => '250.00', 'method' => 'upi']);
            expect($ledger->outstandingBalance($buyer))->toBe('350.00');

            $balances = TransactionLedger::orderBy('id')->pluck('balance_after')
                ->map(fn ($b) => (string) $b)->all();
            expect($balances)->toBe(['1000.00', '600.00', '350.00']);

            // Outstanding_Balance equals the latest balance_after (R25.2).
            expect($ledger->outstandingBalance($buyer))->toBe((string) TransactionLedger::orderByDesc('id')->first()->balance_after);
        });
    } finally {
        teardownPaymentTenants($registry);
    }
});

it('allocates a payment to one or more invoices with the sum within the amount', function () {
    $registry = [];
    $tenant = provisionPaymentTenant($registry);

    try {
        $tenant->run(function () {
            $buyer = seedPaymentBuyer();
            $seller = Company::firstOrFail();
            $invoiceA = seedPaymentInvoice($seller, 'FY26', 1, '300.00');
            $invoiceB = seedPaymentInvoice($seller, 'FY26', 2, '150.00');
            $service = makePaymentService();

            $payment = $service->record($buyer, [
                'amount' => '500.00',
                'method' => 'cheque',
                'allocations' => [
                    ['invoice_id' => $invoiceA, 'amount' => '300.00'],
                    ['invoice_id' => $invoiceB, 'amount' => '150.00'],
                ],
            ]);

            $allocations = PaymentAllocation::where('payment_id', $payment->getKey())
                ->orderBy('invoice_id')->get();

            expect($allocations)->toHaveCount(2);
            expect((string) $allocations[0]->amount)->toBe('300.00');
            expect((string) $allocations[1]->amount)->toBe('150.00');

            // Sum of allocations (450) <= payment amount (500) (R24.3).
            $sum = $allocations->sum(fn ($a) => (float) $a->amount);
            expect($sum)->toBeLessThanOrEqual(500.0);
        });
    } finally {
        teardownPaymentTenants($registry);
    }
});

it('rejects allocations whose sum exceeds the payment amount (R24.3)', function () {
    $registry = [];
    $tenant = provisionPaymentTenant($registry);

    try {
        $tenant->run(function () {
            $buyer = seedPaymentBuyer();
            $service = makePaymentService();

            $call = fn () => $service->record($buyer, [
                'amount' => '100.00',
                'method' => 'cash',
                'allocations' => [
                    ['invoice_id' => 1, 'amount' => '60.00'],
                    ['invoice_id' => 2, 'amount' => '60.00'],
                ],
            ]);

            expect($call)->toThrow(UnprocessableEntityHttpException::class);

            // All-or-nothing: nothing persisted when the ceiling is violated.
            expect(Payment::count())->toBe(0);
            expect(PaymentAllocation::count())->toBe(0);
            expect(TransactionLedger::count())->toBe(0);
        });
    } finally {
        teardownPaymentTenants($registry);
    }
});

it('rejects a non-positive payment amount', function () {
    $registry = [];
    $tenant = provisionPaymentTenant($registry);

    try {
        $tenant->run(function () {
            $buyer = seedPaymentBuyer();
            $service = makePaymentService();

            expect(fn () => $service->record($buyer, ['amount' => '0.00', 'method' => 'cash']))
                ->toThrow(UnprocessableEntityHttpException::class);
            expect(fn () => $service->record($buyer, ['amount' => '-5.00', 'method' => 'cash']))
                ->toThrow(UnprocessableEntityHttpException::class);

            expect(Payment::count())->toBe(0);
        });
    } finally {
        teardownPaymentTenants($registry);
    }
});

it('rejects updates and deletes of an existing transaction_ledger entry (immutable, R25.3)', function () {
    $registry = [];
    $tenant = provisionPaymentTenant($registry);

    try {
        $tenant->run(function () {
            $buyer = seedPaymentBuyer();
            (new AccountLedgerService)->appendPurchase($buyer, '75.00');

            $entry = TransactionLedger::firstOrFail();

            expect(function () use ($entry) {
                $entry->amount = '999.00';
                $entry->save();
            })->toThrow(ImmutableLedgerException::class);

            expect(fn () => $entry->fresh()->delete())->toThrow(ImmutableLedgerException::class);

            // The stored entry is unchanged (R25.3).
            expect((string) TransactionLedger::firstOrFail()->amount)->toBe('75.00');
        });
    } finally {
        teardownPaymentTenants($registry);
    }
});
