<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Central database: custom-domain mapping columns on `domains`.
 *
 * - `type`        platform (provisioned subdomain) | custom (tenant-owned domain)
 * - `status`      pending | active | failed | disabled. Only `active` rows resolve.
 * - `active_key`  equals `domain` while the row is active, NULL otherwise, and is
 *                 UNIQUE. This replaces UNIQUE(domain): several tenants may hold an
 *                 unverified claim on the same host, but only one can be active.
 *                 It stops anyone squatting a domain they don't own.
 *
 * Existing rows are backfilled as active platform domains, so current tenant
 * URLs keep resolving.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->string('type', 16)->default('platform')->after('tenant_id');
            $table->string('status', 16)->default('active')->after('type');
            $table->string('verification_token', 64)->nullable()->after('status');
            $table->boolean('routing_ok')->default(false)->after('verification_token');
            $table->timestamp('verified_at')->nullable()->after('routing_ok');
            $table->timestamp('last_checked_at')->nullable()->after('verified_at');
            $table->timestamp('expires_at')->nullable()->after('last_checked_at');
            $table->unsignedSmallInteger('check_attempts')->default(0)->after('expires_at');
            $table->string('last_error', 255)->nullable()->after('check_attempts');
            $table->string('ssl_status', 16)->default('none')->after('last_error');
            $table->timestamp('ssl_expires_at')->nullable()->after('ssl_status');
            $table->timestamp('ssl_checked_at')->nullable()->after('ssl_expires_at');
            $table->unsignedSmallInteger('ssl_attempts')->default(0)->after('ssl_checked_at');
            $table->boolean('is_primary')->default(false)->after('ssl_attempts');
            $table->string('active_key', 255)->nullable()->after('is_primary');
        });

        DB::table('domains')->update([
            'type' => 'platform',
            'status' => 'active',
            'routing_ok' => true,
            'verified_at' => DB::raw('created_at'),
            'active_key' => DB::raw('domain'),
        ]);

        Schema::table('domains', function (Blueprint $table) {
            $table->dropUnique(['domain']);
            $table->index('domain');
            $table->unique('active_key');
            $table->index(['tenant_id', 'type']);
            $table->index(['status', 'type']);
        });
    }

    public function down(): void
    {
        // Custom rows would violate the restored UNIQUE(domain); keep one per host.
        DB::table('domains')->where('type', 'custom')->delete();

        Schema::table('domains', function (Blueprint $table) {
            $table->dropIndex(['status', 'type']);
            $table->dropIndex(['tenant_id', 'type']);
            $table->dropUnique(['active_key']);
            $table->dropIndex(['domain']);
            $table->unique('domain');
        });

        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn([
                'type', 'status', 'verification_token', 'routing_ok', 'verified_at',
                'last_checked_at', 'expires_at', 'check_attempts', 'last_error',
                'ssl_status', 'ssl_expires_at', 'ssl_checked_at', 'ssl_attempts',
                'is_primary', 'active_key',
            ]);
        });
    }
};
