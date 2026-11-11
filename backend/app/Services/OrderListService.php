<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Order history listing, scoped to the viewer (R20, R21, R33).
 *
 * The order list a user sees depends on who they are relative to each order's
 * polymorphic Buyer/Seller (R20.1, R20.2):
 *
 *   • COMPANY (no party context): sees every order in the tenant — it either
 *     sold the order (seller = Company) or it owns the whole distribution tree.
 *   • DISTRIBUTOR has TWO distinct listings:
 *       - "my orders"     → orders it PLACED as the buyer against the Company
 *                           (buyer = this distributor);
 *       - "dealer orders" → orders its DEALERS placed against its stock
 *                           (seller = this distributor).
 *   • DEALER: sees only the orders it placed (buyer = this dealer).
 *
 * Every query runs on the active tenant connection and eager-loads buyer/seller
 * and lines so the history renders without an N+1 (R38.1).
 */
class OrderListService
{
    /**
     * Orders where the given party is the BUYER — "orders I placed" (R20.1,
     * R20.2). Used for every party's "my orders" listing.
     *
     * @param  "distributor"|"dealer"  $partyType
     * @return LengthAwarePaginator<int, Order>
     */
    public function placedBy(string $partyType, int $partyId, int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->base($filters)
            ->where('buyer_type', $this->classFor($partyType))
            ->where('buyer_id', $partyId)
            ->paginate($perPage);
    }

    /**
     * Every order placed by a party TYPE, regardless of which specific party —
     * e.g. all orders placed by ANY distributor (R20.1). Backs the Company's
     * "Distributor Orders" listing, which oversees orders across the whole
     * distributor tier rather than a single distributor.
     *
     * @param  "distributor"|"dealer"  $partyType
     * @return LengthAwarePaginator<int, Order>
     */
    public function placedByType(string $partyType, int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->base($filters)
            ->where('buyer_type', $this->classFor($partyType))
            ->paginate($perPage);
    }

    /**
     * Orders where the given party is the SELLER — "orders placed against me"
     * (R20.2). Used for a Distributor's "dealer orders" listing (its dealers'
     * orders against its stock).
     *
     * @param  "distributor"|"company"  $partyType
     * @return LengthAwarePaginator<int, Order>
     */
    public function soldBy(string $partyType, int $partyId, int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->base($filters)
            ->where('seller_type', $this->classFor($partyType))
            ->where('seller_id', $partyId)
            ->paginate($perPage);
    }

    /**
     * Every order in the tenant — the Company's full history view (R33).
     *
     * @return LengthAwarePaginator<int, Order>
     */
    public function all(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->base($filters)->paginate($perPage);
    }

    /**
     * Shared base query: newest first, with buyer/seller and lines eager-loaded
     * so the list renders party names and line counts without an N+1 (R38.1).
     *
     * @return Builder<Order>
     */
    private function base(array $filters = []): Builder
    {
        // `lines.product` carries the ordered product's name/SKU so listings can
        // show WHAT was ordered without an N+1 (the full detail is on show()).
        return Order::query()
            ->with(['buyer', 'seller', 'lines.product:id,sku,name'])
            ->when(! empty($filters['status']), fn (Builder $q) => $q->whereIn('status', (array) $filters['status']))
            ->when(! empty($filters['order_type']), fn (Builder $q) => $q->where('order_type', $filters['order_type']))
            ->latest('id');
    }

    /**
     * Map a short party type to its morph class (the FQCN stored in the
     * buyer_type/seller_type columns — no app morph map is enforced).
     */
    private function classFor(string $partyType): string
    {
        return match ($partyType) {
            'company' => Company::class,
            'distributor' => Distributor::class,
            'dealer' => Dealer::class,
            default => $partyType,
        };
    }
}
