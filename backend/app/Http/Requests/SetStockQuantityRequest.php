<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates editing a stock row's on-hand quantity to an absolute value.
 *
 * Authorization is handled by the route pipeline (`permission:inventory.adjust`
 * + `subscription.active`). Like a manual adjustment (R14.3), the change must
 * carry a non-empty reason, which is written to the ledger and the audit log.
 */
class SetStockQuantityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('reason') && is_string($this->input('reason'))) {
            $this->merge(['reason' => trim($this->input('reason'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The new on-hand quantity: DECIMAL(15,3), never negative.
            'quantity' => ['required', 'numeric', 'min:0', 'decimal:0,3'],
            'reason' => ['required', 'string', 'min:1', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Editing stock requires a reason.',
            'reason.min' => 'Editing stock requires a reason.',
        ];
    }
}
