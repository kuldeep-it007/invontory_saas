<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidReturnException;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Models\Order;
use App\Models\StockPoint;
use App\Models\Tenant;
use App\Services\Stock\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Processes a return against a confirmed order: re-enters the returned
 * quantities into stock, adjusts the buyer's account, and recomputes the
 * Outstanding_Balance (R18.3, R22.2, R22.3, design "Cancellation / return
 * reversal").
 *
 * {@see process()} runs the whole return inside ONE tenant-database transaction
 * so the stock re-entry and the account adjustment are all-or-nothing — any
 * failure rolls the entire return back and nothing persists. The pipeline:
 *
 *   1. {@see normalizeLines()} — validate the requested return lines (each a
 *      product + positive quantity, optionally a credited amount) against the
 *      order's lines; a line for a product not on the order, or a non-positive
 *      quantity, is rejected with a 422 before any side effect.
 *   2. {@see buildReturnMovements()} — for each returned line, re-enter the
 *      returned quantity into the SELLER's stock (the goods come back to the
 *      seller) and, where the buyer HELD stock from the confirmation, remove it
 *      from the buyer's stock (R18.3, R22.2). One immutable ledger entry is
 *      written per movement by {@see StockMovementService} (R15.1).
 *   3. {@see AccountLedgerService::appendReturn()} — adjust the buyer's account
 *      by the total credited return value, which reduces what the buyer owes
 *      (R22.2).
 *   4. Read back the buyer's Outstanding_Balance — the latest transaction-ledger
 *      `balance_after` after the return adjustment (R22.3, R25.2).
 *
 * Unlike a cancellation (which reverses the WHOLE order and transitions it to
 * Cancelled), a return is a partial correction against a still-confirmed order:
 * it does NOT change the order's lifecycle state, so multiple returns may be
 * processed against the same order over time.
 *
 * ## Stock re-entry direction
 *
 * The buyer sends goods back to the seller, so a return is the stock inverse of
 * the confirmation for the returned quantity: the SELLER's stock is incremented
 * (goods received back) and, only where the buyer holds stock, the BUYER's
 * stock is decremented. When the buyer does not hold stock (e.g. a dealer with
 * dealer stock-holding disabled), only the seller re-entry is written —
 * mirroring the confirmation that wrote only the seller decrement.
 */
class OrderReturnService
{
    /**
     * The DECIMAL(15,2) monetary scale shared with the account ledger.
     */
    private const MONEY_SCALE = 2;

    public function __construct(
        private readonly StockMovementService $movements,
        private readonly AccountLedgerService $ledger,
        private readonly ?ReportCache $reportCache = null,
    ) {}

    /**
     * Process a return against a confirmed order (R18.3, R22.2, R22.3).
     *
     * @param  Order  $order  The confirmed order the return is processed against.
     * @param  list<array{product_id: int|string, quantity: int|string, amount?: int|string|null}>  $returnLines
     *                                                                                                             The lines to return: a product id, the quantity returned, and an
     *                                                                                                             optional credited amount. When `amount` is omitted for a line, the
     *                                                                                                             credited value is derived from the order line's net unit price
     *                                                                                                             (unit_price − discount) × returned quantity.
     * @param  array{actor_id?: int|null}  $context  `actor_id` recorded on each ledger entry (R15.1).
     * @return array{order_id: int, returned_lines: int, credited_amount: string, outstanding_balance: string}
     *                                                                                                           A summary of the return, including the recomputed Outstanding_Balance (R22.3).
     *
     * @throws InvalidReturnException When a return line is malformed or does not match the order (422).
     */
    public function process(Order $order, array $returnLines, array $context = []): array
    {
        $actorId = $context['actor_id'] ?? null;

        $buyer = $this->resolveParty($order->buyer, 'buyer');
        $seller = $this->resolveParty($order->seller, 'seller');

        $normalized = $this->normalizeLines($order, $returnLines);

        $outstanding = DB::connection('tenant')->transaction(function () use (
            $order,
            $buyer,
            $seller,
            $normalized,
            $actorId,
        ): string {
            // 1. Re-enter the returned quantities into stock (R18.3, R22.2).
            $movements = $this->buildReturnMovements($order, $buyer, $seller, $normalized);

            if ($movements !== []) {
                $this->movements->apply($movements, [
                    'actor_id' => $actorId,
                    'allow_negative' => true,
                ]);
            }

            // 2. Adjust the buyer's account by the total credited return value,
            //    reducing what the buyer owes (R22.2).
            $creditedTotal = $this->creditedTotal($normalized);

            if ($this->isPositive($creditedTotal)) {
                $this->ledger->appendReturn($buyer, $creditedTotal, [
                    'reference' => 'order-return:'.$order->getKey(),
                    'actor_id' => $actorId,
                ]);
            }

            // 3. Recompute the Outstanding_Balance (R22.3) — the latest
            //    transaction-ledger balance_after after the return adjustment.
            return $this->ledger->outstandingBalance($buyer);
        });

        // After commit: invalidate the tenant's cached report aggregates — the
        // return re-entered stock and adjusted the account, changing
        // stock/sales/outstanding reports (R29.8).
        ($this->reportCache ?? new ReportCache)->invalidate();

        return [
            'order_id' => (int) $order->getKey(),
            'returned_lines' => count($normalized),
            'credited_amount' => $this->creditedTotal($normalized),
            'outstanding_balance' => $outstanding,
        ];
    }

    /**
     * Validate and normalize the requested return lines against the order
     * (R18.3). Each line must name a product that is on the order and carry a
     * positive quantity; the credited amount defaults to the order line's net
     * unit value × returned quantity when not supplied.
     *
     * @param  list<array{product_id: int|string, quantity: int|string, amount?: int|string|null}>  $returnLines
     * @return list<array{product_id: int, quantity: string, amount: string}>
     *
     * @throws InvalidReturnException on a malformed line or a product not on the order.
     */
    private function normalizeLines(Order $order, array $returnLines): array
    {
        if ($returnLines === []) {
            throw new InvalidReturnException('A return requires at least one line.');
        }

        $orderLines = $order->relationLoaded('lines') ? $order->lines : $order->lines()->get();
        $byProduct = [];
        foreach ($orderLines as $line) {
            $byProduct[(int) $line->product_id] = $line;
        }

        $normalized = [];

        foreach ($returnLines as $line) {
            $productId = (int) ($line['product_id'] ?? 0);
            $quantity = (string) ($line['quantity'] ?? '0');

            if (! isset($byProduct[$productId])) {
                throw new InvalidReturnException(sprintf(
                    'Return line product [%d] is not part of order [%d].',
                    $productId,
                    (int) $order->getKey(),
                ));
            }

            if (! is_numeric($quantity) || (float) $quantity <= 0.0) {
                throw new InvalidReturnException('A return line quantity must be a positive number.');
            }

            $orderLine = $byProduct[$productId];
            $amount = isset($line['amount']) && $line['amount'] !== null && $line['amount'] !== ''
                ? $this->scaleMoney((string) $line['amount'])
                : $this->defaultCreditedAmount($orderLine, $quantity);

            $normalized[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'amount' => $amount,
            ];
        }

        return $normalized;
    }

    /**
     * Build the stock movements that re-enter returned quantities (R18.3,
     * R22.2): a SELLER increment per returned line (goods received back) and,
     * where the buyer holds stock, a matching BUYER decrement.
     *
     * @param  list<array{product_id: int, quantity: string, amount: string}>  $normalized
     * @return list<StockMovement>
     */
    private function buildReturnMovements(Order $order, Model $buyer, Model $seller, array $normalized): array
    {
        $sellerPointId = $this->sellerStockPointId($seller);
        $buyerHoldsStock = $this->buyerHoldsStock($buyer);
        $buyerPointId = $buyerHoldsStock ? $this->buyerStockPointId($buyer) : null;

        $reference = 'order-return:'.$order->getKey();
        $movements = [];

        foreach ($normalized as $line) {
            $productId = $line['product_id'];
            $quantity = $line['quantity'];

            // Seller receives the returned goods back into stock (R18.3).
            $movements[] = StockMovement::forOwner(
                $seller,
                $productId,
                $sellerPointId,
                $quantity,
                'return_in',
                null,
                $reference,
            );

            // Where the buyer held stock, remove the returned goods from it so
            // the return is the stock inverse of the confirmation.
            if ($buyerPointId !== null) {
                $movements[] = StockMovement::forOwner(
                    $buyer,
                    $productId,
                    $buyerPointId,
                    $this->negate($quantity),
                    'return_out',
                    null,
                    $reference,
                );
            }
        }

        return $movements;
    }

    /**
     * The total credited return value across all normalized lines, as a
     * DECIMAL(15,2) string.
     *
     * @param  list<array{product_id: int, quantity: string, amount: string}>  $normalized
     */
    private function creditedTotal(array $normalized): string
    {
        $total = $this->scaleMoney('0');

        foreach ($normalized as $line) {
            $total = $this->addMoney($total, $line['amount']);
        }

        return $total;
    }

    /**
     * The default credited amount for a returned line when the caller does not
     * supply one: the order line's net unit value (unit_price − discount),
     * floored at zero, multiplied by the returned quantity.
     */
    private function defaultCreditedAmount(Model $orderLine, string $quantity): string
    {
        $unit = $this->subtractMoney(
            $this->scaleMoney((string) $orderLine->unit_price),
            $this->scaleMoney((string) $orderLine->discount),
        );

        if (! $this->isPositive($unit)) {
            return $this->scaleMoney('0');
        }

        // net unit × quantity, computed exactly at the money scale.
        $cents = $this->toMoneyInt($unit);
        $qtyFloat = (float) $quantity;
        $amountInt = (int) round($cents * $qtyFloat);

        return $this->fromMoneyInt($amountInt);
    }

    /**
     * Whether the buyer holds stock: a Distributor always does (R13.5); a Dealer
     * only when dealer stock-holding is enabled for the active tenant (R13.6,
     * R13.7). Mirrors {@see OrderConfirmationService::buyerHoldsStock()}.
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
     * The seller's primary stock point id — the point the return re-enters into.
     * A seller with no stock point cannot receive a return.
     */
    private function sellerStockPointId(Model $seller): int
    {
        $pointId = $this->primaryStockPointId($seller);

        if ($pointId === null) {
            throw new RuntimeException(sprintf(
                'The order seller [%s #%d] has no stock point to receive the return.',
                $seller->getMorphClass(),
                (int) $seller->getKey(),
            ));
        }

        return $pointId;
    }

    /**
     * The buyer's primary stock point id — the point the return removes from.
     * A stock-holding buyer with no stock point yet has a default one
     * provisioned so the decrement has a home.
     */
    private function buyerStockPointId(Model $buyer): int
    {
        return $this->primaryStockPointId($buyer)
            ?? (int) $this->provisionDefaultStockPoint($buyer)->getKey();
    }

    /**
     * The id of an owner's primary stock point: the lowest `sort_order`,
     * tie-broken by lowest id (R13.8). Null when the owner owns no stock point.
     * Mirrors {@see OrderConfirmationService::primaryStockPointId()}.
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
     * Provision a default stock point for a stock-holding buyer that owns none.
     */
    private function provisionDefaultStockPoint(Model $owner): StockPoint
    {
        $point = new StockPoint(['name' => 'Default', 'sort_order' => 0]);
        $point->owner()->associate($owner);
        $point->save();

        return $point;
    }

    /**
     * Resolve a required order party (buyer or seller) from the morph relation.
     */
    private function resolveParty(?Model $party, string $role): Model
    {
        if ($party === null) {
            throw new RuntimeException(sprintf('The order has no resolvable %s.', $role));
        }

        return $party;
    }

    /**
     * Whether the active tenant permits dealers to hold stock (R13.7). Defaults
     * to false when there is no resolved tenancy context or the flag is unset.
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
     * Negate a DECIMAL(15,3) quantity string. Mirrors
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

    // --- Exact DECIMAL(15,2) money helpers (never floats for the ledger) -----

    private function addMoney(string $a, string $b): string
    {
        if (function_exists('bcadd')) {
            return bcadd($a, $b, self::MONEY_SCALE);
        }

        return $this->fromMoneyInt($this->toMoneyInt($a) + $this->toMoneyInt($b));
    }

    private function subtractMoney(string $a, string $b): string
    {
        if (function_exists('bcsub')) {
            return bcsub($a, $b, self::MONEY_SCALE);
        }

        return $this->fromMoneyInt($this->toMoneyInt($a) - $this->toMoneyInt($b));
    }

    private function scaleMoney(string $value): string
    {
        $value = trim($value);

        if ($value === '' || ! is_numeric($value)) {
            $value = '0';
        }

        if (function_exists('bcadd')) {
            return bcadd($value, '0', self::MONEY_SCALE);
        }

        return $this->fromMoneyInt($this->toMoneyInt($value));
    }

    private function isPositive(string $value): bool
    {
        if (function_exists('bccomp')) {
            return bccomp($value, '0', self::MONEY_SCALE) > 0;
        }

        return $this->toMoneyInt($value) > 0;
    }

    private function toMoneyInt(string $value): int
    {
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');

        $parts = explode('.', $value, 2);
        $whole = $parts[0] === '' ? '0' : $parts[0];
        $frac = $parts[1] ?? '';
        $frac = substr(str_pad($frac, self::MONEY_SCALE, '0'), 0, self::MONEY_SCALE);

        $scaled = (int) ($whole.$frac);

        return $negative ? -$scaled : $scaled;
    }

    private function fromMoneyInt(int $scaled): string
    {
        $negative = $scaled < 0;
        $scaled = abs($scaled);

        $digits = str_pad((string) $scaled, self::MONEY_SCALE + 1, '0', STR_PAD_LEFT);
        $whole = substr($digits, 0, -self::MONEY_SCALE);
        $frac = substr($digits, -self::MONEY_SCALE);

        return ($negative ? '-' : '').$whole.'.'.$frac;
    }
}
