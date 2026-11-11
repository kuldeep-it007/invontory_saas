<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an advanced-order placement request (R23.1, R23.2, R23.3).
 *
 * Authorization is handled by the route pipeline (tenant → Sanctum →
 * `permission:orders.create` + `subscription.active`), so this request only
 * validates the payload shape. The buyer is a Distributor or Dealer in the
 * active tenant database; the `order_type` is one of the ADVANCED types
 * (blanket/advance/depot — `standard` goes through the standard order flow).
 * Each line names a product, a positive quantity, and — for a depot order — the
 * stock point (depot) to reserve against (R23.3), optionally with a scheduled
 * `expires_at` release window for a blanket order (R23.1).
 *
 * The `advance_orders` feature gate (R23.2) is NOT asserted here — it is a plan
 * concern enforced by {@see \App\Services\AdvancedOrderService} via the
 * subscription gate so the HTTP-shape and feature-flag concerns stay separate.
 */
class PlaceAdvancedOrderRequest extends FormRequest
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

            // One of the ADVANCED order types — a standard order is placed via
            // POST /orders, so it is rejected here (R23.1–R23.3).
            'order_type' => [
                'required',
                'string',
                Rule::in([Order::TYPE_BLANKET, Order::TYPE_ADVANCE, Order::TYPE_DEPOT]),
            ],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', Rule::exists('tenant.products', 'id')],
            // Positive quantity; DECIMAL(15,3) kept exact as a string downstream.
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            // Depot stock point to reserve against (R23.3); optional otherwise.
            'lines.*.stock_point_id' => ['sometimes', 'nullable', 'integer', Rule::exists('tenant.stock_points', 'id')],
            // Scheduled release window for a blanket order line (R23.1).
            'lines.*.expires_at' => ['sometimes', 'nullable', 'date'],

            'total' => ['sometimes', 'nullable', 'numeric', 'gte:0'],
        ];
    }
}
