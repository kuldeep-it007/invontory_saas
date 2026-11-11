<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `discounts` table.
 *
 * A configured discount of one of exactly two supported types — `customer` or
 * `online` (R12.3). The enum constraint at the database layer (mirrored by the
 * {@see \App\Models\Discount} model and request validation) rejects any other
 * type. Where multiple discount types are simultaneously applicable to the same
 * Buyer and Order_Line, the order-line price resolver applies them cumulatively
 * (R12.5), clamping the result so it is never negative (R12.4).
 *
 * Discount percentages are dimensionless; the resolved monetary result is
 * expressed in the Tenant's single configured currency (R39.1). Isolation is
 * physical, so there is NO `tenant_id` column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();

            // Discount type: `customer` or `online` ONLY (R12.3). The enum is
            // the database-level guard that rejects any other type; the model
            // and validation layer enforce the same allow-list.
            $table->enum('type', ['customer', 'online']);

            // Discount percentage off the (marked-up) base price, e.g. 15.00 =
            // 15% off (R12.5). Applied cumulatively with other applicable
            // discounts by the resolver.
            $table->decimal('percentage', 8, 4);

            // Optional buyer scoping (R12.4): a NULL buyer means the discount is
            // broadly applicable (e.g. an `online` storefront discount), while a
            // set buyer restricts a `customer` discount to that specific Buyer.
            // Polymorphic because a Buyer is a Distributor or a Dealer.
            $table->nullableMorphs('buyer');

            // Whether the discount is currently in effect (R12.5). Inactive
            // discounts are skipped by the resolver.
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // The resolver filters applicable discounts by `type` (customer vs
            // online) and `is_active`; index the pair that drives that WHERE so
            // discount resolution avoids a table scan (R12.5, R38.4). The buyer
            // morph columns are already indexed by nullableMorphs().
            $table->index(['type', 'is_active'], 'discounts_type_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
