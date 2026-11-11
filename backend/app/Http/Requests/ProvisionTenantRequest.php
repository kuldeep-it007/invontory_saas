<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a tenant provisioning request (R2.1, R2.2).
 *
 * Authorization is handled by the route pipeline (platform.admin → Sanctum →
 * role:Platform_Admin), so this request only validates the payload shape.
 * Subdomain/domain UNIQUENESS is NOT asserted here: it is enforced by
 * {@see \App\Services\TenantProvisioningService} which returns HTTP 409 on a
 * duplicate (R2.3) rather than a 422 validation error.
 */
class ProvisionTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Subdomain: DNS-label-safe (lowercase alphanumerics + hyphens).
            'subdomain' => [
                'required',
                'string',
                'max:63',
                'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/',
            ],
            // Optional explicit custom/resolution domain; defaults to
            // "<subdomain>.<central_domain>" in the service when omitted.
            'domain' => ['sometimes', 'nullable', 'string', 'max:255'],

            'plan_id' => ['required', 'integer', 'exists:central.plans,id'],

            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'backorders_enabled' => ['sometimes', 'boolean'],
            'dealer_stock_enabled' => ['sometimes', 'boolean'],

            'subscription_status' => [
                'sometimes',
                Rule::in(['active', 'past_due', 'cancelled', 'trial']),
            ],

            // Tenant's single Company (R1.5).
            'company_name' => ['required', 'string', 'max:255'],
            'company_gstin' => ['sometimes', 'nullable', 'string', 'max:32'],

            // Initial Company_Admin user seeded inside the tenant database (R2.1).
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }
}
