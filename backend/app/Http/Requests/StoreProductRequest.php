<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a product-creation request (R11.1, R11.3).
 *
 * Authorization is handled by the route pipeline (tenant → Sanctum →
 * `permission:catalog.manage`), so this request only validates the payload.
 *
 * SKU uniqueness within the tenant database (R11.1) is validated against the
 * active `tenant` connection as a friendly 422; the UNIQUE(`sku`) index remains
 * the authoritative guarantee. Because each tenant has its own `products`
 * table, this uniqueness is per-tenant automatically.
 *
 * The product QUOTA (R5.1) is NOT checked here: it is enforced in
 * {@see \App\Services\ProductService::create()} which returns a 403 naming the
 * `max_products` quota rather than a 422.
 */
class StoreProductRequest extends FormRequest
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
            'sku' => [
                'required',
                'string',
                'max:255',
                // Unique within the active tenant database (R11.1).
                Rule::unique('tenant.products', 'sku'),
            ],
            'name' => ['required', 'string', 'max:255'],
            // Base selling amount maintained with the product (R11.3). Optional.
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'collection_id' => ['required', 'integer', 'exists:tenant.collections,id'],
            'colour_id' => ['required', 'integer', 'exists:tenant.colours,id'],
            'tax_type' => ['required', 'string', 'max:255'],
            'uom' => ['required', 'string', 'max:255'],
        ];
    }
}
