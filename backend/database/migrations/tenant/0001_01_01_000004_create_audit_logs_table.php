<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `audit_logs` table.
 *
 * Append-only record of security-relevant actions and data mutations of
 * tenant-owned records, with actor, timestamp, and affected record identity
 * (R34.1). Entries are immutable — update/delete are rejected at the model
 * layer (R34.2). Shaped to the design AUDIT_LOG model and used as the
 * owen-it/laravel-auditing driver table for the tenant connection.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // owen-it/laravel-auditing user morph columns (user_type, user_id).
            // morphs() adds the composite index to resolve the acting user.
            $table->nullableMorphs('user');

            // The audited event: created|updated|deleted|restored, plus
            // security events (login, logout, password-reset, otp-verify) (R7.6, R34.1).
            $table->string('event');

            // Affected record identity (auditable_type, auditable_id). morphs()
            // adds the composite index used for per-record audit history (R34.1).
            $table->morphs('auditable');

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // Reason captured for overrides, impersonation, plan changes, and
            // stock adjustments (R34.3).
            $table->string('reason')->nullable();

            // owen-it/laravel-auditing context columns.
            $table->text('url')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('tags')->nullable();

            $table->timestamps();

            // Composite index (auditable_type, auditable_id, created_at):
            // audit history per record ordered by time (R34.1).
            $table->index(['auditable_type', 'auditable_id', 'created_at'], 'audit_logs_auditable_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
