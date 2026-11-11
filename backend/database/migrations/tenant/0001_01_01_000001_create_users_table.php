<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `users`, `password_reset_tokens`, and `sessions`.
 *
 * Tenant-scoped users live in each tenant's own database (physical isolation,
 * no `tenant_id` column). Passwords are stored hashed with bcrypt/argon2 (R7.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // UNIQUE(email): per-tenant unique login identity and the hot lookup
            // during authentication. Uniqueness is per-tenant automatically
            // because each tenant has its own `users` table (R7.1).
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();

            // Stored bcrypt/argon2 hash, never plaintext (R7.3).
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            // Primary key on email is the lookup used when consuming a reset
            // token during the password-reset flow (R7.4).
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();

            // Indexed FK-style column: look up a user's sessions quickly.
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');

            // Indexed for the session garbage-collection scan by last activity.
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
