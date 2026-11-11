<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Task 9.8 — Per-seller invoice generation and series (DB-gated)
|--------------------------------------------------------------------------
|
| Exercises InvoiceService::generate against a real provisioned tenant
| database:
|   • exactly one invoice per confirmed order, keyed to the order (R21.5);
|   • the invoice uses the SELLER's own series and stores the seller morph;
|   • numbers are strictly increasing WITHIN each series, independently per
|     series and per seller (R21.5, Property 11);
|   • allocation runs inside the caller's confirmation transaction (FOR UPDATE
|     series lock) and participates in its roll-back.
|
| DB-GATED: needs MySQL + CREATE DATABASE for stancl tenancy; skips gracefully
| when MySQL is unreachable, mirroring the stock-movement suite.
|
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Provision + migrate an isolated tenant for invoice tests.
 *
 * @param  array<int, Tenant>  $registry
 */
function provisionInvoiceTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'inv'.$suffix,
        'subdomain' => 'inv-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'inv-'.$suffix.'.example.test']);
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
function teardownInvoiceTenants(array $registry): void
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

it('migrates the invoices table with a per-seller series and no tenant_id', function () {
    $registry = [];
    $tenant = provisionInvoiceTenant($registry);

    try {
        $tenant->run(function () {
            expect(Schema::hasTable('invoices'))->toBeTrue();
            expect(Schema::hasColumns('invoices', [
                'id', 'order_id', 'seller_type', 'seller_id',
                'series', 'number', 'amount', 'overdue',
            ]))->toBeTrue();
            expect(Schema::hasColumn('invoices', 'tenant_id'))->toBeFalse();
        });
    } finally {
        teardownInvoiceTenants($registry);
    }
});

it('generates exactly one invoice for an order using the sellers series', function () {
    $registry = [];
    $tenant = provisionInvoiceTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $service = new InvoiceService;

            $invoice = $service->generate(1001, $company, 'COMPANY', '1234.50');

            expect(Invoice::count())->toBe(1);
            expect($invoice->order_id)->toBe(1001);
            expect($invoice->series)->toBe('COMPANY');
            expect($invoice->number)->toBe(1);
            expect((string) $invoice->amount)->toBe('1234.50');
            expect($invoice->overdue)->toBeFalse();
            expect($invoice->seller->is($company))->toBeTrue();
            expect($invoice->seller_type)->toBe(Company::class);
        });
    } finally {
        teardownInvoiceTenants($registry);
    }
});

it('allocates strictly increasing numbers within a single series', function () {
    $registry = [];
    $tenant = provisionInvoiceTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $service = new InvoiceService;

            $first = $service->generate(1, $company, 'FY25', '10.00');
            $second = $service->generate(2, $company, 'FY25', '20.00');
            $third = $service->generate(3, $company, 'FY25', '30.00');

            expect([$first->number, $second->number, $third->number])->toBe([1, 2, 3]);

            // Numbers within the series are strictly increasing and unique.
            $numbers = Invoice::where('series', 'FY25')->orderBy('number')->pluck('number')->all();
            expect($numbers)->toBe([1, 2, 3]);
        });
    } finally {
        teardownInvoiceTenants($registry);
    }
});

it('keeps each series independent so numbers restart per series', function () {
    $registry = [];
    $tenant = provisionInvoiceTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $distributor = Distributor::create([
                'company_id' => $company->id,
                'name' => 'Dist One',
            ]);

            $service = new InvoiceService;

            // Company (seller for distributor orders) runs the COMPANY series.
            $companyInvoice1 = $service->generate(10, $company, 'COMPANY', '100.00');
            $companyInvoice2 = $service->generate(11, $company, 'COMPANY', '200.00');

            // Distributor (seller for dealer orders) runs its own DIST-1 series,
            // whose numbering is independent of the company's series (R21.5).
            $distInvoice1 = $service->generate(12, $distributor, 'DIST-1', '50.00');
            $distInvoice2 = $service->generate(13, $distributor, 'DIST-1', '60.00');

            expect([$companyInvoice1->number, $companyInvoice2->number])->toBe([1, 2]);
            expect([$distInvoice1->number, $distInvoice2->number])->toBe([1, 2]);

            expect($distInvoice1->seller_type)->toBe(Distributor::class);
            expect($distInvoice1->seller->is($distributor))->toBeTrue();
        });
    } finally {
        teardownInvoiceTenants($registry);
    }
});

it('enforces one invoice per order via the unique order index', function () {
    $registry = [];
    $tenant = provisionInvoiceTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $service = new InvoiceService;

            $service->generate(777, $company, 'COMPANY', '10.00');

            // A second invoice for the SAME order violates the unique order_id
            // index — exactly one invoice per confirmed order (R21.5).
            expect(fn () => $service->generate(777, $company, 'COMPANY', '20.00'))
                ->toThrow(Illuminate\Database\QueryException::class);
        });
    } finally {
        teardownInvoiceTenants($registry);
    }
});

it('rolls back the invoice when the enclosing transaction fails', function () {
    $registry = [];
    $tenant = provisionInvoiceTenant($registry);

    try {
        $tenant->run(function () {
            $company = Company::create(['name' => 'Acme']);
            $service = new InvoiceService;

            // Simulate the confirmation transaction aborting AFTER invoice
            // generation: nothing must persist (R35.2, R35.3).
            try {
                DB::connection('tenant')->transaction(function () use ($service, $company) {
                    $service->generate(555, $company, 'COMPANY', '99.00');
                    throw new RuntimeException('confirmation failed after invoice');
                });
            } catch (RuntimeException $e) {
                // expected
            }

            expect(Invoice::count())->toBe(0);
        });
    } finally {
        teardownInvoiceTenants($registry);
    }
});
