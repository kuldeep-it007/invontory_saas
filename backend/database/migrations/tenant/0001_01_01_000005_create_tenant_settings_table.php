<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `tenant_settings` table.
 *
 * Per-tenant white-label and operational settings stored inside the tenant's
 * own database (R3.1): logo, brand name, colour theme, favicon, custom domain,
 * and outbound email sender identity. Stored as a flexible key/value table so
 * later milestones can add settings without a schema change. There is no
 * `tenant_id` column — isolation is physical.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->id();

            // Setting key, e.g. logo, brand_name, color_theme, favicon,
            // custom_domain, mail_from_address, mail_from_name (R3.1).
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();

            // UNIQUE(key): one row per setting key within the tenant database and
            // the direct lookup when the theme loader/mailer reads a setting (R3.1).
            $table->unique('key', 'tenant_settings_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');
    }
};
