<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `personal_access_tokens` (Sanctum).
 *
 * Tenant users authenticate with Sanctum tokens resolved from the active
 * tenant database, so the token table must exist per tenant (R7.1). The
 * `morphs('tokenable')` call adds the (tokenable_type, tokenable_id) index
 * for resolving a token's owning user.
 *
 * Each token also carries the id of the tenant it was issued for in a
 * nullable `tenant_id` stamp. The post-authentication reconciliation guard
 * ({@see \App\Http\Middleware\ReconcileTenantUser}) compares this stamp
 * against the active tenant to reject a token replayed across tenant
 * databases (R1.6, R1.3). The column is indexed so the guard's check and any
 * per-tenant token housekeeping stay cheap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();

            // Composite index on (tokenable_type, tokenable_id) via morphs():
            // resolve the user a token belongs to.
            $table->morphs('tokenable');
            $table->text('name');

            // UNIQUE(token): the hot lookup on every authenticated request.
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();

            // Tenant stamp (R1.6): the tenant this token was issued for. The
            // ReconcileTenantUser middleware rejects a request whose token
            // stamp does not match the active tenant. Nullable + indexed so the
            // guard's comparison is a cheap, non-breaking addition.
            $table->string('tenant_id')->nullable()->index();

            $table->timestamp('last_used_at')->nullable();

            // Indexed for pruning expired tokens.
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
