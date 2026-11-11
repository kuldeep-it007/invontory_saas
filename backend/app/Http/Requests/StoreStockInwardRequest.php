<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\StockOperationsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a stock-inward request (R14.1).
 *
 * Authorization is handled by the route pipeline (tenant → Sanctum →
 * `permission:inventory.adjust` → `subscription.active`), so this request only
 * validates the payload.
 *
 * The `type` is restricted to the inward movement types `opening`, `received`,
 * and `adjustment_in` (R14.1); it defaults to `received` when omitted. The
 * product and stock point must exist in the active tenant database, and the
 * quantity is a positive DECIMAL(15,3) magnitude (inward always increases
 * stock).
 */
class StoreStockInwardRequest extends FormRequest
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
            'product_id' => ['required', 'integer', 'exists:tenant.products,id'],
            'stock_point_id' => ['required', 'integer', 'exists:tenant.stock_points,id'],
            // Positive DECIMAL(15,3) magnitude: inward only increases stock.
            'quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'type' => ['sometimes', Rule::in(StockOperationsService::INWARD_TYPES)],
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
