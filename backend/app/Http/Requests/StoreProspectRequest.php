<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Prospect;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a prospect self sign-up (R9.1).
 *
 * This endpoint is public (the tenant is resolved from the subdomain/custom
 * domain, but no authentication is required), so authorization is open and only
 * the payload shape is validated. A dealer prospect may name a serving
 * Distributor; a distributor prospect must not.
 */
class StoreProspectRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'gstin' => ['sometimes', 'nullable', 'string', 'max:32'],
            'address' => ['sometimes', 'nullable', 'string', 'max:1024'],

            // What the prospect wants to become (R9.2).
            'intended_buyer_type' => [
                'required',
                Rule::in([Prospect::TYPE_DISTRIBUTOR, Prospect::TYPE_DEALER]),
            ],

            // A dealer prospect may name its serving Distributor; required only
            // when the prospect is a dealer, forbidden otherwise (R20.2).
            'serving_distributor_id' => [
                Rule::requiredIf(fn (): bool => $this->input('intended_buyer_type') === Prospect::TYPE_DEALER),
                'prohibited_if:intended_buyer_type,'.Prospect::TYPE_DISTRIBUTOR,
                'nullable',
                'integer',
                'exists:tenant.distributors,id',
            ],
        ];
    }
}
