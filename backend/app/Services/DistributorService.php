<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\QuotaExceededException;
use App\Http\Controllers\DistributorController;
use App\Models\Company;
use App\Models\Concerns\TenantAuditable;
use App\Models\Distributor;
use App\Models\PlanLimit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Focused service for Distributor management (R10.1, R10.3, R10.5).
 *
 * Owns the business rules the thin {@see DistributorController}
 * delegates to: quota-checked creation under the single tenant Company, and
 * Payment_Terms updates. All work runs on the active tenant connection
 * (resolved by the tenancy middleware), so records always belong to the current
 * tenant (physical isolation). Create/update are audited automatically through
 * the {@see Distributor} model's {@see TenantAuditable}
 * trait (R10.5, R34.1).
 */
class DistributorService
{
    public function __construct(private readonly QuotaService $quota) {}

    /**
     * Create a Distributor under the tenant's single Company, enforcing the
     * `max_distributors` quota before insert (R10.1, R5.1, R5.2).
     *
     * @param  array<string, mixed>  $data  Validated distributor attributes.
     *
     * @throws QuotaExceededException When the distributor quota is reached (403).
     */
    public function create(array $data): Distributor
    {
        // Enforce the whole-tenant distributor quota BEFORE creating (R5.1, R5.2).
        $this->quota->assertAllows(PlanLimit::MAX_DISTRIBUTORS);

        $companyId = $this->resolveCompanyId();

        $distributor = DB::connection('tenant')->transaction(function () use ($data, $companyId): Distributor {
            return Distributor::create([
                'company_id' => $companyId,
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
        $this->quota->invalidate(PlanLimit::MAX_DISTRIBUTORS);

        return $distributor;
    }

    /**
     * Update a Distributor's attributes, including Payment_Terms. The change is
     * persisted and audited via the model's auditable trait (R10.5, R34.1).
     *
     * @param  array<string, mixed>  $data  Validated attributes to update (partial).
     */
    public function update(Distributor $distributor, array $data): Distributor
    {
        return DB::connection('tenant')->transaction(function () use ($distributor, $data): Distributor {
            $distributor->fill($this->fillableFrom($data));
            $distributor->save();

            return $distributor->refresh();
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

    /**
     * The id of the tenant's single Company (R1.5, R10.3). Every Distributor is
     * created under it.
     *
     * @throws ModelNotFoundException When no Company exists in the tenant DB.
     */
    private function resolveCompanyId(): int
    {
        $company = Company::query()->firstOrFail();

        return (int) $company->getKey();
    }
}
