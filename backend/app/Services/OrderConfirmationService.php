<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\OrderConfirmedMail;
use App\Models\Company;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\StockPoint;
use App\Models\Tenant;
use App\Services\Stock\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Synchronous, transactional order-confirmation orchestrator (R16, R21.3,
 * R21.5, design "Confirmation (synchronous, transactional)").
 *
 * {@see confirm()} performs the whole confirmation inside ONE tenant-database
 * transaction so stock movements, the invoice, the account-ledger purchase, and
 * the Order's Confirmed state are all-or-nothing — any failure rolls the entire
 * confirmation back and nothing persists (R16.4, R35.2, R35.3). The pipeline,
 * in order, matches the design pseudocode:
 *
 *   1. {@see OrderStateMachine::assertTransition()} — assert the `confirm`
 *      transition is legal from the order's current state, rejecting an illegal
 *      move with a 422 BEFORE any side effect so the order is left unchanged
 *      (R21.4).
 *   2. {@see buildMovements()} — compose the seller decrement (+ buyer increment
 *      where the buyer holds stock) for every order line (R16.1–R16.3).
 *   3. {@see StockMovementService::apply()} — apply the movements with FOR UPDATE
 *      row locks, all-or-nothing, honouring the per-tenant backorder toggle
 *      (R16.4, R16.5, R17.1, R17.2).
 *   4. {@see CreditEnforcementService::assertCreditWithinLimit()} — block the
 *      confirmation when the projected outstanding exceeds the credit limit,
 *      evaluated only at confirmation time (R21.3, R26.2).
 *   5. {@see InvoiceService::generate()} — generate EXACTLY one invoice under the
 *      SELLER's own series, synchronously inside the transaction (R21.5).
 *   6. {@see AccountLedgerService::appendPurchase()} — append the purchase to the
 *      buyer's immutable transaction ledger (R25.1).
 *   7. Persist the Confirmed status.
 *
 * AFTER the transaction commits, the order-confirmed notification is queued
 * (R27.3) — queued post-commit so a rolled-back confirmation never notifies.
 *
 * ## Stock movement rules (R16.1–R16.3)
 *
 *   • Distributor order (seller = Company): decrement Company stock AND
 *     increment Distributor stock by each line quantity (R16.1).
 *   • Dealer order (seller = Distributor): decrement Distributor stock by each
 *     line quantity (R16.2); additionally increment Dealer stock ONLY when
 *     dealer stock-holding is enabled for the tenant (R16.3). With it disabled
 *     the Dealer is a pure consumer and holds no stock, so no buyer increment is
 *     built (R13.6).
 *
 * ## Stock-point resolution
 *
 * Order lines carry a product + quantity, not a stock point, so each owner's
 * PRIMARY stock point (lowest `sort_order`, then lowest id) is used: the seller
 * is decremented from its primary point and the buyer is incremented into its
 * primary point. A seller with no stock point cannot fulfil a confirmation and
 * is rejected; a stock-holding buyer with no stock point has one provisioned so
 * the increment has a home.
 */
class OrderConfirmationService
{
    public function __construct(
        private readonly OrderStateMachine $stateMachine,
        private readonly StockMovementService $movements,
        private readonly CreditEnforcementService $credit,
        private readonly InvoiceService $invoices,
        private readonly AccountLedgerService $ledger,
        private readonly WhiteLabelService $whiteLabel,
        private readonly ?ReportCache $reportCache = null,
        private readonly ?NotificationService $notifications = null,
    ) {}

    /**
     * Confirm an order: run the full transactional pipeline and, after commit,
     * queue the order-confirmed notification (R16, R21.3, R21.5, R27.3).
     *
     * @param  Order  $order  The order being confirmed (its buyer/seller morphs resolve the parties).
     * @param  array{actor_id?: int|null, credit_override_reason?: string|null}  $context
     *                                                                                     Optional context. `actor_id` is recorded on each stock-ledger and
     *                                                                                     account-ledger entry (R15.1); `credit_override_reason`, when non-empty,
     *                                                                                     is an authorized override of the credit-limit block, audited by the
     *                                                                                     credit service (R26.5).
     * @return Order The confirmed order (status Confirmed), freshly reloaded.
     *
     * @throws \App\Exceptions\IllegalOrderTransitionException When `confirm` is illegal from the current state (422).
     * @throws \App\Exceptions\InsufficientStockException      When a decrement would oversell with backorders off (422).
     * @throws \App\Exceptions\CreditLimitExceededException    When projected outstanding exceeds the credit limit (422).
     */
    public function confirm(Order $order, array $context = []): Order
    {
        $actorId = $context['actor_id'] ?? null;
        $overrideReason = $context['credit_override_reason'] ?? null;

        $buyer = $this->resolveParty($order->buyer, 'buyer');
        $seller = $this->resolveParty($order->seller, 'seller');

        $invoiceSeries = $this->invoiceSeries($seller);

        DB::connection('tenant')->transaction(function () use (
            $order,
            $buyer,
            $seller,
            $invoiceSeries,
            $actorId,
            $overrideReason,
        ): void {
            // 1. Assert the confirm transition is legal BEFORE any side effect
            //    (R21.4). An illegal move throws a 422 and nothing is touched.
            $confirmedStatus = $this->stateMachine->assertTransition($order->status, 'confirm');

            // 2. Build the seller decrement (+ buyer increment where held) for
            //    every order line (R16.1–R16.3).
            $movements = $this->buildMovements($order, $buyer, $seller);

            // 3. Apply them FOR UPDATE, all-or-nothing (R16.4, R16.5, R17.1).
            if ($movements !== []) {
                $this->movements->apply($movements, ['actor_id' => $actorId]);
            }

            // 4. Credit-limit check at confirmation time (R21.3, R26.2).
            $this->credit->assertCreditWithinLimit($buyer, (string) $order->total, $overrideReason);

            // 5. Exactly one invoice under the seller's own series (R21.5).
            $this->invoices->generate(
                (int) $order->getKey(),
                $seller,
                $invoiceSeries,
                (string) $order->total,
            );

            // 6. Append the purchase to the buyer's transaction ledger (R25.1).
            $this->ledger->appendPurchase($buyer, (string) $order->total, [
                'reference' => 'order:'.$order->getKey(),
                'actor_id' => $actorId,
            ]);

            // 7. Persist the Confirmed state (R21.3).
            $order->status = $confirmedStatus;
            $order->save();
        });

        // After commit: invalidate the tenant's cached report aggregates — a
        // confirmation moves stock and appends an invoice + account-ledger entry
        // that feed sales/purchases/outstanding/order-status reports, so the
        // next read must recompute (R29.8).
        ($this->reportCache ?? new ReportCache)->invalidate();

        // After commit: queue the order-confirmed notification (R27.3). Done
        // outside the transaction so a rolled-back confirmation never notifies.
        $this->queueOrderConfirmedNotification($order, $buyer, $invoiceSeries);

        // Record the in-app order-confirmed notification mirror (R27.6) and,
        // where enabled, the optional SMS/WhatsApp channels (R27.5) —
        // post-commit, like the email, so a rolled-back confirmation never
        // notifies.
        $this->recordOrderConfirmedNotification($order, $buyer, $invoiceSeries, $actorId);

        return $order->refresh();
    }

    /**
     * Build the stock movements for a confirmation (R16.1–R16.3).
     *
     * Each order line produces a seller decrement at the seller's primary stock
     * point. A buyer increment at the buyer's primary stock point is added only
     * when the buyer HOLDS stock: a Distributor buyer always does (R16.1); a
     * Dealer buyer does only when dealer stock-holding is enabled for the tenant
     * (R16.3), otherwise it is a pure consumer and no increment is built (R16.2,
     * R13.6).
     *
     * @return list<StockMovement>
     */
    private function buildMovements(Order $order, Model $buyer, Model $seller): array
    {
        $buyerHoldsStock = $this->buyerHoldsStock($buyer);
        $buyerPointId = $buyerHoldsStock ? $this->buyerStockPointId($buyer) : null;

        // Resolve the seller's primary point lazily — only lines WITHOUT
        // explicit allocations fall back to it (a seller with no point is only
        // an error when such a line exists).
        $sellerPrimaryPointId = null;

        $reference = 'order:'.$order->getKey();
        $lines = $order->relationLoaded('lines') ? $order->lines : $order->lines()->get();

        $movements = [];

        foreach ($lines as $line) {
            $productId = (int) $line->product_id;
            $quantity = (string) $line->quantity;

            // The per-point source split for this line (R16). When present, the
            // seller is decremented once PER allocation at its own stock point;
            // when absent, a single decrement at the seller's primary point.
            $allocations = $line->relationLoaded('allocations')
                ? $line->allocations
                : $line->allocations()->get();

            if ($allocations->isNotEmpty()) {
                foreach ($allocations as $allocation) {
                    $movements[] = StockMovement::forOwner(
                        $seller,
                        $productId,
                        (int) $allocation->stock_point_id,
                        $this->negate((string) $allocation->quantity),
                        'sale_out',
                        null,
                        $reference,
                    );
                }
            } else {
                $sellerPrimaryPointId ??= $this->sellerStockPointId($seller);
                $movements[] = StockMovement::forOwner(
                    $seller,
                    $productId,
                    $sellerPrimaryPointId,
                    $this->negate($quantity),
                    'sale_out',
                    null,
                    $reference,
                );
            }

            // Buyer increment for the WHOLE line quantity, only where the buyer
            // holds stock, into the buyer's primary point (R16.1, R16.3). The
            // buyer receives the aggregate regardless of how many seller points
            // sourced it.
            if ($buyerPointId !== null) {
                $movements[] = StockMovement::forOwner(
                    $buyer,
                    $productId,
                    $buyerPointId,
                    $quantity,
                    'purchase_in',
                    null,
                    $reference,
                );
            }
        }

        return $movements;
    }

    /**
     * Whether the buyer holds stock: a Distributor always does (R13.5); a Dealer
     * only when dealer stock-holding is enabled for the active tenant (R13.6,
     * R13.7). Any other buyer type does not.
     */
    private function buyerHoldsStock(Model $buyer): bool
    {
        if ($buyer instanceof Distributor) {
            return true;
        }

        if ($buyer instanceof Dealer) {
            return $this->tenantDealerStockEnabled();
        }

        return false;
    }

    /**
     * The seller's primary stock point id — the point the confirmation
     * decrements from. A seller with no stock point cannot fulfil a confirmation
     * (there is nothing to decrement), which is a configuration error (422).
     */
    private function sellerStockPointId(Model $seller): int
    {
        $pointId = $this->primaryStockPointId($seller);

        if ($pointId === null) {
            throw new RuntimeException(sprintf(
                'The order seller [%s #%d] has no stock point to fulfil the confirmation.',
                $seller->getMorphClass(),
                (int) $seller->getKey(),
            ));
        }

        return $pointId;
    }

    /**
     * The buyer's primary stock point id — the point the confirmation increments
     * into. A stock-holding buyer with no stock point yet has a default one
     * provisioned so the increment has a home (R13.5, R13.7, R13.8).
     */
    private function buyerStockPointId(Model $buyer): int
    {
        return $this->primaryStockPointId($buyer)
            ?? (int) $this->provisionDefaultStockPoint($buyer)->getKey();
    }

    /**
     * The id of an owner's primary stock point: the one with the lowest
     * `sort_order`, tie-broken by lowest id (R13.8). Null when the owner owns no
     * stock point.
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
     * Provision a default stock point for a stock-holding buyer that owns none
     * yet, so a confirmation increment has a destination (R13.5, R13.7, R13.8).
     */
    private function provisionDefaultStockPoint(Model $owner): StockPoint
    {
        $point = new StockPoint(['name' => 'Default', 'sort_order' => 0]);
        $point->owner()->associate($owner);
        $point->save();

        return $point;
    }

    /**
     * The seller's own invoice series identifier: a stable per-seller string
     * derived from the seller morph alias + key (e.g. `company-1`,
     * `distributor-5`). Each seller runs an INDEPENDENT series whose numbers are
     * strictly increasing within that series (R21.5).
     */
    private function invoiceSeries(Model $seller): string
    {
        return $seller->getMorphClass().'-'.(int) $seller->getKey();
    }

    /**
     * Resolve a required order party (buyer or seller) from the morph relation,
     * guarding against a dangling morph that cannot be resolved to a model.
     */
    private function resolveParty(?Model $party, string $role): Model
    {
        if ($party === null) {
            throw new RuntimeException(sprintf('The order has no resolvable %s.', $role));
        }

        return $party;
    }

    /**
     * Queue the order-confirmed email to the buyer's contact address using the
     * active tenant's sender identity (R27.3, R3.3). No-op when the buyer has no
     * contact email (e.g. a Company buyer never applies here). The invoice the
     * confirmation issued is the latest number in the seller's series.
     */
    private function queueOrderConfirmedNotification(Order $order, Model $buyer, string $invoiceSeries): void
    {
        $contactEmail = $buyer->contact_email ?? null;

        if (empty($contactEmail)) {
            return;
        }

        $invoiceNumber = (int) \App\Models\Invoice::query()
            ->where('order_id', (int) $order->getKey())
            ->value('number');

        $brandName = $this->whiteLabel->get()['brand_name'] ?? null;

        Mail::to($contactEmail)->queue(new OrderConfirmedMail(
            buyerName: (string) ($buyer->name ?? ''),
            orderId: (int) $order->getKey(),
            invoiceSeries: $invoiceSeries,
            invoiceNumber: $invoiceNumber,
            amount: (string) $order->total,
            brandName: $brandName,
        ));
    }

    /**
     * Record the in-app order-confirmed notification for the confirming user,
     * and offer the optional SMS/WhatsApp channels to the buyer's phone where
     * the `whatsapp_notifications` flag is enabled (R27.3, R27.5, R27.6).
     * Best-effort and post-commit; no-op when the notifications service is not
     * wired (e.g. a unit test constructing the service directly).
     */
    private function recordOrderConfirmedNotification(Order $order, Model $buyer, string $invoiceSeries, ?int $actorId): void
    {
        if ($this->notifications === null) {
            return;
        }

        $userId = $actorId ?? auth()->id();

        if ($userId === null) {
            return;
        }

        $invoiceNumber = (int) \App\Models\Invoice::query()
            ->where('order_id', (int) $order->getKey())
            ->value('number');

        $this->notifications->notify(
            userId: (int) $userId,
            type: Notification::TYPE_ORDER_CONFIRMED,
            title: 'Order confirmed',
            body: sprintf('Order #%d confirmed; invoice %s-%d.', (int) $order->getKey(), $invoiceSeries, $invoiceNumber),
            data: [
                'order_id' => (int) $order->getKey(),
                'invoice_series' => $invoiceSeries,
                'invoice_number' => $invoiceNumber,
                'amount' => (string) $order->total,
            ],
            phone: $buyer->contact_phone ?? null,
        );
    }

    /**
     * Whether the active tenant permits dealers to hold stock (R13.7). Defaults
     * to false (dealers are pure consumers) when there is no resolved tenancy
     * context or the flag is unset, mirroring the safe default in
     * {@see StockMovementService}.
     */
    private function tenantDealerStockEnabled(): bool
    {
        if (! function_exists('tenant')) {
            return false;
        }

        $tenant = tenant();

        if (! $tenant instanceof Tenant) {
            return false;
        }

        return (bool) $tenant->dealer_stock_enabled;
    }

    /**
     * Negate a DECIMAL(15,3) quantity string so the seller movement is a
     * decrement (`-quantity`). A leading sign is normalized so the result is a
     * single well-formed signed decimal {@see StockMovement} accepts. Mirrors
     * {@see StockTransferService::negate()}.
     */
    private function negate(string $quantity): string
    {
        $quantity = ltrim(trim($quantity), '+');

        if ($quantity === '' || $quantity === '0' || (float) $quantity === 0.0) {
            return $quantity === '' ? '0' : $quantity;
        }

        if (str_starts_with($quantity, '-')) {
            return substr($quantity, 1);
        }

        return '-'.$quantity;
    }
}
