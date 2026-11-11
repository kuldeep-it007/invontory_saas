<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a Platform_Admin impersonation request (R8.6).
 *
 * Authorization is handled by the route pipeline (platform.admin → Sanctum →
 * role:Platform_Admin), so this request only validates the payload shape. The
 * target USER id arrives as the `{userId}` route parameter; the body carries
 * the target TENANT identifier (its central `tenants.id`) and an optional
 * free-text `reason` recorded with the audit entry (R34.3).
 */
class ImpersonateUserRequest extends FormRequest
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
            // Central tenants.id of the tenant to impersonate into. The tenant
            // key is a string/uuid-capable column in stancl/tenancy, so accept
            // a non-empty string that must exist centrally.
            'tenant_id' => ['required', 'string', 'exists:central.tenants,id'],

            // Optional reason recorded alongside the impersonation audit entry
            // (R34.3 — the action and its reason are recorded).
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
