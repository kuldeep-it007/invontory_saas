<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Discount;
use App\Models\Price;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Price & discount catalog management (R12.1, R12.2, R12.3).
 *
 * Focused single-responsibility service the thin {@see \App\Http\Controllers\PriceController}
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
    public function paginatePrices(int $perPage = 25): LengthAwarePaginator
    {
        return Price::query()
            ->orderBy('level')
            ->orderBy('ref_id')
            ->paginate($perPage);
    }

    /**
     * Paginated list of configured discounts for the active tenant.
     *
     * @return LengthAwarePaginator<int, Discount>
     */
    public function paginateDiscounts(int $perPage = 25): LengthAwarePaginator
    {
        return Discount::query()
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
    public function createDiscount(array $attributes): Discount
    {
        return Discount::create($attributes);
    }

    /**
     * Update an existing Discount (percentage/scope/active) (R12.3, R12.5).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateDiscount(Discount $discount, array $attributes): Discount
    {
        $discount->fill($attributes)->save();

        return $discount->refresh();
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
