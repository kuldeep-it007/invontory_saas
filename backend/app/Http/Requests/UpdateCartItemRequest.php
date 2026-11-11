<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates setting the quantity of a cart line (R20.3). A quantity of 0 is
 * allowed here and interpreted by the service as "remove the line"; positive
 * values recompute the line's price breakdown.
 *
 * Authorization is handled by the route pipeline (`permission:orders.create`).
 */
class UpdateCartItemRequest extends FormRequest
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
            'buyer_type' => ['required', 'string', Rule::in(['distributor', 'dealer'])],
            'buyer_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'gte:0', 'decimal:0,3'],

            // Optional per-stock-point source split (R16, R20).
            'allocations' => ['sometimes', 'nullable', 'array'],
            'allocations.*.stock_point_id' => ['required_with:allocations', 'integer'],
            'allocations.*.quantity' => ['required_with:allocations', 'numeric', 'gt:0', 'decimal:0,3'],
        ];
    }
}
