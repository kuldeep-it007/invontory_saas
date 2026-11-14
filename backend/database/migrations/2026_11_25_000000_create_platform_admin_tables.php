<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central database: Platform Admin console storage.
 *
 * - `users.phone`          optional contact number on the Platform_Admin profile.
 * - `platform_settings`    key/value platform configuration (name, branding,
 *                          contact details, logo/favicon URLs).
 * - `platform_audit_logs`  append-only record of platform-level actions: tenant
 *                          edits, plan/subscription changes, settings and profile
 *                          changes. `tenant_id` is deliberately NOT a foreign key
 *                          so the history survives tenant deletion.
 *
 * Purely additive: no existing column or tenant database is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('phone', 32)->nullable()->after('email');
            });
        }

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_email')->nullable();
            $table->string('event', 64)->index();
            $table->string('tenant_id')->nullable()->index();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audit_logs');
        Schema::dropIfExists('platform_settings');

        if (Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('phone');
            });
        }
    }
};
