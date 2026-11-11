<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\OrderPlacedMail;
use App\Models\Company;
use App\Models\Dealer;
use App\Models\Notification;
use App\Models\Distributor;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderLineAllocation;
use App\Models\OrderStatus;
use App\Models\PlanLimit;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

/**
 * Cart → Order submission service (R20.1–R20.4).
 *
 * The focused, single-responsibility service the thin
 * {@see \App\Http\Controllers\OrderController} delegates to for turning a
 * submitted cart into a durable Order. A cart accumulates multiple lines before
 * submission (R20.3); submitting a cart of N entries creates EXACTLY ONE
 * {@see Order} with one {@see OrderLine} per cart entry — cart-to-order fidelity
 * (R20.4).
 *
 * **Buyer → Seller resolution (R20.1, R20.2).** The buyer determines the seller
 * the order is placed against:
 *   • a {@see Distributor} buyer orders against the single tenant {@see Company}
 *     catalogue — seller = Company (R20.1);
 *   • a {@see Dealer} buyer orders against its serving {@see Distributor}'s
 *     stock — seller = that Dealer's Distributor (R20.2).
 *
 * **Pricing.** Each line's per-unit price and discount are resolved at
 * submission time through {@see PriceResolutionService} in the tenant's single
 * currency (R12.4, R12.5, R39.1); the order `total` is summed from the priced
 * lines. The whole submission is one atomic transaction so a cart never
 * produces a partially-created order (R35.2).
 *
 * **Quota.** The monthly order quota (`max_orders_per_month`) is enforced BEFORE
 * the order is created (R5.3); exceeding it throws a 403 naming the quota.
 *
 * The order is created in the {@see OrderStatus::PLACED} state — a submitted
 * cart is a placed order awaiting the seller's approval/confirmation (R21.1,
 * R21.2); the Draft state is reserved for a not-yet-submitted cart.
 *
 * **Notification.** AFTER the submission transaction commits, the order-placed
 * email is queued to the buyer's contact address (R27.2) — queued post-commit,
 * like the order-confirmed notification, so a rolled-back submission never
 * notifies (R35.2).
 */
class CartSubmissionService
{
    public function __construct(
        private readonly PriceResolutionService $prices,
        private readonly QuotaService $quota,
        private readonly WhiteLabelService $whiteLabel,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Submit a cart, creating one Order with one Order_Line per entry (R20.4).
     *
     * @param  Distributor|Dealer  $buyer    The Buyer placing the order (R20.1, R20.2).
     * @param  array<int, array{product_id: int, quantity: string|int|float}>  $entries
     *         The cart entries; each becomes exactly one order line.
     * @param  string  $orderType  The order flow discriminator (R23); defaults to `standard`.
     *
     * @throws InvalidArgumentException            When the cart is empty or a product is unknown.
     * @throws \App\Exceptions\QuotaExceededException When the monthly order quota is reached (403).
     */
    public function submit(Model $buyer, array $entries, string $orderType = 'standard'): Order
    {
        if ($entries === []) {
            throw new InvalidArgumentException('Cannot submit an empty cart; at least one line is required.');
        }

        $this->assertBuyer($buyer);

        // Enforce the monthly order quota BEFORE creating the order (R5.3).
        $this->quota->assertAllows(PlanLimit::MAX_ORDERS_PER_MONTH);

        [$sellerType, $sellerId] = $this->resolveSeller($buyer);
        $buyerType = $buyer->getMorphClass();
        $buyerId = (int) $buyer->getKey();

        // The short morph alias (`distributor`/`dealer`) the pricing layer uses
        // to match buyer-scoped discounts (see PriceResolutionService + the
        // discount `buyer_type` convention, R12.5).
        $pricingBuyerType = $buyer instanceof Distributor ? 'distributor' : 'dealer';

        // Resolve the tenant brand name up front, while the active tenant
        // connection is guaranteed, so the post-commit notification does not
        // depend on the default-connection state after central-backed quota
        // reads (R3.3, R27.2).
        $brandName = $this->whiteLabel->get()['brand_name'] ?? null;

        $order = DB::connection('tenant')->transaction(function () use (
            $entries,
            $buyerType,
            $buyerId,
            $sellerType,
            $sellerId,
            $pricingBuyerType,
            $orderType,
        ): Order {
            $order = Order::create([
                'buyer_type' => $buyerType,
                'buyer_id' => $buyerId,
                'seller_type' => $sellerType,
                'seller_id' => $sellerId,
                'status' => OrderStatus::PLACED,
                'order_type' => $orderType,
                'total' => 0,
            ]);

            $total = 0.0;

            // One Order_Line per cart entry — cart-to-order fidelity (R20.4).
            foreach ($entries as $entry) {
                $product = $this->resolveProduct((int) $entry['product_id']);
                $quantity = $this->normalizeQuantity($entry['quantity']);

                $resolved = $this->prices->resolveForProduct($product, $pricingBuyerType, $buyerId);

                $unitPrice = $resolved->final;
                $discount = max(0.0, $resolved->markedUp - $resolved->final);

                $line = OrderLine::create([
                    'order_id' => $order->getKey(),
                    'product_id' => $product->getKey(),
                    'quantity' => $quantity,
                    'unit_price' => round($unitPrice, 2),
                    'discount' => round($discount, 2),
                ]);

                // Persist the per-stock-point source split, if the cart line
                // carried one, so confirmation decrements each chosen seller
                // point (R16, R20). Entries with no allocations fulfil from the
                // seller's primary point.
                $this->createAllocations($line, $entry['allocations'] ?? null);

                $total += $unitPrice * (float) $quantity;
            }

            // The order total is the sum of its priced lines in the tenant's
            // single currency (R39.1).
            $order->total = round($total, 2);
            $order->save();

            return $order;
        });

        // A new order exists this month: invalidate the cached monthly count
        // so the next quota check recomputes (R5.6).
        $this->quota->invalidate(PlanLimit::MAX_ORDERS_PER_MONTH);

        // After commit: queue the order-placed notification (R27.2). Done
        // outside the transaction so a rolled-back submission never notifies.
        // The brand name was resolved up front on the active tenant connection.
        $this->queueOrderPlacedNotification($order, $buyer, $brandName);

        // Record the in-app order-placed notification mirror (R27.6) and, where
        // enabled, the optional SMS/WhatsApp channels (R27.5) — post-commit so a
        // rolled-back submission never notifies.
        $this->recordOrderPlacedNotification($order, $buyer);

        return $order->load('lines');
    }

    /**
     * Queue the order-placed email to the buyer's contact address using the
     * active tenant's sender identity (R27.2, R3.3). No-op when the buyer has
     * no contact email on record, so a buyer without an address never blocks
     * the submission.
     */
    private function queueOrderPlacedNotification(Order $order, Model $buyer, ?string $brandName): void
    {
        $contactEmail = $buyer->contact_email ?? null;

        if (empty($contactEmail)) {
            return;
        }

        Mail::to($contactEmail)->queue(new OrderPlacedMail(
            buyerName: (string) ($buyer->name ?? ''),
            orderId: (int) $order->getKey(),
            amount: (string) $order->total,
            brandName: $brandName,
        ));
    }

    /**
     * Record the in-app order-placed notification for the user that placed the
     * order, and offer the optional SMS/WhatsApp channels to the buyer's phone
     * where the `whatsapp_notifications` flag is enabled (R27.2, R27.5, R27.6).
     * Best-effort: it runs after commit and never fails the submission.
     */
    private function recordOrderPlacedNotification(Order $order, Model $buyer): void
    {
        $userId = auth()->id();

        if ($userId === null) {
            return;
        }

        $this->notifications->notify(
            userId: (int) $userId,
            type: Notification::TYPE_ORDER_PLACED,
            title: 'Order placed',
            body: sprintf('Order #%d placed for %s.', (int) $order->getKey(), (string) $order->total),
            data: [
                'order_id' => (int) $order->getKey(),
                'amount' => (string) $order->total,
            ],
            phone: $buyer->contact_phone ?? null,
        );
    }

    /**
     * The Buyer must be a Distributor or a Dealer (R20.1, R20.2); any other
     * owner type cannot place an order.
     */
    private function assertBuyer(Model $buyer): void
    {
        if (! $buyer instanceof Distributor && ! $buyer instanceof Dealer) {
            throw new InvalidArgumentException(sprintf(
                'An order buyer must be a Distributor or Dealer, got [%s].',
                $buyer::class,
            ));
        }
    }

    /**
     * Resolve the Seller for a Buyer (R20.1, R20.2):
     *   • a Distributor sells-from the single tenant Company (R20.1);
     *   • a Dealer sells-from its serving Distributor (R20.2).
     *
     * @return array{0: string, 1: int} [seller morph type, seller id]
     */
    private function resolveSeller(Model $buyer): array
    {
        if ($buyer instanceof Distributor) {
            // Distributor orders are placed against the Company catalogue (R20.1).
            $company = Company::query()->firstOrFail();

            return [$company->getMorphClass(), (int) $company->getKey()];
        }

        // Dealer orders are placed against the serving Distributor's stock
        // (R20.2); the Dealer belongs to exactly one Distributor (R10.3).
        /** @var Dealer $buyer */
        $distributor = $buyer->distributor()->firstOrFail();

        return [$distributor->getMorphClass(), (int) $distributor->getKey()];
    }

    /**
     * Load the ordered Product from the active tenant database, rejecting an
     * unknown product id with a descriptive error.
     */
    private function resolveProduct(int $productId): Product
    {
        $product = Product::query()->find($productId);

        if ($product === null) {
            throw new InvalidArgumentException(sprintf('Unknown product id [%d] in cart.', $productId));
        }

        return $product;
    }

    /**
     * Create the per-stock-point source allocations for an order line from the
     * cart entry's `allocations` (R16, R20). Each valid `{stock_point_id,
     * quantity}` becomes one {@see OrderLineAllocation}; a null/empty list
     * leaves the line sourced from the seller's primary stock point.
     *
     * @param  array<int, array{stock_point_id?: mixed, quantity?: mixed}>|null  $allocations
     */
    private function createAllocations(OrderLine $line, ?array $allocations): void
    {
        if ($allocations === null || $allocations === []) {
            return;
        }

        foreach ($allocations as $entry) {
            $pointId = (int) ($entry['stock_point_id'] ?? 0);
            $qty = (float) ($entry['quantity'] ?? 0);

            if ($pointId <= 0 || $qty <= 0) {
                continue;
            }

            OrderLineAllocation::create([
                'order_line_id' => (int) $line->getKey(),
                'stock_point_id' => $pointId,
                'quantity' => number_format($qty, 3, '.', ''),
            ]);
        }
    }

    /**
     * Normalize a cart entry quantity to a positive DECIMAL(15,3) string so it
     * is stored exactly on the order line (R13.3). A non-positive quantity is
     * rejected — a cart line must order at least some quantity.
     */
    private function normalizeQuantity(string|int|float $quantity): string
    {
        $value = (float) $quantity;

        if ($value <= 0) {
            throw new InvalidArgumentException('Each cart line quantity must be greater than zero.');
        }

        return number_format($value, 3, '.', '');
    }
}
