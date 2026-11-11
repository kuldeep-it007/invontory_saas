<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central database: `domains` table (stancl/tenancy).
 *
 * Maps a subdomain or custom domain to a tenant. This is the resolution
 * table stancl/tenancy consults on each request to identify the tenant and
 * switch the default connection to that tenant's database (R1.4, R3.4).
 */
class CreateDomainsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->increments('id');

            // UNIQUE(domain): resolve a subdomain/custom domain -> tenant for
            // tenancy initialization, and prevent two tenants claiming the same
            // host. This is the hot lookup on every tenant request (R1.4, R3.4).
            $table->string('domain', 255)->unique();

            // FK -> tenants.id. Indexed implicitly by the foreign key; used to
            // list/resolve all domains that point at a given tenant.
            $table->string('tenant_id');

            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
}
