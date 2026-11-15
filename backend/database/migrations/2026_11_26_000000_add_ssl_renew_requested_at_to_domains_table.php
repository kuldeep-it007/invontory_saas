<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central database: lets a Platform_Admin queue a forced certificate renewal
 * for a custom domain. The host certificate script picks it up on its next
 * run and clears it when it reports back. Tenants can only see the state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->timestamp('ssl_renew_requested_at')->nullable()->after('ssl_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn('ssl_renew_requested_at');
        });
    }
};
