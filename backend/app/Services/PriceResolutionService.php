<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Dealer;
use App\Models\Discount;
use App\Models\Distributor;
use App\Models\Price;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Order-line price resolution (R12.1–R12.5, R39.1).
 *
 *   1. BASE price: the product-level Price row; else the product's own `price`
 *      (maintained on the product form); else the collection-level Price row.
 *   2. MARKUP/MUP from the Price row, if any (R12.2).
 *   3. ONE discount: of all active discounts that apply, only the one with the
 *      highest priority is used (Dealer > Distributor > Product > General).
 *      Discounts are never combined. A discount is a percentage or a fixed
 *      amount. Ties at the same level go to the more specific catalog scope
 *      (product > colour > collection > category), then the larger saving.
 *      Only discounts owned by the product's owner apply, plus, for a dealer
 *      buyer, discounts owned by the dealer's distributor.
 *   4. CLAMP so the price is NEVER negative (R12.4).
 *
 * Amounts are in the tenant's single currency (R39.1). {@see computePrice()}
 * is pure; {@see resolveForProduct()} / {@see resolveMany()} do the lookups.
 */
class PriceResolutionService
{
    /** Buyer-type spellings accepted on discounts (short form and class name). */
    private const DEALER_TYPES = ['dealer', Dealer::class];

    private const DISTRIBUTOR_TYPES = ['distributor', Distributor::class];

    /**
     * Resolve the price of one product for a Buyer.
     *
     * @param  string|null  $buyerType  `distributor` / `dealer` (or the model class), or null.
     *
     * @throws InvalidArgumentException when no base price is configured.
     */
    public function resolveForProduct(Product $product, ?string $buyerType = null, ?int $buyerId = null): ResolvedPrice
    {
        $resolved = $this->resolveMany(collect([$product]), $buyerType, $buyerId, strict: true);

        return $resolved[(int) $product->getKey()];
    }

    /**
     * Resolve many products for one Buyer with a fixed number of queries
     * (used for product listings). Products without any base price are
     * skipped unless $strict.
     *
     * @param  Collection<int, Product>  $products
     * @return array<int, ResolvedPrice> keyed by product id
     *
     * @throws InvalidArgumentException when $strict and a product has no base price.
     */
    public function resolveMany(Collection $products, ?string $buyerType = null, ?int $buyerId = null, bool $strict = false): array
    {
        if ($products->isEmpty()) {
            return [];
        }

        $products = EloquentCollection::make($products->all())->loadMissing('collection');
        $productPrices = Price::query()->where('level', Price::LEVEL_PRODUCT)
            ->whereIn('ref_id', $products->modelKeys())->get()->keyBy('ref_id');
        $collectionPrices = Price::query()->where('level', Price::LEVEL_COLLECTION)
            ->whereIn('ref_id', $products->pluck('collection_id')->unique()->all())->get()->keyBy('ref_id');
        [$dealerId, $distributorId] = $this->buyerChain($buyerType, $buyerId);
        $candidates = $this->candidateDiscounts($dealerId, $distributorId);

        $out = [];
        foreach ($products as $product) {
            $price = $productPrices->get($product->getKey());
            $base = $price?->amount;
            if ($base === null && $product->price !== null) {
                $base = $product->price;
            }
            if ($base === null) {
                $price = $collectionPrices->get($product->collection_id);
                $base = $price?->amount;
            }
            if ($base === null) {
                if ($strict) {
                    throw new InvalidArgumentException(sprintf(
                        'No applicable price configured for product %d (no product price, product-level or collection-level price).',
                        (int) $product->getKey(),
                    ));
                }

                continue;
            }

            $markupType = $price?->markup_type;
            $markupValue = $price?->markup_value === null ? null : (float) $price->markup_value;
            $markedUp = $this->markUp(max(0.0, (float) $base), $markupType, $markupValue);
            $best = $this->bestDiscount($candidates, $product, $dealerId, $distributorId, $markedUp);

            $out[(int) $product->getKey()] = $this->computePrice((float) $base, $markupType, $markupValue, $best['discount'] ?? null, $best['level'] ?? null);
        }

        return $out;
    }

    /**
     * Pure price computation (R12.2, R12.4, R12.5, R39.1): markup, then at most
     * ONE discount, clamped at zero.
     *
     * @param  float  $baseAmount  the resolved base price (>= 0).
     * @param  string|null  $markupType  `markup`, `mup`, or null (R12.2).
     * @param  float|null  $markupValue  markup/MUP percentage, or null.
     * @param  Discount|null  $discount  the single discount to apply, if any.
     * @param  string|null  $level  the priority level the discount was chosen at.
     */
    public function computePrice(
        float $baseAmount,
        ?string $markupType = null,
        ?float $markupValue = null,
        ?Discount $discount = null,
        ?string $level = null,
    ): ResolvedPrice {
        $base = max(0.0, $baseAmount);
        $markedUp = $this->markUp($base, $markupType, $markupValue);
        $saving = $discount?->savingOn($markedUp) ?? 0.0;
        $final = max(0.0, $markedUp - $saving);

        return new ResolvedPrice(
            $base,
            $markedUp,
            $final,
            $this->tenantCurrency(),
            discountAmount: $saving,
            discountId: $discount?->getKey() === null ? null : (int) $discount->getKey(),
            discountLevel: $discount === null ? null : ($level ?? Discount::LEVEL_GENERAL),
            discountType: $discount?->value_type ?? ($discount === null ? null : Discount::VALUE_PERCENT),
            discountValue: $discount === null ? null : (float) ($discount->isFixed() ? $discount->amount : $discount->percentage),
        );
    }

    private function markUp(float $base, ?string $markupType, ?float $markupValue): float
    {
        if ($markupType === null || $markupValue === null) {
            return $base;
        }
        if (! Price::isValidMarkupType($markupType)) {
            throw new InvalidArgumentException(sprintf('Invalid markup type "%s".', $markupType));
        }

        return max(0.0, $base * (1.0 + ($markupValue / 100.0)));
    }

    /**
     * [dealer id, distributor id] for the buyer: a dealer's distributor is its
     * serving distributor; a distributor buyer has no dealer.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function buyerChain(?string $buyerType, ?int $buyerId): array
    {
        if ($buyerId === null || $buyerType === null) {
            return [null, null];
        }
        if (in_array($buyerType, self::DEALER_TYPES, true)) {
            $distributorId = Dealer::query()->whereKey($buyerId)->value('distributor_id');

            return [$buyerId, $distributorId === null ? null : (int) $distributorId];
        }
        if (in_array($buyerType, self::DISTRIBUTOR_TYPES, true)) {
            return [null, $buyerId];
        }

        return [null, null];
    }

    /**
     * Active discounts whose buyer scope fits: unscoped, this dealer, or this
     * (or the dealer's) distributor.
     *
     * @return Collection<int, Discount>
     */
    private function candidateDiscounts(?int $dealerId, ?int $distributorId): Collection
    {
        return Discount::query()
            ->where('is_active', true)
            ->where(function ($q) use ($dealerId, $distributorId): void {
                $q->whereNull('buyer_id');
                if ($dealerId !== null) {
                    $q->orWhere(fn ($i) => $i->whereIn('buyer_type', self::DEALER_TYPES)->where('buyer_id', $dealerId));
                }
                if ($distributorId !== null) {
                    $q->orWhere(fn ($i) => $i->whereIn('buyer_type', self::DISTRIBUTOR_TYPES)->where('buyer_id', $distributorId));
                }
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * The single highest-priority discount for this product, or null.
     *
     * @param  Collection<int, Discount>  $candidates
     * @return array{discount: Discount, level: string}|null
     */
    private function bestDiscount(Collection $candidates, Product $product, ?int $dealerId, ?int $distributorId, float $markedUp): ?array
    {
        $categoryId = $product->collection?->category_id;
        $best = null;
        $bestKey = null;

        $productOwner = $product->owner_distributor_id === null ? null : (int) $product->owner_distributor_id;
        $servingDistributor = $dealerId !== null ? $distributorId : null;

        foreach ($candidates as $discount) {
            // Each owner prices its own products; a distributor may also
            // discount anything for its own dealers.
            $discountOwner = $discount->owner_distributor_id;
            if ($discountOwner !== $productOwner && ($discountOwner === null || $discountOwner !== $servingDistributor)) {
                continue;
            }

            if (($discount->product_id !== null && $discount->product_id !== (int) $product->getKey())
                || ($discount->colour_id !== null && $discount->colour_id !== (int) $product->colour_id)
                || ($discount->collection_id !== null && $discount->collection_id !== (int) $product->collection_id)
                || ($discount->category_id !== null && $discount->category_id !== (int) $categoryId)) {
                continue;
            }

            $level = match (true) {
                $discount->buyer_id !== null && in_array($discount->buyer_type, self::DEALER_TYPES, true) && (int) $discount->buyer_id === $dealerId => Discount::LEVEL_DEALER,
                $discount->buyer_id !== null && in_array($discount->buyer_type, self::DISTRIBUTOR_TYPES, true) && (int) $discount->buyer_id === $distributorId => Discount::LEVEL_DISTRIBUTOR,
                $discount->hasCatalogScope() => Discount::LEVEL_PRODUCT,
                default => Discount::LEVEL_GENERAL,
            };

            // Compare (level, catalog specificity, saving, newest) - higher wins.
            $key = [Discount::LEVEL_RANK[$level], $discount->catalogSpecificity(), round($discount->savingOn($markedUp), 4), (int) $discount->getKey()];
            if ($bestKey === null || $key > $bestKey) {
                $best = ['discount' => $discount, 'level' => $level];
                $bestKey = $key;
            }
        }

        return $best;
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
