<?php

declare(strict_types=1);

use App\Reports\ReportAccessContext;
use App\Services\PartyImpersonationService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: link a login `users` row to the buyer party it represents.
 *
 * A tenant user may be a company-side operator (Company_Admin/Company_Staff/
 * Read-only — no party link) OR a buyer-side panel user that logs in on behalf
 * of a single Distributor or a single Dealer (R8.6, R10.3). The optional
 * `distributor_id` / `dealer_id` FKs carry that link so the panel can confine a
 * buyer user to its OWN details and orders, and so the existing report-engine
 * buyer-scoping ({@see ReportAccessContext}) and party-impersonation
 * hierarchy ({@see PartyImpersonationService}) resolve the user's
 * party automatically.
 *
 * A user is linked to AT MOST one party: either a distributor, a dealer, or
 * neither (company-side). Both columns are nullable; the application layer
 * (TenantUserService) enforces the "exactly one party for a buyer user" rule
 * and assigns the matching spatie role. ON DELETE CASCADE removes a party's
 * login users when the party itself is deleted so no orphaned buyer login can
 * outlive its party.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The Distributor this user logs in as, when it is a distributor
            // panel user (null otherwise). Indexed for the hot "my party" and
            // "users of distributor X" lookups.
            $table->foreignId('distributor_id')
                ->nullable()
                ->after('email_verified_at')
                ->constrained('distributors')
                ->nullOnDelete();

            // The Dealer this user logs in as, when it is a dealer panel user
            // (null otherwise).
            $table->foreignId('dealer_id')
                ->nullable()
                ->after('distributor_id')
                ->constrained('dealers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('distributor_id');
            $table->dropConstrainedForeignId('dealer_id');
        });
    }
};
