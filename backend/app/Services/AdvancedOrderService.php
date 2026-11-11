<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\FeatureDisabledException;
use App\Models\Company;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PlanFeature;
use App\Models\Stock;
use App\Models\StockPoint;
use App\Models\StockReservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Orchestrates the advanced order types and ties the stock-reservation
 * lifecycle to the order lifecycle (R23.1, R23.2, R23.3, R23.4, R23.5, design
 * "Advanced order types").
 *
 * The three advanced order types and their reservation behaviour:
 *
 *   • BLANKET ({@see Order::TYPE_BLANKET}) — defines scheduled releases
 *     (R23.1). Each release is modelled as a reservation carrying an
 *     `expires_at` so a release blocks stock until its scheduled window and is
 *     swept back to available when it lapses ({@see StockReservationService::releaseExpired()}).
 *   • ADVANCE ({@see Order::TYPE_ADVANCE}) — gated by the `advance_orders`
 *     feature flag (R23.2): the order is placed against future/blocked stock
 *     with a reservation, so the ordered quantity is blocked without moving the
 *     owner's on-hand quantity (R23.4). When the flag is disabled the request
 *     is denied with a 403 before any reservation is made.
 *   • DEPOT ({@see Order::TYPE_DEPOT}) — recorded at the stock-point (depot)
 *     level (R23.3): the reservation targets the stock row of a SPECIFIC stock
 *     point rather than the owner's primary point.
 *
 * The reserved stock is unavailable to other orders while the order is in
 * progress (R23.4); cancelling or expiring the order releases its reservations
 * back to available (R23.5) — handled here via {@see releaseReservations()} and
 * wired into the cancellation path.
 *
 * This service does NOT move owned quantity — it only blocks it via
 * {@see StockReservationService}. Actual stock movement remains the sole job of
 * {@see StockMovementService} at confirmation time.
 */
class AdvancedOrderService
{
    public function __construct(
        private readonly StockReservationService $reservations,
        private readonly SubscriptionGateService $gate,
    ) {}

    /**
     * Place an advanced order of the given type and reserve the stock it blocks
     * (R23.1–R23.4).
     *
     * The whole placement — order row, lines, and one reservation per line —
     * runs in ONE tenant-database transaction so it is all-or-nothing: if any
     * line cannot be reserved (would exceed available), nothing persists. An
     * ADVANCE order first passes the `advance_orders` feature gate (R23.2); a
     * DEPOT order reserves at the named stock point (R23.3); a BLANKET order's
     * lines may each carry a scheduled `expires_at` release window (R23.1).
     *
     * @param  Model  $buyer  The polymorphic buyer placing the order; the seller is resolved from it (R20.1, R20.2).
     * @param  string  $orderType  One of {@see Order::ORDER_TYPES} (not `standard`).
     * @param  list<array{product_id:int, quantity:string, stock_point_id?:int|null, expires_at?:\DateTimeInterface|string|null}>  $lines
     *                                                                                                                                     The order lines; each reserves `quantity` of the seller's stock for the
     *                                                                                                                                     product (at `stock_point_id` for a depot order), optionally expiring at
     *                                                                                                                                     `expires_at` (a blanket scheduled release — R23.1).
     * @param  array{total?:string, actor_id?:int|null}  $context
     *
     * @throws FeatureDisabledException  When an advance order is placed but the `advance_orders` flag is off (403).
     * @throws InvalidArgumentException  When the order type is unknown or `standard`.
     */
    public function place(Model $buyer, string $orderType, array $lines, array $context = []): Order
    {
        $this->assertAdvancedType($orderType);

        // Advance orders are gated by the `advance_orders` feature flag (R23.2):
        // a disabled flag denies the request with a 403 before anything is made.
        if ($orderType === Order::TYPE_ADVANCE) {
            $this->gate->assertFeatureEnabled(PlanFeature::ADVANCE_ORDERS);
        }

        if ($lines === []) {
            throw new InvalidArgumentException('An advanced order requires at least one line.');
        }

        $seller = $this->resolveSeller($buyer);

        return DB::connection('tenant')->transaction(function () use ($buyer, $seller, $orderType, $lines, $context): Order {
            $order = new Order([
                'status' => OrderStatus::PLACED,
                'order_type' => $orderType,
                'total' => $context['total'] ?? '0',
            ]);
            $order->buyer()->associate($buyer);
            $order->seller()->associate($seller);
            $order->save();

            foreach ($lines as $line) {
                $productId = (int) $line['product_id'];
                $quantity = (string) $line['quantity'];

                $order->lines()->create([
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => '0',
                    'discount' => '0',
                ]);

                // Resolve the stock row to block: a DEPOT order reserves at the
                // named stock point (R23.3); otherwise the seller's primary
                // point. Reserving blocks the quantity without moving on-hand
                // (R23.4).
                $stock = $this->resolveStock(
                    $seller,
                    $productId,
                    $orderType === Order::TYPE_DEPOT ? ($line['stock_point_id'] ?? null) : null,
                );

                $this->reservations->reserve(
                    $stock,
                    $quantity,
                    (int) $order->getKey(),
                    ['expires_at' => $line['expires_at'] ?? null],
                );
            }

            return $order;
        });
    }

    /**
     * Release every reservation an advanced order holds, returning its blocked
     * stock to available (R23.5). Called when the order is cancelled or
     * expires. Returns the number of reservations released. Idempotent — a
     * second call releases nothing because the first already flipped them.
     */
    public function releaseReservations(Order|int $order): int
    {
        return $this->reservations->releaseForOrder($order);
    }

    /**
     * Resolve the Seller the order is placed against from the Buyer (R20.1,
     * R20.2), mirroring {@see CartSubmissionService}: a Distributor buys from
     * the Company; a Dealer buys from its serving Distributor.
     */
    private function resolveSeller(Model $buyer): Model
    {
        if ($buyer instanceof Distributor) {
            return Company::query()->firstOrFail();
        }

        if ($buyer instanceof Dealer) {
            return $buyer->distributor()->firstOrFail();
        }

        throw new InvalidArgumentException(sprintf(
            'An advanced order buyer must be a Distributor or Dealer, got [%s].',
            $buyer->getMorphClass(),
        ));
    }

    /**
     * Resolve the stock row to reserve against for a (seller, product) — at a
     * SPECIFIC stock point for a depot order (R23.3), otherwise the seller's
     * primary point (lowest `sort_order`, then id). A reservation blocks an
     * existing stock row, so the row must already exist (there is nothing to
     * block otherwise).
     */
    private function resolveStock(Model $seller, int $productId, ?int $stockPointId): Stock
    {
        $pointId = $stockPointId ?? $this->primaryStockPointId($seller);

        if ($pointId === null) {
            throw new RuntimeException(sprintf(
                'The order seller [%s #%d] has no stock point to reserve against.',
                $seller->getMorphClass(),
                (int) $seller->getKey(),
            ));
        }

        $stock = Stock::query()
            ->where('stockable_type', $seller->getMorphClass())
            ->where('stockable_id', (int) $seller->getKey())
            ->where('product_id', $productId)
            ->where('stock_point_id', $pointId)
            ->first();

        if ($stock === null) {
            throw new RuntimeException(sprintf(
                'No stock row exists for product [%d] at stock point [%d] to reserve against.',
                $productId,
                $pointId,
            ));
        }

        return $stock;
    }

    /**
     * The id of an owner's primary stock point: lowest `sort_order`, tie-broken
     * by lowest id. Null when the owner owns none. Mirrors the resolution in
     * {@see OrderConfirmationService}.
     */
    private function primaryStockPointId(Model $owner): ?int
    {
        $point = StockPoint::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', (int) $owner->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        return $point === null ? null : (int) $point->getKey();
    }

    /**
     * Assert the order type is a recognised ADVANCED type (not `standard`),
     * which is what this service handles (R23.1–R23.3).
     */
    private function assertAdvancedType(string $orderType): void
    {
        if (! in_array($orderType, Order::ORDER_TYPES, true)) {
            throw new InvalidArgumentException(sprintf('Unknown order type [%s].', $orderType));
        }

        if ($orderType === Order::TYPE_STANDARD) {
            throw new InvalidArgumentException('A standard order is placed through the standard ordering flow, not the advanced-order service.');
        }
    }
}
