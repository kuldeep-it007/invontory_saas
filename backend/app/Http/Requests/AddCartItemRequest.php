<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates adding a product line to the cart (R20.3).
 *
 * Authorization is handled by the route pipeline (`permission:orders.create`).
 * The buyer owns the cart; the product must exist in the active tenant
 * database and the quantity must be a positive DECIMAL(15,3).
 */
class AddCartItemRequest extends FormRequest
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
            'product_id' => ['required', 'integer', 'exists:tenant.products,id'],
            'quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],

            // Optional per-stock-point source split (R16, R20).
            'allocations' => ['sometimes', 'nullable', 'array'],
            'allocations.*.stock_point_id' => ['required_with:allocations', 'integer'],
            'allocations.*.quantity' => ['required_with:allocations', 'numeric', 'gt:0', 'decimal:0,3'],
        ];
    }
}
