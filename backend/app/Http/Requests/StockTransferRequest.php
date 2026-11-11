<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a stock-transfer request (R18.1).
 *
 * Authorization is handled by the route pipeline (tenant → Sanctum →
 * `permission:inventory.adjust` + `subscription.active`), so this request only
 * validates the payload shape. The product and both stock points must exist in
 * the active tenant database. The quantity is a positive decimal (quantities
 * are DECIMAL(15,3)). Source and destination must differ — transferring stock
 * to the SAME stock point is a no-op and rejected here.
 *
 * The SAME-OWNER rule (both stock points must belong to one owner) is NOT
 * asserted here: it is a business invariant enforced by
 * {@see \App\Services\StockTransferService}, which returns a 422 via
 * {@see \App\Exceptions\StockPointOwnerMismatchException} on a cross-owner
 * transfer, so the two validation concerns stay cleanly separated.
 */
class StockTransferRequest extends FormRequest
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
            'product_id' => ['required', 'integer', Rule::exists('tenant.products', 'id')],
            'source_stock_point_id' => [
                'required',
                'integer',
                Rule::exists('tenant.stock_points', 'id'),
            ],
            'destination_stock_point_id' => [
                'required',
                'integer',
                'different:source_stock_point_id',
                Rule::exists('tenant.stock_points', 'id'),
            ],
            // Positive quantity to move; DECIMAL(15,3) is kept exact as a string
            // downstream, so validate as a numeric string strictly greater than 0.
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
