<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Discount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a discount-creation request (R12.3, R12.5).
 *
 * Authorization is handled by the route pipeline (tenant → Sanctum →
 * `permission:catalog.manage`), so this request only validates the payload.
 * The `type` is constrained to `customer`/`online` ONLY (R12.3), rejecting any
 * other type with a 422 — mirroring the database enum and the model's setter
 * guard. The percentage is bounded to [0, 100] so a discount can only reduce a
 * price (R12.5).
 */
class StoreDiscountRequest extends FormRequest
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
            // Only `customer`/`online` are accepted; anything else is a 422 (R12.3).
            'type' => ['required', 'string', Rule::in(Discount::TYPES)],
            'customer_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            // percent = `percentage` % off; fixed = `amount` off in the tenant currency.
            'value_type' => ['sometimes', 'string', Rule::in(Discount::VALUE_TYPES)],
            'percentage' => ['required_unless:value_type,'.Discount::VALUE_FIXED, 'nullable', 'numeric', 'min:0', 'max:100'],
            'amount' => ['required_if:value_type,'.Discount::VALUE_FIXED, 'nullable', 'numeric', 'min:0', 'max:9999999999999'],
            // Dealer- or distributor-specific discount (highest priority levels).
            'buyer_type' => ['sometimes', 'nullable', 'string', Rule::in(['dealer', 'distributor'])],
            'buyer_id' => ['nullable', 'integer', 'required_with:buyer_type',
                Rule::when($this->input('buyer_type') === 'dealer', ['exists:tenant.dealers,id']),
                Rule::when($this->input('buyer_type') === 'distributor', ['exists:tenant.distributors,id'])],
            'product_id' => ['sometimes', 'nullable', 'integer', 'exists:tenant.products,id'],
            // Rosetta-style catalog scope (most-specific-first resolution). Each
            // level is optional; NULL = applies to all at that level (R12.5).
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:tenant.categories,id'],
            'collection_id' => ['sometimes', 'nullable', 'integer', 'exists:tenant.collections,id'],
            'colour_id' => ['sometimes', 'nullable', 'integer', 'exists:tenant.colours,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
