<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\QuotaExceededException;
use App\Http\Controllers\DealerController;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Models\PlanLimit;
use Illuminate\Support\Facades\DB;

/**
 * Focused service for Dealer management (R10.2, R10.3, R10.4, R10.5).
 *
 * Owns the business rules the thin {@see DealerController}
 * delegates to: quota-checked creation under a Distributor and Payment_Terms
 * updates. Creation enforces BOTH the whole-tenant `max_dealers` quota and the
 * per-Distributor `max_dealers_per_distributor` cap (R10.4) before insert. All
 * work runs on the active tenant connection (physical isolation); create/update
 * are audited through the {@see Dealer} model's auditable trait (R10.5, R34.1).
 */
class DealerService
{
    public function __construct(private readonly QuotaService $quota) {}

    /**
     * Create a Dealer under the given Distributor, enforcing the whole-tenant
     * `max_dealers` quota (R5.1, R5.2) and the per-Distributor
     * `max_dealers_per_distributor` cap (R10.4) before insert.
     *
     * @param  array<string, mixed>  $data  Validated dealer attributes.
     *
     * @throws QuotaExceededException When a dealer quota/cap is reached (403).
     */
    public function create(Distributor $distributor, array $data): Dealer
    {
        $distributorId = (int) $distributor->getKey();

        // Enforce both quotas BEFORE creating (R5.1, R5.2, R10.4). The
        // per-distributor cap is checked in addition to the tenant-wide one.
        $this->quota->assertAllows(PlanLimit::MAX_DEALERS);
        $this->quota->assertAllowsDealerForDistributor($distributorId);

        $dealer = DB::connection('tenant')->transaction(function () use ($data, $distributorId): Dealer {
            return Dealer::create([
                'distributor_id' => $distributorId,
                'name' => $data['name'],
                'contact_name' => $data['contact_name'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'fax' => $data['fax'] ?? null,
                'gstin' => $data['gstin'] ?? null,
                'address' => $data['address'] ?? null,
                'profile' => $data['profile'] ?? null,
                'payment_terms' => $data['payment_terms'] ?? null,
            ]);
        });

        // A new counted resource exists: invalidate the cached count (R5.6).
        $this->quota->invalidate(PlanLimit::MAX_DEALERS);

        return $dealer;
    }

    /**
     * Update a Dealer's attributes, including Payment_Terms. The change is
     * persisted and audited via the model's auditable trait (R10.5, R34.1).
     *
     * @param  array<string, mixed>  $data  Validated attributes to update (partial).
     */
    public function update(Dealer $dealer, array $data): Dealer
    {
        return DB::connection('tenant')->transaction(function () use ($dealer, $data): Dealer {
            $dealer->fill($this->fillableFrom($data));
            $dealer->save();

            return $dealer->refresh();
        });
    }

    /**
     * Only copy the keys actually present in the payload so a partial update
     * never nulls out unspecified fields.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fillableFrom(array $data): array
    {
        $keys = [
            'name', 'contact_name', 'contact_email', 'contact_phone', 'fax',
            'gstin', 'address', 'profile', 'payment_terms',
        ];

        return array_intersect_key($data, array_flip($keys));
    }
}
