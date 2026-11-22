<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Controllers\PriceController;
use App\Models\Collection;
use App\Models\Discount;
use App\Models\Price;
use App\Models\Product;
use App\Support\CatalogOwner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Price & discount catalog management (R12.1, R12.2, R12.3).
 *
 * Focused single-responsibility service the thin {@see PriceController}
 * delegates to. It owns the configuration side of pricing — creating and
 * listing Prices (product/collection level, markup/MUP) and Discounts (the two
 * supported `customer`/`online` types). The RUNTIME resolution of an order-line
 * price lives in {@see PriceResolutionService}; this service only persists the
 * inputs that resolver reads.
 */
class PriceService
{
    /**
     * Paginated list of configured prices for the active tenant, ordered for
     * stable paging.
     *
     * @return LengthAwarePaginator<int, Price>
     */
    public function paginatePrices(int $ownerKey = CatalogOwner::COMPANY, int $perPage = 25): LengthAwarePaginator
    {
        return Price::query()
            ->where(function (Builder $q) use ($ownerKey): void {
                $q->where(fn (Builder $p) => $p->where('level', Price::LEVEL_PRODUCT)
                    ->whereIn('ref_id', Product::query()->withoutGlobalScopes()->ownedBy($ownerKey)->select('id')))
                    ->orWhere(fn (Builder $p) => $p->where('level', Price::LEVEL_COLLECTION)
                        ->whereIn('ref_id', Collection::query()->withoutGlobalScopes()->ownedBy($ownerKey)->select('id')));
            })
            ->orderBy('level')
            ->orderBy('ref_id')
            ->paginate($perPage);
    }

    /**
     * Paginated list of configured discounts for the active tenant.
     *
     * @return LengthAwarePaginator<int, Discount>
     */
    public function paginateDiscounts(int $ownerKey = CatalogOwner::COMPANY, int $perPage = 25): LengthAwarePaginator
    {
        return Discount::query()
            ->ownedBy($ownerKey)
            ->orderBy('type')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Create a Price at the product or collection level (R12.1, R12.2). The
     * level/markup_type allow-lists are enforced by the request + model/enum.
     *
     * @param  array{level: string, ref_id: int, amount: numeric-string|float|int, markup_type?: string|null, markup_value?: numeric-string|float|int|null}  $attributes
     */
    public function createPrice(array $attributes): Price
    {
        return Price::create($attributes);
    }

    /**
     * Create a Discount of a supported type (R12.3). The model's setter guard
     * rejects any type other than `customer`/`online`, so an invalid type can
     * never be persisted even if request validation is bypassed.
     *
     * @param  array{type: string, percentage: numeric-string|float|int, buyer_type?: string|null, buyer_id?: int|null, is_active?: bool}  $attributes
     */
    public function createDiscount(array $attributes, ?int $ownerDistributorId = null): Discount
    {
        $discount = new Discount($this->normalizeDiscount($attributes));
        $discount->owner_distributor_id = $ownerDistributorId;
        $discount->save();

        return $discount;
    }

    /**
     * Owner key of a price row: the owner of the product or collection it
     * prices (0 = Company).
     */
    public function priceOwnerKey(Price $price): int
    {
        $table = $price->level === Price::LEVEL_PRODUCT ? 'products' : 'collections';

        return (int) DB::connection($price->getConnectionName())->table($table)
            ->where('id', $price->ref_id)->value('owner_key');
    }

    /**
     * Update an existing Discount (percentage/scope/active) (R12.3, R12.5).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateDiscount(Discount $discount, array $attributes): Discount
    {
        $discount->fill($this->normalizeDiscount($attributes, $discount))->save();

        return $discount->refresh();
    }

    /**
     * Keep the value columns consistent with the value type: a fixed discount
     * stores its `amount` (percentage 0); a percent discount stores its
     * `percentage` (amount null). A buyer scope is kept or cleared as a pair.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizeDiscount(array $attributes, ?Discount $existing = null): array
    {
        $type = $attributes['value_type'] ?? $existing?->value_type ?? Discount::VALUE_PERCENT;
        $attributes['value_type'] = $type;
        if ($type === Discount::VALUE_FIXED) {
            $attributes['percentage'] = 0;
        } else {
            $attributes['amount'] = null;
            if (array_key_exists('percentage', $attributes) && $attributes['percentage'] === null) {
                unset($attributes['percentage']);
            }
        }
        if (array_key_exists('buyer_type', $attributes) && empty($attributes['buyer_type'])) {
            $attributes['buyer_type'] = null;
            $attributes['buyer_id'] = null;
        }

        return $attributes;
    }

    /** Delete a Discount. */
    public function deleteDiscount(Discount $discount): void
    {
        $discount->delete();
    }

    /** Delete a Price configuration. */
    public function deletePrice(Price $price): void
    {
        $price->delete();
    }
}
