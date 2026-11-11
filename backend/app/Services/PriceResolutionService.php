<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Discount;
use App\Models\Price;
use App\Models\Product;
use InvalidArgumentException;

/**
 * Order-line price resolution (R12.1–R12.5, R39.1).
 *
 * Focused single-responsibility service that computes the price of an order
 * line for a Buyer. The resolution is a pure, deterministic pipeline:
 *
 *   1. RESOLVE the applicable base price. A Product-level price OVERRIDES a
 *      Collection-level price for that product; the collection price is the
 *      fallback when no product-level price exists (R12.1, R12.4).
 *   2. APPLY the collection markup/MUP (if any) to the base (R12.2).
 *   3. APPLY all applicable discounts (`customer` + `online`) CUMULATIVELY —
 *      each successive percentage reduces the running amount (R12.5).
 *   4. CLAMP the result so it is NEVER negative (R12.4).
 *
 * Every monetary value is expressed in the Tenant's single configured currency
 * (R39.1), read from the active tenancy context. The arithmetic core
 * ({@see computePrice()}) is pure so it can be exercised directly by property
 * tests, while {@see resolveForProduct()} performs the tenant-database lookups.
 */
class PriceResolutionService
{
    /**
     * Resolve the price for a Product and Buyer from the active tenant database
     * (R12.1–R12.5). Reads the applicable Price (product-level overriding
     * collection-level) and the set of active, applicable discounts, then
     * delegates to the pure {@see computePrice()}.
     *
     * @param  Product     $product   the ordered product.
     * @param  string|null $buyerType the Buyer's morph type (Distributor/Dealer), or null.
     * @param  int|null    $buyerId   the Buyer's id, or null.
     */
    public function resolveForProduct(Product $product, ?string $buyerType = null, ?int $buyerId = null): ResolvedPrice
    {
        $price = $this->resolveApplicablePrice($product);

        if ($price === null) {
            throw new InvalidArgumentException(sprintf(
                'No applicable price configured for product %d (neither product- nor collection-level).',
                (int) $product->getKey(),
            ));
        }

        $discounts = $this->applicableDiscounts($product, $buyerType, $buyerId);

        return $this->computePrice(
            (float) $price->amount,
            $price->markup_type,
            $price->markup_value === null ? null : (float) $price->markup_value,
            $discounts,
        );
    }

    /**
     * Pure price computation (R12.2, R12.4, R12.5, R39.1).
     *
     * Applies the markup/MUP to the base, then every discount percentage
     * cumulatively, and clamps the final amount at zero so it is never
     * negative. The returned {@see ResolvedPrice} is tagged with the tenant's
     * single currency.
     *
     * @param  float                       $baseAmount     the resolved applicable base price (>= 0).
     * @param  string|null                 $markupType     `markup`, `mup`, or null for no markup (R12.2).
     * @param  float|null                  $markupValue    markup/MUP percentage, or null.
     * @param  array<int, float>           $discountPercents applicable discount percentages, applied cumulatively (R12.5).
     */
    public function computePrice(
        float $baseAmount,
        ?string $markupType = null,
        ?float $markupValue = null,
        array $discountPercents = [],
    ): ResolvedPrice {
        $base = max(0.0, $baseAmount);

        // Step 2 — markup/MUP (R12.2). Both markup and MUP raise the base by
        // the configured percentage; a null type or value means no markup.
        $markedUp = $base;

        if ($markupType !== null && $markupValue !== null) {
            if (! Price::isValidMarkupType($markupType)) {
                throw new InvalidArgumentException(sprintf('Invalid markup type "%s".', $markupType));
            }

            $markedUp = $base * (1.0 + ($markupValue / 100.0));
        }

        // Step 3 — cumulative discounts (R12.5). Each discount reduces the
        // running amount by its percentage, compounding on the already-
        // discounted amount so order does not change the result.
        $final = $markedUp;

        foreach ($discountPercents as $percent) {
            $final *= (1.0 - ($this->normalizeDiscountPercent((float) $percent) / 100.0));
        }

        // Step 4 — never negative (R12.4). Cumulative discounts can only reduce
        // toward zero, but clamp defensively so the contract always holds.
        $final = max(0.0, $final);

        return new ResolvedPrice($base, max(0.0, $markedUp), $final, $this->tenantCurrency());
    }

    /**
     * The applicable base Price for a product: the Product-level price when one
     * exists, otherwise the Collection-level price (R12.1, R12.4). Returns null
     * when neither is configured.
     */
    private function resolveApplicablePrice(Product $product): ?Price
    {
        $productPrice = Price::query()
            ->where('level', Price::LEVEL_PRODUCT)
            ->where('ref_id', $product->getKey())
            ->first();

        if ($productPrice !== null) {
            return $productPrice;
        }

        return Price::query()
            ->where('level', Price::LEVEL_COLLECTION)
            ->where('ref_id', $product->collection_id)
            ->first();
    }

    /**
     * The percentages of every active discount applicable to this Product +
     * Buyer, resolved Rosetta-style (R12.5).
     *
     * A discount applies when BOTH hold:
     *   - Buyer scope: the discount has no buyer, OR it targets this Buyer.
     *   - Catalog scope: for each of category/collection/colour the discount
     *     pins, the Product must match that level (a NULL level = "any").
     *
     * Rosetta picks the single most-specific discount per customer_type family;
     * we keep our cumulative model (R12.5) but PREFER specificity by applying
     * at most one discount per `type` (customer / online) — the most specific
     * applicable one. That yields the Rosetta "most-specific wins" behaviour
     * for a given type while still combining the two independent types
     * cumulatively.
     *
     * @return array<int, float>
     */
    private function applicableDiscounts(Product $product, ?string $buyerType, ?int $buyerId): array
    {
        $categoryId = $product->collection?->category_id;
        $collectionId = $product->collection_id;
        $colourId = $product->colour_id;

        $candidates = Discount::query()
            ->where('is_active', true)
            // Buyer scope: unscoped OR this buyer.
            ->where(function ($q) use ($buyerType, $buyerId): void {
                $q->whereNull('buyer_id');
                if ($buyerType !== null && $buyerId !== null) {
                    $q->orWhere(function ($inner) use ($buyerType, $buyerId): void {
                        $inner->where('buyer_type', $buyerType)->where('buyer_id', $buyerId);
                    });
                }
            })
            // Catalog scope: each pinned level must match the product (NULL = any).
            ->where(fn ($q) => $q->whereNull('category_id')->orWhere('category_id', $categoryId))
            ->where(fn ($q) => $q->whereNull('collection_id')->orWhere('collection_id', $collectionId))
            ->where(fn ($q) => $q->whereNull('colour_id')->orWhere('colour_id', $colourId))
            ->get();

        // Keep only the most-specific applicable discount per type family.
        $bestByType = [];
        foreach ($candidates as $discount) {
            $type = $discount->type;
            if (! isset($bestByType[$type]) || $discount->specificity() > $bestByType[$type]->specificity()) {
                $bestByType[$type] = $discount;
            }
        }

        return array_map(
            static fn (Discount $d): float => (float) $d->percentage,
            array_values($bestByType),
        );
    }

    /**
     * Clamp a discount percentage into [0, 100] so a single discount can never
     * push the price negative or increase it; the never-negative contract
     * (R12.4) is additionally guaranteed by the final clamp.
     */
    private function normalizeDiscountPercent(float $percent): float
    {
        return max(0.0, min(100.0, $percent));
    }

    /**
     * The default currency used when no tenant currency can be resolved (R39.1),
     * e.g. when the pure computation core is exercised outside a booted app.
     */
    public const DEFAULT_CURRENCY = 'USD';

    /**
     * The Tenant's single configured currency (R39.1) — public accessor so
     * callers (e.g. the cart totals summary) can tag aggregate figures with the
     * same currency the resolver uses, without re-implementing the lookup.
     */
    public function currency(): string
    {
        return $this->tenantCurrency();
    }

    /**
     * The Tenant's single configured currency (R39.1), read from the active
     * tenancy context. Resolution is wrapped defensively so the pure
     * computation core keeps working outside a booted application / initialized
     * tenant (e.g. unit tests), falling back to {@see DEFAULT_CURRENCY}.
     */
    private function tenantCurrency(): string
    {
        try {
            if (function_exists('tenant') && function_exists('app') && app()->bound('config')) {
                /** @var string|null $currency */
                $currency = tenant('currency');

                if (is_string($currency) && $currency !== '') {
                    return $currency;
                }

                return (string) config('tenancy.default_currency', self::DEFAULT_CURRENCY);
            }
        } catch (\Throwable) {
            // Fall through to the default when no app/tenant context exists.
        }

        return self::DEFAULT_CURRENCY;
    }
}
