<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Stock;
use App\Models\StockLedger;
use App\Models\Tenant;
use App\Services\Stock\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * The single authority for every stock change in the platform (R13.1, design
 * "Inventory Engine"). All stock mutations — opening balances, inward receipts,
 * manual adjustments, order-confirmation decrements/increments, transfers,
 * reversals, and returns — route through {@see apply()} so the atomicity,
 * row-locking, immutable-ledger, and balance-after invariants hold in exactly
 * one place.
 *
 * ## Guarantees
 *
 * For a batch of {@see StockMovement}s, {@see apply()}:
 *
 *   1. Opens a SINGLE database transaction (R16.4, R35.3).
 *   2. Locks every affected `stock` row with `SELECT ... FOR UPDATE`, ordered
 *      deterministically by primary key, to acquire row locks and avoid
 *      deadlocks under concurrency (R16.5, R35.1).
 *   3. Computes each resulting quantity and runs the negative-stock validation,
 *      which is governed by the per-tenant backorder toggle: when the active
 *      tenant's `backorders_enabled` flag is OFF, any line that would drive an
 *      owner's stock below zero rejects the WHOLE batch with a 422 that lists
 *      every insufficient product (R17.1, R17.3); when it is ON, the batch is
 *      permitted to go negative and the backordered quantity is recorded on the
 *      ledger (R17.2).
 *   4. Applies all increments/decrements to the locked rows.
 *   5. Writes exactly ONE immutable `stock_ledger` entry per movement, with
 *      `balance_after` equal to the owner's post-movement stock quantity, plus
 *      movement type, reason, actor, timestamp, and reference (R15.1, R15.3).
 *   6. Commits — or rolls back the ENTIRE batch on any failure so no partial
 *      changes persist (R14.2, R35.2, R35.3).
 *
 * Quantities are handled as fixed-scale (DECIMAL(15,3)) decimal strings and
 * never as floats, so the balance-after invariant is exact (R15.3).
 */
class StockMovementService
{
    /**
     * The fixed decimal scale of stock quantities (DECIMAL(15,3)).
     */
    private const SCALE = 3;

    /**
     * The per-tenant report aggregate cache. Every applied batch of movements
     * changes on-hand quantities and the stock ledger, which feed stock and
     * sales reports, so a successful batch invalidates the tenant's cached
     * aggregates (R29.8). Defaulted so direct construction (unit tests) keeps
     * working; the container injects the shared singleton.
     */
    private ReportCache $reportCache;

    public function __construct(?ReportCache $reportCache = null)
    {
        $this->reportCache = $reportCache ?? new ReportCache;
    }

    /**
     * Apply a batch of stock movements atomically and append one immutable
     * ledger entry per movement.
     *
     * All movements succeed together or none do: on any failure the whole
     * transaction rolls back and no stock change or ledger write persists
     * (R14.2, R35.2, R35.3). The affected `stock` rows are locked FOR UPDATE in
     * deterministic primary-key order before any mutation, serializing
     * concurrent callers and preventing deadlocks (R16.5, R35.1).
     *
     * @param  list<StockMovement>  $movements  The movements to apply in one transaction.
     * @param  array{actor_id?: int|null, allow_negative?: bool}  $context
     *                                                                      Optional context. `actor_id` is recorded on each ledger entry (R15.1).
     *                                                                      `allow_negative` is an explicit per-call override of the negative-stock
     *                                                                      guard: when it is set it wins (true skips the guard, false enforces it),
     *                                                                      which lets callers outside a tenancy context — and tests — pin the
     *                                                                      behaviour deterministically. When it is OMITTED (the normal path for an
     *                                                                      order confirmation), the guard is driven by the ACTIVE TENANT'S
     *                                                                      `backorders_enabled` flag: backorders off → reject negatives (R17.1),
     *                                                                      backorders on → permit them and record the backordered quantity (R17.2).
     * @return list<StockLedger> The ledger entries written, in movement order.
     *
     * @throws InsufficientStockException when a movement would drive stock
     *                                    negative and backorders are disabled for the tenant — the whole batch
     *                                    is rejected and nothing is persisted (R17.1, R17.3).
     */
    public function apply(array $movements, array $context = []): array
    {
        if ($movements === []) {
            return [];
        }

        $actorId = $context['actor_id'] ?? null;

        // The negative-stock guard is driven by the per-tenant backorder toggle
        // (R17.2). An explicit `allow_negative` in the context overrides it so
        // internal callers and tests can pin the behaviour; otherwise we consult
        // the active tenant's `backorders_enabled` flag (task 9.5).
        $allowNegative = array_key_exists('allow_negative', $context)
            ? (bool) $context['allow_negative']
            : $this->tenantAllowsBackorders();

        $ledgerEntries = DB::connection($this->connection())->transaction(
            function () use ($movements, $actorId, $allowNegative): array {
                // 1. Lock every affected stock row FOR UPDATE in deterministic
                //    primary-key order (R16.5, R35.1). Rows that do not yet
                //    exist are created (and locked) at quantity 0 so an inward
                //    movement for a brand-new (owner, product, point) works.
                $rows = $this->lockStockRowsForUpdate($movements);

                // 2. Compute resulting quantities and run the negative-stock
                //    guard across the WHOLE batch before mutating anything, so
                //    the operation is all-or-nothing (R17.1). When backorders
                //    are enabled for the tenant the guard is skipped and the
                //    batch may go negative; the shortfall is then recorded as a
                //    negative balance_after on the ledger (R17.2).
                $resulting = $this->resultingQuantities($movements, $rows);

                if (! $allowNegative) {
                    $this->assertNoNegativeResult($movements, $rows, $resulting);
                }

                // 3. Apply increments/decrements and append one immutable ledger
                //    entry per movement with balance_after = post-movement
                //    quantity (R15.1, R15.3). Running balances are tracked per
                //    stock key so repeated movements on the same row chain
                //    correctly within the batch.
                $ledgerEntries = [];
                $running = [];

                foreach ($rows as $key => $row) {
                    $running[$key] = (string) $row->quantity;
                }

                foreach ($movements as $movement) {
                    $key = $movement->stockKey();
                    $newQuantity = $this->add($running[$key], $movement->delta);
                    $running[$key] = $newQuantity;

                    $row = $rows[$key];
                    $row->quantity = $newQuantity;
                    $row->save();

                    $ledgerEntries[] = StockLedger::create([
                        'stockable_type' => $movement->stockableType,
                        'stockable_id' => $movement->stockableId,
                        'product_id' => $movement->productId,
                        'stock_point_id' => $movement->stockPointId,
                        'movement_type' => $movement->movementType,
                        'quantity' => $this->scale($movement->delta),
                        'balance_after' => $newQuantity,
                        'reason' => $movement->reason,
                        'actor_id' => $actorId,
                        'reference' => $movement->reference,
                    ]);
                }

                return $ledgerEntries;
            }
        );

        // After commit: invalidate the tenant's cached report aggregates so the
        // next report read recomputes against the new stock/ledger state and can
        // never serve a stale aggregate (R29.8). Done post-commit so a
        // rolled-back batch never disturbs the cache.
        $this->reportCache->invalidate();

        return $ledgerEntries;
    }

    /**
     * Read + lock every distinct `stock` row targeted by the batch with
     * `SELECT ... FOR UPDATE`, ordered by primary key for deadlock-free locking
     * (R16.5, R35.1). A targeted row that does not exist yet is created at
     * quantity 0 and then re-locked, so inward movements for a new
     * (owner, product, stock_point) are supported without a race.
     *
     * @param  list<StockMovement>  $movements
     * @return array<string, Stock> Locked stock rows keyed by movement stock key.
     */
    private function lockStockRowsForUpdate(array $movements): array
    {
        // Collapse to the DISTINCT (owner, product, stock_point) targets; many
        // movements may hit the same row (e.g. a transfer touches two rows,
        // repeated adjustments touch one).
        $targets = [];
        foreach ($movements as $movement) {
            $targets[$movement->stockKey()] = $movement;
        }

        // Ensure a row exists for each target so FOR UPDATE has something to
        // lock, creating a zero-quantity row when absent. The owner morph
        // columns (`stockable_type`/`stockable_id`) are intentionally NOT
        // mass-assignable on the Stock model, so they are set explicitly via
        // forceFill rather than through firstOrCreate's attribute array.
        foreach ($targets as $movement) {
            $existing = Stock::query()
                ->where('stockable_type', $movement->stockableType)
                ->where('stockable_id', $movement->stockableId)
                ->where('product_id', $movement->productId)
                ->where('stock_point_id', $movement->stockPointId)
                ->first();

            if ($existing === null) {
                (new Stock)->forceFill([
                    'stockable_type' => $movement->stockableType,
                    'stockable_id' => $movement->stockableId,
                    'product_id' => $movement->productId,
                    'stock_point_id' => $movement->stockPointId,
                    'quantity' => $this->scale('0'),
                ])->save();
            }
        }

        // Lock the exact target rows in a single deterministic, primary-key
        // ordered FOR UPDATE read (R16.5). Ordering by `id` guarantees every
        // concurrent caller acquires locks in the same order, so two batches
        // touching an overlapping set of rows can never deadlock.
        $locked = Stock::query()
            ->where(function ($query) use ($targets): void {
                foreach ($targets as $movement) {
                    $query->orWhere(function ($q) use ($movement): void {
                        $q->where('stockable_type', $movement->stockableType)
                            ->where('stockable_id', $movement->stockableId)
                            ->where('product_id', $movement->productId)
                            ->where('stock_point_id', $movement->stockPointId);
                    });
                }
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $rows = [];
        foreach ($locked as $row) {
            $key = implode('|', [
                $row->stockable_type,
                (int) $row->stockable_id,
                (int) $row->product_id,
                (int) $row->stock_point_id,
            ]);
            $rows[$key] = $row;
        }

        return $rows;
    }

    /**
     * Compute the final resulting quantity per stock key after applying every
     * movement in the batch to that key, in order.
     *
     * @param  list<StockMovement>  $movements
     * @param  array<string, Stock>  $rows
     * @return array<string, string> Resulting quantity (decimal string) per stock key.
     */
    private function resultingQuantities(array $movements, array $rows): array
    {
        $resulting = [];
        foreach ($rows as $key => $row) {
            $resulting[$key] = (string) $row->quantity;
        }

        foreach ($movements as $movement) {
            $key = $movement->stockKey();
            $resulting[$key] = $this->add($resulting[$key], $movement->delta);
        }

        return $resulting;
    }

    /**
     * Reject the WHOLE batch if any resulting quantity is negative (R17.1).
     *
     * Collects every offending movement so the response can list all
     * insufficient products at once (R17.3), then throws — nothing has been
     * mutated yet, so the all-or-nothing guarantee holds.
     *
     * @param  list<StockMovement>  $movements
     * @param  array<string, Stock>  $rows
     * @param  array<string, string>  $resulting
     *
     * @throws InsufficientStockException when one or more results are negative.
     */
    private function assertNoNegativeResult(array $movements, array $rows, array $resulting): void
    {
        $offenders = [];

        foreach ($resulting as $key => $quantity) {
            if ($this->isNegative($quantity)) {
                // Find a representative movement for this key to describe the
                // offender (product + stock point + requested delta).
                foreach ($movements as $movement) {
                    if ($movement->stockKey() === $key) {
                        $offenders[] = [
                            'product_id' => $movement->productId,
                            'stock_point_id' => $movement->stockPointId,
                            'requested' => $this->scale($movement->delta),
                            'available' => (string) $rows[$key]->quantity,
                        ];
                        break;
                    }
                }
            }
        }

        if ($offenders !== []) {
            throw new InsufficientStockException($offenders);
        }
    }

    /**
     * Add two fixed-scale decimal strings exactly, returning a string at the
     * stock scale. Uses bcmath when available; otherwise falls back to
     * scaled-integer arithmetic so exactness is preserved without a hard
     * extension dependency.
     */
    private function add(string $a, string $b): string
    {
        if (function_exists('bcadd')) {
            return bcadd($a, $b, self::SCALE);
        }

        $sum = $this->toScaledInt($a) + $this->toScaledInt($b);

        return $this->fromScaledInt($sum);
    }

    /**
     * Whether a fixed-scale decimal string is strictly less than zero.
     */
    private function isNegative(string $value): bool
    {
        if (function_exists('bccomp')) {
            return bccomp($value, '0', self::SCALE) < 0;
        }

        return $this->toScaledInt($value) < 0;
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
     * for exact integer arithmetic. Used only on the bcmath-less fallback path.
     */
    private function toScaledInt(string $value): int
    {
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');

        $parts = explode('.', $value, 2);
        $whole = $parts[0] === '' ? '0' : $parts[0];
        $frac = $parts[1] ?? '';
        $frac = substr(str_pad($frac, self::SCALE, '0'), 0, self::SCALE);

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

    /**
     * Whether the ACTIVE TENANT permits backorders, i.e. allows an order
     * confirmation to drive stock below zero (R17.2). This is the per-tenant
     * `backorders_enabled` toggle read from the CENTRAL `tenants` row that
     * stancl/tenancy resolved for the current request.
     *
     * Defaults to false — negative stock rejected (R17.1) — when there is no
     * active tenancy context (e.g. the service is exercised outside a resolved
     * tenant, as in some unit tests) or the flag is unset, so the safe,
     * oversell-preventing behaviour is the default.
     */
    private function tenantAllowsBackorders(): bool
    {
        if (! function_exists('tenant')) {
            return false;
        }

        $tenant = tenant();

        if (! $tenant instanceof Tenant) {
            return false;
        }

        return (bool) $tenant->backorders_enabled;
    }

    /**
     * The Laravel connection name for the active tenant database. Stock and the
     * ledger always live in the tenant database (physical isolation).
     */
    private function connection(): string
    {
        return 'tenant';
    }
}
