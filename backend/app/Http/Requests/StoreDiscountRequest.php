<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Discount;
use App\Models\User;
use App\Support\CatalogOwner;
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
 *
 * Ownership: the catalogue scope (product/category/collection/colour) must be
 * the actor's own. A Distributor may target only its own dealers, or another
 * distributor; the Company may target any dealer or distributor.
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
        return array_merge([
            // Only `customer`/`online` are accepted; anything else is a 422 (R12.3).
            'type' => ['required', 'string', Rule::in(Discount::TYPES)],
            'percentage' => ['required_unless:value_type,'.Discount::VALUE_FIXED, 'nullable', 'numeric', 'min:0', 'max:100'],
        ], self::sharedRules($this->user(), $this->input('buyer_type')));
    }

    /**
     * Rules shared by create and update.
     *
     * @return array<string, mixed>
     */
    public static function sharedRules(?User $user, mixed $buyerType): array
    {
        $distributorId = CatalogOwner::distributorIdOf($user);

        $dealerExists = Rule::exists('tenant.dealers', 'id');
        if ($distributorId !== null) {
            $dealerExists = $dealerExists->where('distributor_id', $distributorId);
        }
        $distributorExists = Rule::exists('tenant.distributors', 'id');
        if ($distributorId !== null) {
            $distributorExists = $distributorExists->whereNot('id', $distributorId);
        }

        return [
            'customer_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            // percent = `percentage` % off; fixed = `amount` off in the tenant currency.
            'value_type' => ['sometimes', 'string', Rule::in(Discount::VALUE_TYPES)],
            'amount' => ['required_if:value_type,'.Discount::VALUE_FIXED, 'nullable', 'numeric', 'min:0', 'max:9999999999999'],
            // Dealer- or distributor-specific discount (highest priority levels).
            'buyer_type' => ['sometimes', 'nullable', 'string', Rule::in(['dealer', 'distributor'])],
            'buyer_id' => ['nullable', 'integer', 'required_with:buyer_type',
                Rule::when($buyerType === 'dealer', [$dealerExists]),
                Rule::when($buyerType === 'distributor', [$distributorExists])],
            // Rosetta-style catalog scope (most-specific-first resolution). Each
            // level is optional; NULL = applies to all at that level (R12.5).
            'product_id' => ['sometimes', 'nullable', 'integer', CatalogOwner::ownedExistsRule($user, 'products')],
            'category_id' => ['sometimes', 'nullable', 'integer', CatalogOwner::ownedExistsRule($user, 'categories')],
            'collection_id' => ['sometimes', 'nullable', 'integer', CatalogOwner::ownedExistsRule($user, 'collections')],
            'colour_id' => ['sometimes', 'nullable', 'integer', CatalogOwner::ownedExistsRule($user, 'colours')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
