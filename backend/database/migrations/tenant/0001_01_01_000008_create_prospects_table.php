<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `prospects` table (R9.1–R9.4).
 *
 * Captures a prospective buyer's self sign-up before it becomes a real
 * Distributor or Dealer. A prospect is recorded with contact details and a
 * `status` of `pending` (R9.1). A Company_Admin later approves it — creating
 * the corresponding Distributor/Dealer (R9.2) and sending an account-created
 * email (R9.4) — or rejects it, marking the row `rejected` (R9.3). Isolation is
 * physical (one database per tenant), so there is no `tenant_id` column.
 *
 * `intended_buyer_type` records whether the prospect wants to become a
 * `distributor` or a `dealer`; a dealer prospect may name the Distributor it
 * wishes to be served by via the nullable `serving_distributor_id` FK. Approval
 * delegates to DistributorService/DealerService so quota and
 * `max_dealers_per_distributor` are enforced there (R10.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospects', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('gstin')->nullable();
            $table->text('address')->nullable();

            // What the prospect wants to become (R9.2): 'distributor' | 'dealer'.
            $table->string('intended_buyer_type', 16);

            // Lifecycle status (R9.1, R9.2, R9.3): 'pending' | 'approved' | 'rejected'.
            $table->string('status', 16)->default('pending');

            // Dealer prospects may name the serving Distributor (R20.2). Null for
            // distributor prospects and for dealer prospects left unassigned until
            // approval. Set null on delete so removing a distributor never orphans
            // the FK integrity of a historical prospect row.
            $table->foreignId('serving_distributor_id')
                ->nullable()
                ->constrained('distributors')
                ->nullOnDelete();

            $table->timestamps();

            // Status is the primary filter column for the pending-approvals queue
            // and status-scoped listings (R38.4).
            $table->index('status', 'prospects_status_index');

            // Buyer-type filter (e.g. "pending distributor sign-ups") (R38.4).
            $table->index('intended_buyer_type', 'prospects_intended_buyer_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospects');
    }
};
