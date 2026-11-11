<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Base request carrying the Buyer that owns the cart (R20.1, R20.2).
 *
 * Every cart operation is performed on behalf of a specific Buyer (Distributor
 * or Dealer). Authorization is handled by the route pipeline
 * (`permission:orders.create`); this validates the buyer identity, which must
 * exist in the active tenant database.
 */
class CartBuyerRequest extends FormRequest
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
        ];
    }
}
