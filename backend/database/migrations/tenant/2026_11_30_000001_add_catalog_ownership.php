<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 of the multi-distributor catalogue work: catalogue ownership.
 *
 *  - owner_distributor_id: NULL = owned by the Company, otherwise the owning
 *    Distributor. Set server-side from the authenticated user, never from input.
 *  - owner_key: owner_distributor_id ?? 0, kept in sync by the model. It exists
 *    because a UNIQUE index treats NULLs as distinct, so (owner_distributor_id,
 *    sku) would let the Company create the same SKU twice.
 *  - SKU (products) and code (categories, collections, colours) become unique
 *    per owner instead of per tenant.
 *  - discounts get an owner too: the Company's discounts and each
 *    distributor's discounts are managed and applied separately.
 *
 * Existing rows get owner_key 0 (the Company), so nothing changes for them.
 */
return new class extends Migration
{
    /** @var array<string, string> table => column that is unique per owner */
    private array $catalogTables = [
        'categories' => 'code',
        'collections' => 'code',
        'colours' => 'code',
        'products' => 'sku',
    ];

    public function up(): void
    {
        foreach ($this->catalogTables as $table => $column) {
            Schema::table($table, function (Blueprint $t) use ($table, $column): void {
                $t->foreignId('owner_distributor_id')->nullable()->after('id')
                    ->constrained('distributors')->restrictOnDelete();
                $t->unsignedBigInteger('owner_key')->default(0)->after('owner_distributor_id');
                $t->dropUnique("{$table}_{$column}_unique");
                $t->unique(['owner_key', $column], "{$table}_owner_{$column}_unique");
            });
        }

        Schema::table('discounts', function (Blueprint $t): void {
            $t->foreignId('owner_distributor_id')->nullable()->after('id')
                ->constrained('distributors')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('discounts', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('owner_distributor_id');
        });

        foreach ($this->catalogTables as $table => $column) {
            Schema::table($table, function (Blueprint $t) use ($table, $column): void {
                $t->dropUnique("{$table}_owner_{$column}_unique");
                $t->unique($column, "{$table}_{$column}_unique");
                $t->dropConstrainedForeignId('owner_distributor_id');
                $t->dropColumn('owner_key');
            });
        }
    }
};
