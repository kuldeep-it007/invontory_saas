<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: an optional profile picture per user.
 *
 * Stored as a small, already-resized image data URL (the browser crops it to
 * 256×256 before upload, typically 10–40 KB). Keeping it with the user row
 * means it is backed up with the tenant database and needs no separate file
 * serving or storage volume. MEDIUMTEXT comfortably fits the server-side cap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'avatar')) {
                $table->mediumText('avatar')->nullable()->after('dealer_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'avatar')) {
                $table->dropColumn('avatar');
            }
        });
    }
};
