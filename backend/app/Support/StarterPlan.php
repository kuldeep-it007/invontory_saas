<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\PlanLimit;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Reusable definition and provisioner of the STARTER subscription tier (R4.7).
 *
 * This is the single source of truth for the Starter plan's name, price,
 * billing cycle, quota limits, and feature flags. Both the {@see PlanSeeder}
 * (environment/baseline seeding) and tenant provisioning reference it so the
 * starter defaults are defined exactly once.
 *
 * Starter quota defaults (R4.7): max_distributors=5, max_dealers=50,
 * max_products=200, max_stock_points=3, max_users=10. Quota keys not listed
 * here (e.g. max_dealers_per_distributor, max_orders_per_month, max_storage_mb)
 * are intentionally omitted, which means UNLIMITED under R4.4.
 *
 * Starter feature flags: all optional modules are OFF on the Starter tier
 * (R4.5) — higher tiers opt in explicitly.
 */
final class StarterPlan
{
    public const NAME = 'Starter';

    public const PRICE = 0.0;

    public const BILLING_CYCLE = 'monthly';

    /**
     * Starter-tier quota defaults keyed by the supported quota keys (R4.3, R4.7).
     *
     * @var array<string, int>
     */
    public const LIMITS = [
        PlanLimit::MAX_DISTRIBUTORS => 5,
        PlanLimit::MAX_DEALERS => 50,
        PlanLimit::MAX_PRODUCTS => 200,
        PlanLimit::MAX_STOCK_POINTS => 3,
        PlanLimit::MAX_USERS => 10,
    ];

    /**
     * Starter-tier feature flags (R4.5). All optional modules disabled on
     * Starter; enumerated explicitly so the allow-list is documented per plan.
     *
     * @var array<string, bool>
     */
    public const FEATURES = [
        PlanFeature::WHATSAPP_NOTIFICATIONS => false,
        PlanFeature::OTP_LOGIN => false,
        PlanFeature::ADVANCE_ORDERS => false,
        PlanFeature::EXPORTS => false,
        PlanFeature::API_ACCESS => false,
        PlanFeature::AI_REPORTS => false,
    ];

    /**
     * Idempotently create (or return the existing) Starter plan in the central
     * database with its quota limits and feature flags attached (R4.7). Safe to
     * call repeatedly: existing limits/features are synced to the defaults.
     *
     * Runs inside a central-connection transaction so a partial seed never
     * leaves a plan without its limits/features.
     */
    public static function provision(): Plan
    {
        return DB::connection('central')->transaction(static function (): Plan {
            /** @var Plan $plan */
            $plan = Plan::query()->firstOrCreate(
                ['name' => self::NAME],
                [
                    'price' => self::PRICE,
                    'billing_cycle' => self::BILLING_CYCLE,
                    'is_active' => true,
                ],
            );

            foreach (self::LIMITS as $key => $value) {
                $plan->limits()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $value],
                );
            }

            foreach (self::FEATURES as $key => $enabled) {
                $plan->features()->updateOrCreate(
                    ['key' => $key],
                    ['enabled' => $enabled],
                );
            }

            return $plan->fresh(['limits', 'features']) ?? $plan;
        });
    }
}
