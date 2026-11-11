<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InsufficientAvailableStockException;
use App\Models\Order;
use App\Models\Stock;
use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;

/**
 * The single authority for stock reservations and the available-quantity
 * computation (R23.4, R23.5, design "Stock blocking/reservation").
 *
 * A reservation BLOCKS a quantity of a {@see Stock} row for an in-progress or
 * advance {@see Order} WITHOUT moving the owner's on-hand quantity. Every
 * reservation flows through this service so the one invariant the whole feature
 * rests on is computed in exactly one place:
 *
 *     available(stock) = on-hand(stock) − Σ quantity of ACTIVE reservations(stock)
 *
 * The reserved quantity is thereby unavailable to other orders while the
 * reservation is active (R23.4); releasing a reservation (order cancelled or
 * reservation expired) returns its quantity to available, restoring the prior
 * value (R23.5).
 *
 * ## Guarantees
 *
 *   • {@see reserve()} locks the target stock row FOR UPDATE, recomputes
 *     available under the lock, and rejects a request that exceeds available
 *     with a 422 — all inside one transaction, so two concurrent reservations
 *     can never together over-block a row.
 *   • {@see release()} / {@see releaseForOrder()} flip active reservations to
 *     `released`, which immediately returns their quantity to available (R23.5).
 *   • {@see releaseExpired()} releases every lapsed reservation so advance/
 *     blanket reservations that pass their `expires_at` free their stock
 *     (R23.1, R23.2, R23.5).
 *
 * Quantities are handled as fixed-scale (DECIMAL(15,3)) decimal strings and
 * never as floats, so the availability arithmetic is exact (R13.3).
 *
 * This service does NOT move on-hand quantity — that stays the sole job of
 * {@see StockMovementService}. A reservation only blocks; a confirmation (which
 * actually moves owned quantity) releases the reservation it was holding.
 */
class StockReservationService
{
    /**
     * The fixed decimal scale of stock quantities (DECIMAL(15,3)).
     */
    private const SCALE = 3;

    /**
     * The sum of the ACTIVE reservations' quantities for a stock row — the
     * amount currently BLOCKED and therefore unavailable to other orders
     * (R23.4). Computed SQL-side against the `(stock_id, status)` index.
     */
    public function reservedQuantity(int $stockId): string
    {
        $sum = StockReservation::query()
            ->where('stock_id', $stockId)
            ->active()
            ->sum('quantity');

        return $this->scale((string) ($sum ?? '0'));
    }

    /**
     * The AVAILABLE quantity of a stock row: on-hand minus the sum of its
     * active reservations (R23.4). This is the single definition every caller
     * shares, so "what is available" and "what reserving subtracts" are
     * provably the same value (design Property 13).
     */
    public function availableQuantity(Stock $stock): string
    {
        return $this->subtract(
            (string) $stock->quantity,
            $this->reservedQuantity((int) $stock->getKey()),
        );
    }

    /**
     * Reserve (block) a quantity of a stock row for an order (R23.2, R23.4).
     *
     * Runs inside one tenant-database transaction that locks the target stock
     * row FOR UPDATE, recomputes available UNDER THE LOCK, and rejects a request
     * that would block more than is available with a 422 — so two concurrent
     * reservations can never together over-block the row. On success it writes
     * one `active` reservation; the reserved quantity is immediately unavailable
     * to other orders because {@see availableQuantity()} subtracts it (R23.4).
     *
     * On-hand quantity is NOT moved — a reservation only blocks (design "Stock
     * blocking/reservation"); the owned quantity stays put until a confirmation
     * actually moves it through {@see StockMovementService}.
     *
     * @param  array{expires_at?: \DateTimeInterface|string|null}  $options
     *                                                                       Optional. `expires_at` sets a scheduled lapse for an advance/blanket
     *                                                                       reservation (R23.1, R23.2); omitted leaves the reservation open-ended.
     *
     * @throws InsufficientAvailableStockException When the request exceeds available (422).
     */
    public function reserve(Stock $stock, string $quantity, ?int $orderId = null, array $options = []): StockReservation
    {
        $quantity = $this->scale($quantity);
        $expiresAt = $options['expires_at'] ?? null;

        return DB::connection('tenant')->transaction(function () use ($stock, $quantity, $orderId, $expiresAt): StockReservation {
            // Lock the stock row FOR UPDATE so a concurrent reservation on the
            // same row serializes behind us and sees our reservation when it
            // recomputes available (R23.4 under concurrency).
            $locked = Stock::query()
                ->whereKey($stock->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $available = $this->availableQuantity($locked);

            // Reject a request that would block more than is available: the
            // whole request fails and nothing is persisted (R23.4).
            if ($this->compare($quantity, $available) > 0) {
                throw new InsufficientAvailableStockException(
                    (int) $locked->getKey(),
                    $quantity,
                    $available,
                );
            }

            return StockReservation::create([
                'stock_id' => (int) $locked->getKey(),
                'order_id' => $orderId,
                'quantity' => $quantity,
                'status' => StockReservation::STATUS_ACTIVE,
                'expires_at' => $expiresAt,
            ]);
        });
    }

    /**
     * Release a single reservation, returning its blocked quantity to available
     * (R23.5). Idempotent: releasing an already-released reservation is a no-op.
     * The status change is captured by the audit trail (R34.1).
     */
    public function release(StockReservation $reservation): StockReservation
    {
        if (! $reservation->isActive()) {
            return $reservation;
        }

        $reservation->status = StockReservation::STATUS_RELEASED;
        $reservation->save();

        return $reservation->refresh();
    }

    /**
     * Release EVERY active reservation held for an order (R23.5).
     *
     * Called when an order is cancelled or expires so all the stock it blocked
     * returns to available at once. Runs in one transaction so the release is
     * all-or-nothing. Returns the number of reservations released.
     */
    public function releaseForOrder(Order|int $order): int
    {
        $orderId = $order instanceof Order ? (int) $order->getKey() : $order;

        return DB::connection('tenant')->transaction(function () use ($orderId): int {
            $reservations = StockReservation::query()
                ->where('order_id', $orderId)
                ->active()
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $reservation) {
                $reservation->status = StockReservation::STATUS_RELEASED;
                $reservation->save();
            }

            return $reservations->count();
        });
    }

    /**
     * Release every reservation that has lapsed (its `expires_at` is at or
     * before `$asOf`, default now), returning each one's blocked quantity to
     * available (R23.1, R23.2, R23.5). Returns the number released. Intended to
     * be driven by a scheduled sweep for advance/blanket reservations.
     */
    public function releaseExpired(?\DateTimeInterface $asOf = null): int
    {
        return DB::connection('tenant')->transaction(function () use ($asOf): int {
            $reservations = StockReservation::query()
                ->expired($asOf)
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $reservation) {
                $reservation->status = StockReservation::STATUS_RELEASED;
                $reservation->save();
            }

            return $reservations->count();
        });
    }

    /* ----------------------------------------------------------------------
     | Exact fixed-scale decimal arithmetic (never floats) — mirrors
     | StockMovementService so the availability invariant is exact (R13.3).
     * -------------------------------------------------------------------- */

    /**
     * Subtract two fixed-scale decimal strings exactly: `$a − $b`.
     */
    private function subtract(string $a, string $b): string
    {
        if (function_exists('bcsub')) {
            return bcsub($a, $b, self::SCALE);
        }

        return $this->fromScaledInt($this->toScaledInt($a) - $this->toScaledInt($b));
    }

    /**
     * Compare two fixed-scale decimal strings exactly, returning -1/0/1.
     */
    private function compare(string $a, string $b): int
    {
        if (function_exists('bccomp')) {
            return bccomp($a, $b, self::SCALE);
        }

        return $this->toScaledInt($a) <=> $this->toScaledInt($b);
    }

    /**
     * Normalize a numeric string to the fixed stock scale (DECIMAL(15,3)).
     */
    private function scale(string $value): string
    {
        if (function_exists('bcadd')) {
            return bcadd($value, '0', self::SCALE);
        }

        return $this->fromScaledInt($this->toScaledInt($value));
    }

    /**
     * Convert a decimal string to an integer in the smallest unit (×10^SCALE)
     * for exact integer arithmetic on the bcmath-less fallback path.
     */
    private function toScaledInt(string $value): int
    {
        $value = trim($value);
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');

        $parts = explode('.', $value, 2);
        $whole = $parts[0] === '' ? '0' : $parts[0];
        $frac = substr(str_pad($parts[1] ?? '', self::SCALE, '0'), 0, self::SCALE);

        $scaled = (int) ($whole.$frac);

        return $negative ? -$scaled : $scaled;
    }

    /**
     * Convert a smallest-unit integer back to a fixed-scale decimal string.
     */
    private function fromScaledInt(int $scaled): string
    {
        $negative = $scaled < 0;
        $scaled = abs($scaled);

        $digits = str_pad((string) $scaled, self::SCALE + 1, '0', STR_PAD_LEFT);
        $whole = substr($digits, 0, -self::SCALE);
        $frac = substr($digits, -self::SCALE);

        return ($negative ? '-' : '').$whole.'.'.$frac;
    }
}
