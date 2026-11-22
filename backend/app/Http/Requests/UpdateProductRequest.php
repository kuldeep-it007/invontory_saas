<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Product;
use App\Support\CatalogOwner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a product-update request (R11.3).
 *
 * A partial payload is accepted. When `sku` is present it must remain UNIQUE
 * within the tenant database (R11.1), ignoring the product being updated so a
 * no-op save does not collide with itself.
 */
class UpdateProductRequest extends FormRequest
{
    /**
     * Only the product's owner may edit it; anyone else gets 404 before
     * validation runs.
     */
    public function authorize(): bool
    {
        $product = $this->route('product');
        if ($product instanceof Product) {
            CatalogOwner::assertManages($this->user(), $product);
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $productId = $this->route('product');

        return [
            'sku' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                CatalogOwner::ownedUniqueRule($this->user(), 'products', 'sku', $productId),
            ],
            // Mandatory: required when the product has no HSN yet; never cleared.
            'hsn_code' => [Rule::requiredIf(fn (): bool => blank($this->route('product')?->hsn_code)), 'string', 'regex:/^(\d{4}|\d{6}|\d{8})$/'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            // Base selling amount maintained with the product (R11.3). Optional,
            // and may be explicitly cleared by sending null.
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'collection_id' => ['sometimes', 'required', 'integer', CatalogOwner::ownedExistsRule($this->user(), 'collections')],
            'colour_id' => ['sometimes', 'required', 'integer', CatalogOwner::ownedExistsRule($this->user(), 'colours')],
            'tax_type' => ['sometimes', 'required', 'string', 'max:255'],
            'uom' => ['sometimes', 'required', 'string', 'max:255'],
        ];
    }
}
