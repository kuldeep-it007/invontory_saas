<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;

/**
 * Resolves a numeric tax rate (percent) for a product's tax type (R12, R39.1).
 *
 * The catalog stores `Product.tax_type` as a free-form label (e.g. "GST18",
 * "gst_18", "GST 5%", "VAT-12"). This service parses a percentage out of that
 * label so the cart/order can compute a tax amount, without requiring a
 * separate tax-rate table. A label with no parseable number resolves to 0%
 * (tax-free), so an un-configured product never blocks a calculation.
 *
 * The rate is a percentage (e.g. 18.0 means 18%); the monetary tax amount is
 * `net_amount × rate / 100`, expressed in the tenant's single currency (R39.1).
 */
class TaxResolutionService
{
    /**
     * The tax rate (as a percentage) for a product. Parses the first numeric
     * value from the product's `tax_type` label; returns 0.0 when none is
     * present. The rate is clamped into [0, 100].
     */
    public function rateForProduct(Product $product): float
    {
        return $this->rateFromLabel($product->tax_type);
    }

    /**
     * Parse a tax percentage out of a free-form label.
     *
     * Accepts integers or decimals embedded anywhere in the string
     * ("GST18" → 18, "gst_18" → 18, "5% CGST" → 5, "VAT-12.5" → 12.5). A null,
     * empty, or number-less label resolves to 0.0. The result is clamped into
     * [0, 100] so a malformed label can never produce a negative or absurd rate.
     */
    public function rateFromLabel(?string $label): float
    {
        if ($label === null || trim($label) === '') {
            return 0.0;
        }

        if (preg_match('/\d+(?:\.\d+)?/', $label, $matches) !== 1) {
            return 0.0;
        }

        $rate = (float) $matches[0];

        return max(0.0, min(100.0, $rate));
    }
}
