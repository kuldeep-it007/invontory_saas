<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates submission of a cart into an Order (R20.3, R20.4).
 *
 * Authorization is handled by the route pipeline (tenant → Sanctum →
 * `permission:orders.create`), so this request only validates the payload: the
 * Buyer placing the order and the non-empty list of cart entries, each a
 * product + positive quantity. The Buyer's morph type is restricted to
 * Distributor or Dealer (R20.1, R20.2); the product and buyer must exist in the
 * active tenant database.
 */
class SubmitCartRequest extends FormRequest
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
            // The Buyer placing the order: a Distributor or Dealer (R20.1, R20.2).
            'buyer_type' => ['required', 'string', Rule::in(['distributor', 'dealer'])],
            'buyer_id' => ['required', 'integer'],

            // Order flow discriminator (R23); optional, defaults to `standard`.
            'order_type' => ['sometimes', Rule::in(['standard', 'blanket', 'advance', 'depot'])],

            // A cart accumulates one or more lines before submission (R20.3);
            // at least one entry is required, and each becomes one order line
            // (R20.4).
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:tenant.products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
        ];
    }
}
