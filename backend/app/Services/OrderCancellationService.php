<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\StockLedger;
use App\Services\Stock\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cancels a confirmed order, reversing every side effect the confirmation
 * applied (R18.2, R22.1, design "Cancellation / return reversal").
 *
 * {@see cancel()} runs the whole cancellation inside ONE tenant-database
 * transaction so the stock reversal, the account reversal, and the order's
 * Cancelled state are all-or-nothing — any failure rolls the entire
 * cancellation back and nothing persists. The pipeline, in order:
 *
 *   1. {@see OrderStateMachine::assertTransition()} — assert `cancel` is legal
 *      from the order's current state BEFORE any side effect, rejecting an
 *      illegal move with a 422 so the order is left unchanged (R21.4). Cancel is
 *      defined from Placed, Confirmed, and Dispatched (OrderStateMachine map).
 *   2. {@see buildReversingMovements()} — for every stock-ledger entry the
 *      confirmation wrote under this order's reference, build the EXACT inverse
 *      movement (negated signed quantity) so each affected stock quantity is
 *      restored to its pre-confirmation value (R18.2, R22.1, Property 7).
 *   3. {@see StockMovementService::apply()} — apply the reversing movements with
 *      FOR UPDATE row locks, all-or-nothing, writing one immutable reversing
 *      ledger entry per movement (R15.1, R18.2). Reversals are applied with the
 *      negative-stock guard OFF: a reversal only ever RESTORES stock the
 *      confirmation moved, so it can never oversell, and it must not be blocked
 *      by a tenant whose backorders are disabled.
 *   4. {@see AccountLedgerService::appendCancellation()} — reverse the buyer's
 *      account so the cancelled purchase no longer counts toward the
 *      Outstanding_Balance (R22.1).
 *   5. Persist the Cancelled state.
 *
 * ## Why reverse from the confirmation's ledger entries?
 *
 * The confirmation wrote one stock-ledger entry per movement, each tagged with
 * `reference = order:{id}` and a SIGNED `quantity`. Restoring stock to its
 * exact pre-confirmation value is therefore just "apply the negative of every
 * one of those signed quantities" — this works regardless of how many stock
 * points/owners the confirmation touched (seller decrement, buyer increment
 * where it held stock) and needs no re-derivation of the party/stock-point
 * resolution the confirmation performed. Idempotency is protected by the state
 * machine: once the order is Cancelled, `cancel` is undefined from a terminal
 * state, so a second cancel is rejected and no second reversal is written.
 */
class OrderCancellationService
{
    public function __construct(
        private readonly OrderStateMachine $stateMachine,
        private readonly StockMovementService $movements,
        private readonly AccountLedgerService $ledger,
        private readonly ?ReportCache $reportCache = null,
        private readonly ?StockReservationService $reservations = null,
    ) {}

    /**
     * Cancel a confirmed order: reverse its stock movements and its account
     * charge, then transition it to Cancelled (R18.2, R22.1).
     *
     * @param  Order  $order  The order being cancelled (its buyer morph resolves the account to reverse).
     * @param  array{actor_id?: int|null}  $context
     *                                               Optional context. `actor_id` is recorded on each reversing stock-ledger
     *                                               and account-ledger entry (R15.1).
     * @return Order The cancelled order (status Cancelled), freshly reloaded.
     *
     * @throws \App\Exceptions\IllegalOrderTransitionException When `cancel` is illegal from the current state (422).
     */
    public function cancel(Order $order, array $context = []): Order
    {
        $actorId = $context['actor_id'] ?? null;
        $buyer = $this->resolveBuyer($order);

        DB::connection('tenant')->transaction(function () use ($order, $buyer, $actorId): void {
            // 1. Assert the cancel transition is legal BEFORE any side effect
            //    (R21.4). An illegal move throws a 422 and nothing is touched.
            $cancelledStatus = $this->stateMachine->assertTransition($order->status, 'cancel');

            // 2. Build the exact inverse of every movement the confirmation
            //    wrote for this order (R18.2, R22.1).
            $movements = $this->buildReversingMovements($order);

            // 3. Apply the reversals FOR UPDATE, all-or-nothing. Negative guard
            //    off: a reversal only restores stock, so it can never oversell.
            if ($movements !== []) {
                $this->movements->apply($movements, [
                    'actor_id' => $actorId,
                    'allow_negative' => true,
                ]);
            }

            // 4. Reverse the buyer's account so the cancelled purchase no longer
            //    counts toward the Outstanding_Balance (R22.1).
            $this->ledger->appendCancellation($buyer, (string) $order->total, [
                'reference' => 'order-cancel:'.$order->getKey(),
                'actor_id' => $actorId,
            ]);

            // 5. Release any stock the order had RESERVED (advance/blanket/depot
            //    or an in-progress block), returning the blocked quantity to
            //    available (R23.5). A standard confirmed order holds none, so
            //    this is a no-op there; an advanced order's reservations are
            //    flipped to released inside the same cancellation transaction.
            ($this->reservations ?? new StockReservationService)->releaseForOrder($order);

            // 6. Persist the Cancelled state.
            $order->status = $cancelledStatus;
            $order->save();
        });

        // After commit: invalidate the tenant's cached report aggregates — the
        // cancellation restored stock and reversed the account, changing
        // stock/sales/outstanding/order-status reports (R29.8).
        ($this->reportCache ?? new ReportCache)->invalidate();

        return $order->refresh();
    }

    /**
     * Build the reversing stock movements for a cancellation (R18.2, R22.1).
     *
     * Reads every stock-ledger entry the confirmation wrote under this order's
     * `reference` and emits, for each, a movement that applies the NEGATED
     * signed quantity to the same (owner, product, stock point). Applying these
     * restores every affected stock quantity to its exact pre-confirmation
     * value (Property 7). Returns an empty list when the order moved no stock
     * (e.g. a dealer order with dealer stock disabled still wrote the seller
     * decrement, so this is normally non-empty for a confirmed order).
     *
     * @return list<StockMovement>
     */
    private function buildReversingMovements(Order $order): array
    {
        $confirmationReference = 'order:'.$order->getKey();

        $entries = StockLedger::query()
            ->where('reference', $confirmationReference)
            ->whereIn('movement_type', ['sale_out', 'purchase_in'])
            ->orderBy('id')
            ->get();

        $reference = 'order-cancel:'.$order->getKey();
        $movements = [];

        foreach ($entries as $entry) {
            $movements[] = new StockMovement(
                (string) $entry->stockable_type,
                (int) $entry->stockable_id,
                (int) $entry->product_id,
                (int) $entry->stock_point_id,
                $this->negate((string) $entry->quantity),
                $entry->movement_type === 'sale_out' ? 'cancel_in' : 'cancel_out',
                null,
                $reference,
            );
        }

        return $movements;
    }

    /**
     * Resolve the order's Buyer, guarding against a dangling morph that cannot
     * be resolved to a model (the account to reverse must exist).
     */
    private function resolveBuyer(Order $order): Model
    {
        $buyer = $order->buyer;

        if ($buyer === null) {
            throw new RuntimeException('The order has no resolvable buyer to reverse the account for.');
        }

        return $buyer;
    }

    /**
     * Negate a DECIMAL(15,3) signed quantity string so a confirmation decrement
     * (`-q`) becomes a restoring increment (`+q`) and vice versa. A leading sign
     * is normalized so the result is a single well-formed signed decimal that
     * {@see StockMovement} accepts. Mirrors
     * {@see OrderConfirmationService::negate()}.
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
