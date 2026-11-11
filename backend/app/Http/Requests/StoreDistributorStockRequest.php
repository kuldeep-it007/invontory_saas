<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates adding stock held by a Distributor (distributor-wise stock
 * management). Authorization is the route pipeline's job
 * (`permission:inventory.adjust` + `subscription.active`).
 */
class StoreDistributorStockRequest extends FormRequest
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
            'distributor_id' => ['required', 'integer', 'exists:tenant.distributors,id'],
            'product_id' => ['required', 'integer', 'exists:tenant.products,id'],
            // Optional: defaults to the distributor's primary stock point.
            'stock_point_id' => ['sometimes', 'nullable', 'integer', 'exists:tenant.stock_points,id'],
            'quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
