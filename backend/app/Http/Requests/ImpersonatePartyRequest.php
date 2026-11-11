<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a party impersonation request (R8.6).
 *
 * Authorization of WHO may impersonate WHOM (the hierarchy) is enforced in
 * {@see \App\Services\PartyImpersonationService}; this only validates the shape.
 */
class ImpersonatePartyRequest extends FormRequest
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
            'target_type' => ['required', 'string', Rule::in(['distributor', 'dealer'])],
            'target_id' => ['required', 'integer'],
        ];
    }
}
