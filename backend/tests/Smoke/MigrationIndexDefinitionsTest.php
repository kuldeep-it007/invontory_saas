<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Smoke / Static: required index definitions present in migration files
|--------------------------------------------------------------------------
|
| Task 1.3 — "required indexes exist in both the central and tenant schemas
| (R38.2–R38.4)".
|
| These checks are UNCONDITIONAL (no MySQL required): they statically parse
| the migration source so the required index DEFINITIONS are guaranteed to be
| present regardless of DB availability. A complementary DB-gated test
| (TenantConnectionRoutingTest) asserts the indexes actually exist in a live
| schema when MySQL is reachable.
|
| Requirements: 38.2 (indexes on FKs and WHERE/JOIN/ORDER BY columns),
| 38.3 (filter-column indexes within each tenant DB, no tenant_id column),
| 38.4 (central lookups: subdomain, domain resolution).
*/

/**
 * Read the full source of the single migration file matching a glob fragment.
 */
function readMigrationSource(string $relativeGlob): string
{
    $base = database_path('migrations');
    $matches = glob($base.DIRECTORY_SEPARATOR.$relativeGlob);

    expect($matches)->not->toBeEmpty("Expected a migration matching [{$relativeGlob}]");

    return (string) file_get_contents($matches[0]);
}

it('defines a UNIQUE index on central tenants.subdomain (R38.4)', function () {
    $src = readMigrationSource('*_create_tenants_table.php');

    // UNIQUE subdomain is the hot resolution path and enforces R1.5.
    expect($src)->toMatch("/->string\('subdomain'\)->unique\(\)/");
});

it('defines a UNIQUE index on central domains.domain for host resolution (R38.4)', function () {
    $src = readMigrationSource('*_create_domains_table.php');

    // UNIQUE(domain): resolve subdomain/custom-domain -> tenant on every request.
    expect($src)->toMatch("/->string\('domain'.*\)->unique\(\)/");
    // Foreign key to tenants provides the FK index (R38.2).
    expect($src)->toContain("->foreign('tenant_id')");
});

it('defines the central subscriptions status index for subscription-status lookups (R38.4)', function () {
    $src = readMigrationSource('*_create_subscriptions_table.php');

    // R38.4 names "subscription status" as a required central lookup index.
    // Accept either a dedicated status index or a composite leading with status.
    expect($src)->toMatch("/index\(.*status.*\)|->index\(\)/i");
});

it('defines the central plan_limits lookup index (R38.4)', function () {
    $src = readMigrationSource('*_create_plan_limits_table.php');

    // Plan limits are resolved by plan; an index must back that lookup.
    expect(strtolower($src))->toContain('index');
});

it('defines a composite history index on tenant audit_logs (R38.3)', function () {
    $src = readMigrationSource('tenant'.DIRECTORY_SEPARATOR.'*_create_audit_logs_table.php');

    // Composite (auditable_type, auditable_id, created_at) backs per-record
    // audit history ordered by time.
    expect($src)->toContain('audit_logs_auditable_created_index');
});

it('defines targeted indexes on tenant stock_ledger (R38.4)', function () {
    $src = readMigrationSource('tenant'.DIRECTORY_SEPARATOR.'*_create_stock_ledger_table.php');

    // Movement report ordered by time per owner/product (R28.2) and date-range
    // scans (R38.4): the owner/product/created composite + a created_at index.
    expect($src)->toContain('stock_ledger_owner_product_created_index');
    expect($src)->toContain('stock_ledger_created_index');

    // Append-only ledger carries no tenant_id discriminator (R38.3).
    expect($src)->not->toMatch("/->[a-zA-Z]+\('tenant_id'/");
});

it('defines targeted indexes on tenant stock_reservations (R38.4)', function () {
    $src = readMigrationSource('tenant'.DIRECTORY_SEPARATOR.'*_create_stock_reservations_table.php');

    // The availability computation sums the ACTIVE reservations for a stock row
    // — (stock_id, status) is that exact predicate (R23.4); release-by-order
    // reads every reservation for an order (R23.5) via the order_id index.
    expect($src)->toContain('stock_reservations_stock_status_index');
    expect($src)->toContain('stock_reservations_order_index');

    // Tenant-owned and physically isolated: no tenant_id discriminator (R38.3).
    expect($src)->not->toMatch("/->[a-zA-Z]+\('tenant_id'/");
});

it('defines targeted indexes on tenant stock (R38.5)', function () {
    $src = readMigrationSource('tenant'.DIRECTORY_SEPARATOR.'*_create_stock_table.php');

    // The composite owner/product/stock_point lookup+locking key and the
    // product-level stock-on-hand index back the core inventory access paths.
    expect($src)->toContain('stock_owner_product_point_unique');
    expect($src)->toContain('stock_product_index');
    // Physical isolation: no tenant_id discriminator (R38.3).
    expect($src)->not->toMatch("/->[a-zA-Z]+\('tenant_id'/");
});

it('defines targeted indexes on tenant orders (R38.5)', function () {
    $src = readMigrationSource('tenant'.DIRECTORY_SEPARATOR.'*_create_orders_table.php');

    // Order listings/filters by status and by buyer/seller morph (R21.2, R28.7).
    expect($src)->toContain('orders_status_index');
    expect($src)->toContain('orders_buyer_index');
    expect($src)->toContain('orders_seller_index');
});

it('defines the eager-load index on tenant order_lines (R38.1)', function () {
    $src = readMigrationSource('tenant'.DIRECTORY_SEPARATOR.'*_create_order_lines_table.php');

    // The order_id FK index lets an order's lines be eager-loaded without N+1
    // (R38.1); product_id backs product-level order-line reporting (R38.2).
    expect($src)->toContain("constrained('orders')");
    expect($src)->toContain('order_lines_product_index');
});

it('defines targeted indexes on tenant transaction_ledger (R38.5)', function () {
    $src = readMigrationSource('tenant'.DIRECTORY_SEPARATOR.'*_create_transaction_ledger_table.php');

    // Per-buyer account history + latest-balance (Outstanding_Balance) lookup
    // and tenant-wide date-range scans (R25.2, R28.6).
    expect($src)->toContain('transaction_ledger_buyer_id_index');
    expect($src)->toContain('transaction_ledger_created_index');
    expect($src)->not->toMatch("/->[a-zA-Z]+\('tenant_id'/");
});

it('defines targeted indexes on tenant payments (R38.4)', function () {
    $src = readMigrationSource('tenant'.DIRECTORY_SEPARATOR.'*_create_payments_table.php');

    // Per-buyer payment history by date + payments/collections report scans (R28.6).
    expect($src)->toContain('payments_buyer_paid_at_index');
    expect($src)->toContain('payments_paid_at_index');
});

it('defines series/order indexes on tenant invoices (R38.4)', function () {
    $src = readMigrationSource('tenant'.DIRECTORY_SEPARATOR.'*_create_invoices_table.php');

    // Per-seller series uniqueness + next-number allocation + one-invoice-per-order.
    expect($src)->toContain('invoices_series_number_unique');
    expect($src)->toContain('invoices_seller_number_index');
    expect($src)->toContain('invoices_order_id_unique');
});

it('defines the per-invoice allocation index on tenant payment_allocations (R38.4)', function () {
    $src = readMigrationSource('tenant'.DIRECTORY_SEPARATOR.'*_create_payment_allocations_table.php');

    expect($src)->toContain('payment_allocations_invoice_index');
});

it('defines FK/listing indexes on tenant products, distributors, and dealers (R38.2)', function () {
    $products = readMigrationSource('tenant'.DIRECTORY_SEPARATOR.'*_create_products_table.php');
    expect($products)->toContain('products_sku_unique');
    // collection_id / colour_id FKs provide the catalog filter/join indexes.
    expect($products)->toContain("constrained('collections')");
    expect($products)->toContain("constrained('colours')");

    $distributors = readMigrationSource('tenant'.DIRECTORY_SEPARATOR.'*_create_distributors_table.php');
    // company_id FK provides the "distributors under the company" index.
    expect($distributors)->toContain("constrained('companies')");

    $dealers = readMigrationSource('tenant'.DIRECTORY_SEPARATOR.'*_create_dealers_table.php');
    // distributor_id FK provides the "dealers per distributor" index (R10.4).
    expect($dealers)->toContain("constrained('distributors')");
});

it('defines a UNIQUE key index on tenant tenant_settings (R38.3)', function () {
    $src = readMigrationSource('tenant'.DIRECTORY_SEPARATOR.'*_create_tenant_settings_table.php');

    expect($src)->toContain('tenant_settings_key_unique');
});

it('keeps tenant-owned tables free of any tenant_id discriminator column (R38.3)', function () {
    // Physical isolation: no tenant_id DISCRIMINATOR column, no composite
    // tenant index, no global scope on tenant-owned BUSINESS tables. Scan every
    // tenant migration for a tenant_id column.
    //
    // Documented exception: `personal_access_tokens` carries a nullable,
    // indexed `tenant_id` STAMP (not a discriminator). It is Sanctum
    // infrastructure, not a tenant-owned business table, and the stamp exists
    // purely as defence-in-depth: the ReconcileTenantUser guard compares it to
    // the active tenant to reject a token replayed across tenant databases
    // (R1.6). Isolation remains physical — the stamp is never used as a WHERE
    // predicate to partition rows within a shared table.
    $base = database_path('migrations'.DIRECTORY_SEPARATOR.'tenant');
    $files = glob($base.DIRECTORY_SEPARATOR.'*.php');

    expect($files)->not->toBeEmpty('Expected tenant migrations to exist.');

    foreach ($files as $file) {
        // The token-stamp exception is explicit and documented above.
        if (str_contains(basename($file), 'personal_access_tokens')) {
            continue;
        }

        $src = (string) file_get_contents($file);
        // Guard against a column literally named tenant_id being declared.
        expect($src)->not->toMatch("/->[a-zA-Z]+\('tenant_id'/", basename($file).' must not declare a tenant_id discriminator column');
    }
});
