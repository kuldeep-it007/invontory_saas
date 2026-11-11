<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a `POST /orders/{id}/return` request (R18.3, R22.2).
 *
 * Authorization is handled by the route pipeline (tenant → Sanctum → RBAC), so
 * this request only validates the payload: a non-empty `lines` array where each
 * line names a `product_id`, a positive `quantity`, and an optional credited
 * `amount`. Whether each product is actually part of the order is decided by
 * {@see \App\Services\OrderReturnService}, which rejects a mismatch with a 422.
 */
class OrderReturnRequest extends FormRequest
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
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'min:1'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => 'A return requires at least one line.',
            'lines.min' => 'A return requires at least one line.',
            'lines.*.quantity.gt' => 'A return line quantity must be greater than zero.',
        ];
    }
}
