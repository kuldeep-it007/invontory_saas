<?php

declare(strict_types=1);

namespace App\Http\Requests;

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
    public function authorize(): bool
    {
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
                Rule::unique('tenant.products', 'sku')->ignore($productId),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            // Base selling amount maintained with the product (R11.3). Optional,
            // and may be explicitly cleared by sending null.
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'collection_id' => ['sometimes', 'required', 'integer', 'exists:tenant.collections,id'],
            'colour_id' => ['sometimes', 'required', 'integer', 'exists:tenant.colours,id'],
            'tax_type' => ['sometimes', 'required', 'string', 'max:255'],
            'uom' => ['sometimes', 'required', 'string', 'max:255'],
        ];
    }
}
