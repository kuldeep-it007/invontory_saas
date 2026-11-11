<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TransactionLedger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The single authority for every Buyer account movement in the platform
 * (design "Payments & Accounts"). All account changes — a confirmed purchase
 * (R25.1), a recorded payment (R24.2), and a return adjustment (R22.3) — append
 * through this service so the transaction-ledger balance invariant holds in
 * exactly one place: each entry's `balance_after` equals the previous
 * `balance_after` for that Buyer plus the entry's signed `amount` (R24.2,
 * R25.1), and a Buyer's Outstanding_Balance is the latest `balance_after`
 * (R25.2).
 *
 * This mirrors {@see StockMovementService} for stock: an immutable, append-only
 * ledger ({@see TransactionLedger}) is the single source of truth, written
 * inside the caller's transaction so the account change and its side effects
 * are atomic.
 *
 * ## Sign convention
 *
 * - A **purchase** increases what the Buyer owes → positive `amount`.
 * - A **payment** reduces the outstanding balance → negative `amount`.
 * - A **return** reduces what the Buyer owes → negative `amount`.
 *
 * Monetary values are handled as fixed-scale (DECIMAL(15,2)) decimal strings
 * and never as floats, so the balance-after invariant is exact (R25.1).
 */
class AccountLedgerService
{
    /**
     * The fixed decimal scale of monetary amounts (DECIMAL(15,2)).
     */
    private const SCALE = 2;

    /**
     * Append a confirmed-purchase entry to a Buyer's transaction ledger (R25.1).
     *
     * @param  Model  $buyer  The Distributor/Dealer whose account is charged.
     * @param  string  $amount  The purchase total (positive) as a decimal string.
     * @param  array{reference?: string|null, actor_id?: int|null}  $context
     */
    public function appendPurchase(Model $buyer, string $amount, array $context = []): TransactionLedger
    {
        return $this->append(
            $buyer,
            TransactionLedger::ENTRY_PURCHASE,
            $this->abs($amount),
            $context,
        );
    }

    /**
     * Append a payment entry to a Buyer's transaction ledger (R24.2).
     *
     * The payment reduces the outstanding balance, so a positive payment amount
     * is recorded as a negative ledger `amount`.
     *
     * @param  Model  $buyer  The Distributor/Dealer whose account is credited.
     * @param  string  $amount  The payment amount (positive) as a decimal string.
     * @param  array{reference?: string|null, actor_id?: int|null, payment_id?: int|null}  $context
     */
    public function appendPayment(Model $buyer, string $amount, array $context = []): TransactionLedger
    {
        return $this->append(
            $buyer,
            TransactionLedger::ENTRY_PAYMENT,
            $this->negate($this->abs($amount)),
            $context,
        );
    }

    /**
     * Append a return adjustment to a Buyer's transaction ledger (R22.3).
     *
     * A return reduces what the Buyer owes, so a positive return amount is
     * recorded as a negative ledger `amount`.
     *
     * @param  Model  $buyer  The Distributor/Dealer whose account is credited.
     * @param  string  $amount  The returned value (positive) as a decimal string.
     * @param  array{reference?: string|null, actor_id?: int|null}  $context
     */
    public function appendReturn(Model $buyer, string $amount, array $context = []): TransactionLedger
    {
        return $this->append(
            $buyer,
            TransactionLedger::ENTRY_RETURN,
            $this->negate($this->abs($amount)),
            $context,
        );
    }

    /**
     * Append a cancellation reversal to a Buyer's transaction ledger (R22.1).
     *
     * Cancelling a confirmed order must make the cancelled purchase no longer
     * count toward the Buyer's Outstanding_Balance. The confirmation appended a
     * positive purchase (what the Buyer owed), so the reversal records the SAME
     * magnitude as a negative ledger `amount`, exactly backing it out: the
     * running balance returns to what it was before the confirmation's purchase
     * was appended (R22.1, R25.1, R25.2).
     *
     * @param  Model  $buyer  The Distributor/Dealer whose account is reversed.
     * @param  string  $amount  The cancelled purchase total (positive) as a decimal string.
     * @param  array{reference?: string|null, actor_id?: int|null}  $context
     */
    public function appendCancellation(Model $buyer, string $amount, array $context = []): TransactionLedger
    {
        return $this->append(
            $buyer,
            TransactionLedger::ENTRY_CANCELLATION,
            $this->negate($this->abs($amount)),
            $context,
        );
    }

    /**
     * A Buyer's Outstanding_Balance: the latest `balance_after` for that Buyer,
     * or 0.00 when the Buyer has no ledger entries yet (R25.2).
     */
    public function outstandingBalance(Model $buyer): string
    {
        $latest = $this->latestEntryQuery($buyer)->first();

        return $latest === null
            ? $this->scale('0')
            : $this->scale((string) $latest->balance_after);
    }

    /**
     * Append one immutable ledger entry for a Buyer with
     * `balance_after = previous balance + signed amount` (R24.2, R25.1).
     *
     * The read of the previous balance and the append run inside a single
     * transaction with the previous row locked FOR UPDATE, so concurrent
     * appends for the SAME Buyer serialize and the running balance can never
     * fork. When the caller is already inside a transaction (the confirmation /
     * payment flow), this nests via a savepoint and participates in the outer
     * atomicity.
     *
     * @param  array{reference?: string|null, actor_id?: int|null, payment_id?: int|null}  $context
     */
    private function append(Model $buyer, string $entryType, string $signedAmount, array $context): TransactionLedger
    {
        return DB::connection($this->connection())->transaction(
            function () use ($buyer, $entryType, $signedAmount, $context): TransactionLedger {
                // Lock the Buyer's latest entry (if any) so the previous balance
                // we read cannot change under us while we compute and append the
                // next one — the per-Buyer running balance stays linear.
                $previous = $this->latestEntryQuery($buyer)->lockForUpdate()->first();

                $previousBalance = $previous === null
                    ? $this->scale('0')
                    : $this->scale((string) $previous->balance_after);

                $balanceAfter = $this->add($previousBalance, $signedAmount);

                return TransactionLedger::create([
                    'buyer_type' => $buyer->getMorphClass(),
                    'buyer_id' => (int) $buyer->getKey(),
                    'entry_type' => $entryType,
                    'amount' => $this->scale($signedAmount),
                    'balance_after' => $balanceAfter,
                    'payment_id' => $context['payment_id'] ?? null,
                    'reference' => $context['reference'] ?? null,
                    'actor_id' => $context['actor_id'] ?? null,
                ]);
            }
        );
    }

    /**
     * Query the Buyer's transaction-ledger entries newest-first (highest id).
     * `id` is monotonically increasing, so the newest row is the current
     * balance holder — matching the (buyer_type, buyer_id, id) index.
     *
     * @return Builder<TransactionLedger>
     */
    private function latestEntryQuery(Model $buyer)
    {
        return TransactionLedger::query()
            ->where('buyer_type', $buyer->getMorphClass())
            ->where('buyer_id', (int) $buyer->getKey())
            ->orderByDesc('id');
    }

    /**
     * Add two fixed-scale decimal strings exactly, returning a string at the
     * monetary scale. Uses bcmath when available; otherwise falls back to
     * scaled-integer arithmetic so exactness is preserved without a hard
     * extension dependency. Mirrors {@see StockMovementService::add()}.
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
     * The absolute value of a fixed-scale decimal string (strip any leading
     * minus), normalized to the monetary scale.
     */
    private function abs(string $value): string
    {
        return $this->scale(ltrim($value, '+-') === '' ? '0' : ltrim($value, '+-'));
    }

    /**
     * Negate a fixed-scale decimal string, treating -0 as 0.
     */
    private function negate(string $value): string
    {
        $scaled = $this->toScaledInt($this->scale($value));

        return $this->fromScaledInt(-$scaled);
    }

    /**
     * Normalize a numeric string to the fixed monetary scale (DECIMAL(15,2)).
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
     * The Laravel connection name for the active tenant database. The
     * transaction ledger always lives in the tenant database (physical
     * isolation).
     */
    private function connection(): string
    {
        return 'tenant';
    }
}
