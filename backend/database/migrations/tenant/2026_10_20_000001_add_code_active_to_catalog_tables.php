<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rosetta-style catalog: add a human `code` identifier and an `active` flag to
 * every level of the Category → Collection → Colour hierarchy (R11.2).
 *
 * Rosetta keys catalog levels by a short string `code` (e.g. "CAT01") plus a
 * display name, and toggles visibility with an `active` boolean. We keep the
 * numeric surrogate `id` as the primary key (so existing foreign keys and the
 * physical-isolation model are untouched) and ADD:
 *   - `code`   — a tenant-unique short identifier, matched case-insensitively
 *                in the app layer; back-filled from the id for existing rows.
 *   - `active` — whether the level is shown in catalog browse (default true).
 *
 * Tenant-owned tables (no `tenant_id` column — isolation is physical). The
 * `code` uniqueness is per-tenant automatically because each tenant has its own
 * database.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['categories', 'collections', 'colours'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) use ($table): void {
                if (! Schema::hasColumn($table, 'code')) {
                    // Nullable first so we can back-fill existing rows before
                    // enforcing uniqueness.
                    $t->string('code', 64)->nullable()->after('id');
                }
                if (! Schema::hasColumn($table, 'active')) {
                    $t->boolean('active')->default(true)->after('name');
                }
            });

            // Back-fill a deterministic code for any existing rows so the unique
            // index can be added without collisions.
            DB::table($table)->whereNull('code')->orderBy('id')->get(['id'])
                ->each(function ($row) use ($table): void {
                    $prefix = strtoupper(substr($table, 0, 3)); // CAT / COL / COL
                    DB::table($table)->where('id', $row->id)->update([
                        'code' => $prefix.'-'.$row->id,
                    ]);
                });

            Schema::table($table, function (Blueprint $t) use ($table): void {
                // Per-tenant unique code (physical isolation makes it per-tenant).
                $t->unique('code', $table.'_code_unique');
                // active is a common filter in catalog browse.
                $t->index('active', $table.'_active_index');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) use ($table): void {
                if (Schema::hasColumn($table, 'code')) {
                    $t->dropUnique($table.'_code_unique');
                    $t->dropColumn('code');
                }
                if (Schema::hasColumn($table, 'active')) {
                    $t->dropIndex($table.'_active_index');
                    $t->dropColumn('active');
                }
            });
        }
    }
};
