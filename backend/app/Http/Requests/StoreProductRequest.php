<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\ProductService;
use App\Support\CatalogOwner;
use Illuminate\Foundation\Http\FormRequest;

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
 * {@see ProductService::create()} which returns a 403 naming the
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
                // Unique among the actor's own products (SKU is unique per owner).
                CatalogOwner::ownedUniqueRule($this->user(), 'products', 'sku'),
            ],
            // GST HSN / SAC code: 4, 6 or 8 digits. Mandatory.
            'hsn_code' => ['required', 'string', 'regex:/^(\d{4}|\d{6}|\d{8})$/'],
            'name' => ['required', 'string', 'max:255'],
            // Base selling amount maintained with the product (R11.3). Optional.
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'collection_id' => ['required', 'integer', CatalogOwner::ownedExistsRule($this->user(), 'collections')],
            'colour_id' => ['required', 'integer', CatalogOwner::ownedExistsRule($this->user(), 'colours')],
            'tax_type' => ['required', 'string', 'max:255'],
            'uom' => ['required', 'string', 'max:255'],
        ];
    }
}
