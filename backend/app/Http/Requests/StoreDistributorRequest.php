<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\PartyProfileRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates creation of a Distributor (R10.1).
 *
 * Authorization is handled by the route pipeline (tenant → Sanctum →
 * `permission:distributors.manage`), so this request only validates the
 * payload shape: contact, GSTIN, address, the Rosetta-style party profile, and
 * the Payment_Terms block.
 */
class StoreDistributorRequest extends FormRequest
{
    use PartyProfileRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'gstin' => ['sometimes', 'nullable', 'string', 'max:32'],
            'address' => ['sometimes', 'nullable', 'string', 'max:1024'],

            // Payment_Terms block (R10.1, R26.1).
            'payment_terms' => ['sometimes', 'nullable', 'array'],
            'payment_terms.credit_limit' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'payment_terms.overdue_balance_limit' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'payment_terms.overdue_hold_days' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'payment_terms.payment_type' => ['sometimes', 'nullable', 'string', 'max:64'],
        ], $this->partyProfileRules());
    }
}
