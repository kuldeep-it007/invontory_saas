<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central database: index of every log file archived to the master bucket, so
 * Platform_Admin can find, search and export them without listing the bucket.
 * Rows are append-only (see App\Models\LogArchive).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_archives', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 16);                 // tenant | platform
            $table->string('tenant_id')->nullable();     // no FK: history outlives the tenant
            $table->string('tenant_slug', 80)->nullable();
            $table->string('kind', 16);                  // app | audit
            $table->date('log_date');                    // IST calendar day
            $table->string('path', 500)->unique();
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->unsignedInteger('lines')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['scope', 'tenant_id', 'log_date']);
            $table->index(['log_date', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_archives');
    }
};
