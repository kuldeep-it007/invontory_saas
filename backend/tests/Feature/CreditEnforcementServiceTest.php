<?php

declare(strict_types=1);

use App\Exceptions\CreditLimitExceededException;
use App\Exceptions\OverdueBalanceExceededException;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Tenant;
use App\Services\AccountLedgerService;
use App\Services\CreditEnforcementService;
use App\Support\AuditRecorder;
use App\Support\PaymentTerms;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Task 9.14 — Credit & overdue enforcement (DB-gated)
|--------------------------------------------------------------------------
|
| Exercises CreditEnforcementService against a real provisioned tenant
| database:
|   • per-buyer Payment_Terms parsing (R26.1);
|   • credit-limit block iff projected outstanding O + T exceeds limit L,
|     evaluated only at confirmation time (R26.2, Property 24);
|   • overdue invoice flagging past the configured hold days (R26.3);
|   • new-order block iff overdue balance exceeds the overdue balance limit
|     (R26.4, Property 25);
|   • authorized override lifts the block and is recorded in the Audit_Log
|     with its reason (R26.5).
|
| DB-GATED: needs MySQL + CREATE DATABASE for stancl tenancy; skips
| gracefully when MySQL is unreachable, mirroring the invoice/stock suites.
|
| NOTE: top-level helpers are file-uniquely named (…CreditTenant) so Pest can
| load this file alongside the other feature suites without redeclaration.
|
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Provision + migrate an isolated tenant for credit-enforcement tests.
 *
 * @param  array<int, Tenant>  $registry
 */
function provisionCreditTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'cred'.$suffix,
        'subdomain' => 'cred-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'cred-'.$suffix.'.example.test']);
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
function teardownCreditTenants(array $registry): void
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
 * A Distributor buyer carrying the given Payment_Terms.
 *
 * @param  array<string, mixed>  $terms
 */
function makeCreditBuyer(array $terms = []): Distributor
{
    $company = Company::query()->firstOrCreate(['name' => 'Acme']);

    return Distributor::create([
        'company_id' => $company->id,
        'name' => 'Dist '.bin2hex(random_bytes(2)),
        'payment_terms' => $terms,
    ]);
}

/**
 * Build the service wired to the real AccountLedgerService + AuditRecorder.
 */
function makeCreditService(): CreditEnforcementService
{
    return new CreditEnforcementService(new AccountLedgerService, new AuditRecorder);
}

/**
 * Create an invoice for a buyer with a controllable issue date and amount,
 * routed through an Order so the service can resolve it by buyer.
 */
function makeCreditInvoice(Distributor $buyer, string $amount, Carbon $issuedAt): Invoice
{
    $company = Company::query()->firstOrFail();

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
        'number' => Invoice::max('number') + 1,
        'amount' => $amount,
        'overdue' => false,
    ]);
    $invoice->seller()->associate($company);
    $invoice->save();

    // Backdate the issue timestamp to control invoice age for overdue tests.
    $invoice->forceFill(['created_at' => $issuedAt])->saveQuietly();

    return $invoice->fresh();
}

// -------- R26.1: Payment_Terms parsing --------

it('parses per-buyer payment terms into typed fields', function () {
    $terms = PaymentTerms::fromArray([
        'credit_limit' => '1000',
        'overdue_balance_limit' => '250.5',
        'overdue_hold_days' => '30',
        'payment_type' => 'credit',
    ]);

    expect($terms->creditLimit)->toBe('1000.00');
    expect($terms->overdueBalanceLimit)->toBe('250.50');
    expect($terms->overdueHoldDays)->toBe(30);
    expect($terms->paymentType)->toBe('credit');
    expect($terms->hasCreditLimit())->toBeTrue();
    expect($terms->hasOverdueBalanceLimit())->toBeTrue();
    expect($terms->hasOverdueHoldDays())->toBeTrue();
});

it('treats missing or empty payment terms as absent (no limits)', function () {
    $terms = PaymentTerms::fromArray([]);

    expect($terms->creditLimit)->toBeNull();
    expect($terms->overdueBalanceLimit)->toBeNull();
    expect($terms->overdueHoldDays)->toBeNull();
    expect($terms->paymentType)->toBeNull();
    expect($terms->hasCreditLimit())->toBeFalse();
});

// -------- R26.2: credit-limit enforcement at confirmation --------

it('allows confirmation when projected outstanding is within the credit limit', function () {
    $registry = [];
    $tenant = provisionCreditTenant($registry);

    try {
        $tenant->run(function () {
            $buyer = makeCreditBuyer(['credit_limit' => '1000.00']);
            $service = makeCreditService();

            // Current outstanding 600; order 400 → projected 1000 == limit (allowed).
            (new AccountLedgerService)->appendPurchase($buyer, '600.00');

            $service->assertCreditWithinLimit($buyer, '400.00');

            expect(true)->toBeTrue(); // reached without throwing
        });
    } finally {
        teardownCreditTenants($registry);
    }
});

it('blocks confirmation when projected outstanding exceeds the credit limit', function () {
    $registry = [];
    $tenant = provisionCreditTenant($registry);

    try {
        $tenant->run(function () {
            $buyer = makeCreditBuyer(['credit_limit' => '1000.00']);
            $service = makeCreditService();

            (new AccountLedgerService)->appendPurchase($buyer, '600.00');

            // Order 400.01 → projected 1000.01 > 1000 limit → blocked.
            expect(fn () => $service->assertCreditWithinLimit($buyer, '400.01'))
                ->toThrow(CreditLimitExceededException::class);
        });
    } finally {
        teardownCreditTenants($registry);
    }
});

it('never blocks on credit when no credit limit is configured', function () {
    $registry = [];
    $tenant = provisionCreditTenant($registry);

    try {
        $tenant->run(function () {
            $buyer = makeCreditBuyer([]); // no terms
            $service = makeCreditService();

            (new AccountLedgerService)->appendPurchase($buyer, '999999.00');

            $service->assertCreditWithinLimit($buyer, '999999.00');
            expect(true)->toBeTrue();
        });
    } finally {
        teardownCreditTenants($registry);
    }
});

// -------- R26.3: overdue invoice flagging --------

it('flags invoices unpaid beyond the overdue hold days and leaves younger ones alone', function () {
    $registry = [];
    $tenant = provisionCreditTenant($registry);

    try {
        $tenant->run(function () {
            $buyer = makeCreditBuyer(['overdue_hold_days' => '30']);
            $service = makeCreditService();

            $old = makeCreditInvoice($buyer, '100.00', Carbon::now()->subDays(40));
            $recent = makeCreditInvoice($buyer, '100.00', Carbon::now()->subDays(10));
            $boundary = makeCreditInvoice($buyer, '100.00', Carbon::now()->subDays(30));

            $changed = $service->flagOverdueInvoices($buyer);

            expect($changed)->toBe(1);
            expect($old->fresh()->overdue)->toBeTrue();
            expect($recent->fresh()->overdue)->toBeFalse();
            // Exactly 30 days old is at the boundary, not strictly beyond it.
            expect($boundary->fresh()->overdue)->toBeFalse();
        });
    } finally {
        teardownCreditTenants($registry);
    }
});

it('does not flag a fully paid invoice as overdue', function () {
    $registry = [];
    $tenant = provisionCreditTenant($registry);

    try {
        $tenant->run(function () {
            $buyer = makeCreditBuyer(['overdue_hold_days' => '30']);
            $service = makeCreditService();

            $invoice = makeCreditInvoice($buyer, '100.00', Carbon::now()->subDays(60));

            $payment = Payment::create([
                'buyer_type' => $buyer->getMorphClass(),
                'buyer_id' => $buyer->getKey(),
                'amount' => '100.00',
                'method' => 'cash',
                'paid_at' => Carbon::now()->toDateString(),
            ]);
            PaymentAllocation::create([
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount' => '100.00',
            ]);

            $changed = $service->flagOverdueInvoices($buyer);

            expect($changed)->toBe(0);
            expect($invoice->fresh()->overdue)->toBeFalse();
        });
    } finally {
        teardownCreditTenants($registry);
    }
});

// -------- R26.4: overdue balance blocks new orders --------

it('blocks a new order when the overdue balance exceeds the limit', function () {
    $registry = [];
    $tenant = provisionCreditTenant($registry);

    try {
        $tenant->run(function () {
            $buyer = makeCreditBuyer([
                'overdue_hold_days' => '30',
                'overdue_balance_limit' => '150.00',
            ]);
            $service = makeCreditService();

            // Two overdue invoices totalling 200 > 150 limit.
            makeCreditInvoice($buyer, '100.00', Carbon::now()->subDays(45));
            makeCreditInvoice($buyer, '100.00', Carbon::now()->subDays(45));
            $service->flagOverdueInvoices($buyer);

            expect((string) $service->overdueBalance($buyer))->toBe('200.00');

            expect(fn () => $service->assertNotOverdueBlocked($buyer))
                ->toThrow(OverdueBalanceExceededException::class);
        });
    } finally {
        teardownCreditTenants($registry);
    }
});

it('allows a new order when the overdue balance is within the limit', function () {
    $registry = [];
    $tenant = provisionCreditTenant($registry);

    try {
        $tenant->run(function () {
            $buyer = makeCreditBuyer([
                'overdue_hold_days' => '30',
                'overdue_balance_limit' => '150.00',
            ]);
            $service = makeCreditService();

            makeCreditInvoice($buyer, '100.00', Carbon::now()->subDays(45));
            $service->flagOverdueInvoices($buyer);

            $service->assertNotOverdueBlocked($buyer);
            expect(true)->toBeTrue();
        });
    } finally {
        teardownCreditTenants($registry);
    }
});

// -------- R26.5: authorized override --------

it('permits a blocked confirmation with an authorized override and audits the reason', function () {
    $registry = [];
    $tenant = provisionCreditTenant($registry);

    try {
        $tenant->run(function () {
            $buyer = makeCreditBuyer(['credit_limit' => '1000.00']);
            $service = makeCreditService();

            (new AccountLedgerService)->appendPurchase($buyer, '1000.00');

            // Projected 1500 > 1000 would block, but an override lifts it.
            $service->assertCreditWithinLimit($buyer, '500.00', 'Approved by finance for Q4 push');

            $audit = AuditLog::where('event', 'credit_limit_override')->latest('id')->first();
            expect($audit)->not->toBeNull();
            expect($audit->reason)->toBe('Approved by finance for Q4 push');
        });
    } finally {
        teardownCreditTenants($registry);
    }
});

it('permits a blocked new order with an authorized overdue override and audits it', function () {
    $registry = [];
    $tenant = provisionCreditTenant($registry);

    try {
        $tenant->run(function () {
            $buyer = makeCreditBuyer([
                'overdue_hold_days' => '30',
                'overdue_balance_limit' => '150.00',
            ]);
            $service = makeCreditService();

            makeCreditInvoice($buyer, '200.00', Carbon::now()->subDays(45));
            $service->flagOverdueInvoices($buyer);

            $service->assertNotOverdueBlocked($buyer, 'Temporary hold waiver');

            $audit = AuditLog::where('event', 'overdue_block_override')->latest('id')->first();
            expect($audit)->not->toBeNull();
            expect($audit->reason)->toBe('Temporary hold waiver');
        });
    } finally {
        teardownCreditTenants($registry);
    }
});

it('ignores a blank override reason so the block stays in force', function () {
    $registry = [];
    $tenant = provisionCreditTenant($registry);

    try {
        $tenant->run(function () {
            $buyer = makeCreditBuyer(['credit_limit' => '100.00']);
            $service = makeCreditService();

            (new AccountLedgerService)->appendPurchase($buyer, '100.00');

            expect(fn () => $service->assertCreditWithinLimit($buyer, '50.00', '   '))
                ->toThrow(CreditLimitExceededException::class);
        });
    } finally {
        teardownCreditTenants($registry);
    }
});
