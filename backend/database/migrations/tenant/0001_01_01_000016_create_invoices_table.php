<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `invoices` table.
 *
 * Exactly one invoice is generated per confirmed Order, synchronously inside
 * the order-confirmation transaction before the response returns (R21.5,
 * design "Confirmation"). Each invoice is issued against the SELLER's own
 * invoice `series`, and the per-series `number` is strictly increasing within
 * that series (R21.5) — allocation is serialized under the confirmation
 * transaction by {@see App\Services\InvoiceService} so numbers never collide
 * or regress under concurrency.
 *
 * The Seller is polymorphic (`seller_type`, `seller_id`): the single tenant
 * Company (for distributor orders) or a Distributor (for dealer orders),
 * mirroring the Order's seller morph. Storing the seller on the invoice lets
 * the service allocate and verify the next number within that seller's series
 * without re-reading the order.
 *
 * Ordered AFTER the catalog/stock tables. The `orders` table is created later
 * in milestone M4 (task 9.1); so the `order_id` foreign key is attached
 * conditionally once `orders` exists, keeping the invoices schema installable
 * on its own (mirrors the guarded product FKs on `stock`/`stock_ledger`).
 * Isolation is physical (one database per tenant), so there is NO `tenant_id`
 * column (R38.3).
 *
 * Targeted indexes (R38.4):
 *   • UNIQUE (`series`, `number`) — guarantees per-seller series uniqueness and
 *     backs the "next number in series" lookup (R21.5, design index catalog).
 *   • (`seller_type`, `seller_id`, `number`) — scopes a seller's own series and
 *     backs MAX(number)/ordering within that series during allocation.
 *   • (`order_id`) — one-invoice-per-order lookup and the FK/JOIN index.
 *   • (`overdue`) is not indexed here; overdue flagging is driven by the
 *     transaction/account layer (task 9.14) and reported from there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // The confirmed Order this invoice was generated for (R21.5). FK is
            // attached conditionally below once the `orders` table exists (task
            // 9.1). One invoice per order — enforced by the unique index below.
            $table->foreignId('order_id');

            // Polymorphic Seller (seller_type, seller_id): the Company or the
            // Distributor whose invoice series this invoice belongs to (R21.5).
            // Declared as explicit columns (NOT morphs()) so no standalone
            // (seller_type, seller_id) index is created — those columns are the
            // leading columns of the wider series index defined below, which
            // already serves seller-only and seller+number prefix scans. Avoids
            // a redundant duplicate index on the same leading columns (R38.4).
            $table->string('seller_type');
            $table->unsignedBigInteger('seller_id');

            // The seller's invoice series identifier (R21.5). A seller may run
            // one or more named series; numbers are strictly increasing WITHIN
            // each series. Stored as a string so series are human-meaningful
            // (e.g. "FY25-COMPANY", "DIST-7") without a schema change.
            $table->string('series');

            // The strictly-increasing per-series sequence number (R21.5).
            // Allocated as MAX(number for this series) + 1 under the
            // confirmation transaction so it never regresses or collides.
            $table->unsignedBigInteger('number');

            // The invoice total (the confirmed order total). DECIMAL(15,2) for
            // monetary amounts in the tenant's single currency (R39.1).
            $table->decimal('amount', 15, 2);

            // Whether the invoice is currently flagged overdue (R26.3). Set by
            // the overdue-enforcement layer (task 9.14); defaults to not overdue
            // at generation time.
            $table->boolean('overdue')->default(false);

            $table->timestamps();

            // (series, number): per-seller series uniqueness + the "next number
            // in series" lookup. UNIQUE because a (series, number) pair is the
            // invoice's human identifier and must never repeat (R21.5).
            $table->unique(['series', 'number'], 'invoices_series_number_unique');

            // (seller_type, seller_id, number): scope a seller's own series and
            // back MAX(number)/ordering during allocation; also serves
            // seller-only prefix scans, so no separate seller morph index is
            // needed (R21.5, R38.4).
            $table->index(
                ['seller_type', 'seller_id', 'number'],
                'invoices_seller_number_index'
            );

            // (order_id): one-invoice-per-order lookup + FK/JOIN index. UNIQUE
            // because exactly one invoice is generated per confirmed order
            // (R21.5, Property 11).
            $table->unique('order_id', 'invoices_order_id_unique');
        });

        // Attach the order foreign key only when the `orders` table is already
        // present (task 9.1). The guard keeps the invoices schema installable on
        // its own while still enforcing referential integrity once orders exist.
        if (Schema::hasTable('orders')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreign('order_id', 'invoices_order_id_foreign')
                    ->references('id')->on('orders')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
