<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: extend `distributors` and `dealers` with the full
 * Rosetta-style party profile (R10.1, R10.2).
 *
 * Both Buyer types (Distributor and Dealer) share the same detailed profile —
 * contact (fax), logistics/terms (territory, billing location, shipment method,
 * shipping advice, reserve), GST registration, and Income-Tax (PAN) details.
 * These are captured in a single flexible JSON `profile` column so the profile
 * schema can evolve without further migrations, mirroring how `payment_terms`
 * is stored. The canonical credit limit stays in `payment_terms.credit_limit`
 * (it drives credit checks — R26.1); the form surfaces it alongside the profile.
 *
 * `fax` is promoted to its own nullable column for parity with the existing
 * `contact_phone` column.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['distributors', 'dealers'] as $table) {
            Schema::table($table, function (Blueprint $t): void {
                if (! Schema::hasColumn($t->getTable(), 'fax')) {
                    $t->string('fax')->nullable()->after('contact_phone');
                }
                if (! Schema::hasColumn($t->getTable(), 'profile')) {
                    $t->json('profile')->nullable()->after('gstin');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['distributors', 'dealers'] as $table) {
            Schema::table($table, function (Blueprint $t): void {
                if (Schema::hasColumn($t->getTable(), 'profile')) {
                    $t->dropColumn('profile');
                }
                if (Schema::hasColumn($t->getTable(), 'fax')) {
                    $t->dropColumn('fax');
                }
            });
        }
    }
};
