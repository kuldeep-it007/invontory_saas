<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CreditLimitExceededException;
use App\Exceptions\OverdueBalanceExceededException;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PaymentAllocation;
use App\Support\AuditRecorder;
use App\Support\PaymentTerms;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Credit & overdue enforcement authority (R26, design "Payments & Accounts").
 *
 * The single, focused place that enforces a Buyer's Payment_Terms. It is
 * deliberately self-contained so the sibling M4 services can call it at well
 * defined seams without this task depending on their completion:
 *
 *   • {@see assertCreditWithinLimit()} — the OrderConfirmationService (task 9.7)
 *     calls this INSIDE the confirmation transaction, right before persisting
 *     the Confirmed state, to block confirmation when the projected outstanding
 *     (current O + order total T) exceeds the credit limit L — evaluated ONLY
 *     at confirmation time (R26.2, Property 24).
 *   • {@see assertNotOverdueBlocked()} — the order-placement path calls this
 *     when a Buyer places a NEW order, blocking it when the Buyer's overdue
 *     balance exceeds the overdue balance limit (R26.4, Property 25).
 *   • {@see flagOverdueInvoices()} / {@see isInvoiceOverdue()} — flag invoices
 *     unpaid beyond the configured overdue hold days (R26.3, Property 25).
 *
 * ## Integration seams
 *
 * - **Outstanding_Balance** is read through {@see AccountLedgerService::outstandingBalance()}
 *   (task 9.10) — the single source of truth for the latest transaction-ledger
 *   `balance_after` (R25.2). This service never touches the ledger directly, so
 *   if the ledger machinery evolves, only that one method matters here.
 * - **Overdue balance** is derived from the Buyer's flagged-overdue invoices
 *   net of their allocations (what is still owed on invoices past their hold
 *   window). This keeps the task self-contained without requiring the
 *   `overdue_amounts` projection table; it can be swapped for that table later
 *   behind the same {@see overdueBalance()} method.
 *
 * ## Overrides (R26.5)
 *
 * Every blocking check accepts an optional authorized override: a non-empty
 * reason that permits the otherwise-blocked action. When an override is applied
 * the service records it in the Audit_Log via {@see AuditRecorder} with the
 * reason in the dedicated `reason` column (R26.5, R34.3), then allows the action
 * to proceed. An absent/empty override leaves the block in force.
 *
 * Monetary values are fixed-scale DECIMAL(15,2) decimal strings compared with
 * exact (bcmath / scaled-integer) arithmetic — never floats — so the
 * "exceeds the limit" boundary is exact (R39.1).
 */
class CreditEnforcementService
{
    /**
     * The DECIMAL(15,2) monetary scale shared with the ledger and Payment_Terms.
     */
    private const SCALE = 2;

    public function __construct(
        private readonly AccountLedgerService $ledger,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * Assert that confirming an order for `$buyer` with total `$orderTotal`
     * keeps the Buyer's projected Outstanding_Balance within the credit limit
     * (R26.2, Property 24).
     *
     * Projected outstanding = current Outstanding_Balance O + order total T.
     * Confirmation is blocked if and only if O + T EXCEEDS the credit limit L
     * (strictly greater — reaching the limit exactly is allowed). When no
     * credit limit is configured the action is never blocked.
     *
     * This is intended to run at CONFIRMATION time only; callers must not invoke
     * it while an order is still Draft/Placed, preserving R26.2's "only at the
     * time the confirmation is requested" guarantee.
     *
     * @param  Model  $buyer  The Distributor/Dealer whose order is being confirmed.
     * @param  string  $orderTotal  The order total T as a decimal string.
     * @param  string|null  $overrideReason  An authorized override reason; when non-empty the
     *                                        block is lifted and recorded in the Audit_Log (R26.5).
     *
     * @throws CreditLimitExceededException when projected outstanding exceeds the limit and no override is given.
     */
    public function assertCreditWithinLimit(Model $buyer, string $orderTotal, ?string $overrideReason = null): void
    {
        $terms = PaymentTerms::fromBuyer($buyer);

        if (! $terms->hasCreditLimit()) {
            return;
        }

        $outstanding = $this->scale($this->ledger->outstandingBalance($buyer));
        $total = $this->scale($orderTotal);
        $projected = $this->add($outstanding, $total);
        $limit = $this->scale((string) $terms->creditLimit);

        // Blocked iff projected outstanding strictly exceeds the limit (R26.2).
        if ($this->compare($projected, $limit) <= 0) {
            return;
        }

        if ($this->applyOverride($buyer, 'credit_limit_override', $overrideReason, [
            'check' => 'credit_limit',
            'outstanding' => $outstanding,
            'order_total' => $total,
            'projected_outstanding' => $projected,
            'credit_limit' => $limit,
        ])) {
            return;
        }

        throw new CreditLimitExceededException($outstanding, $total, $projected, $limit);
    }

    /**
     * Assert that `$buyer` is not blocked from placing a NEW order by an overdue
     * balance above its overdue balance limit (R26.4, Property 25).
     *
     * The Buyer is blocked if and only if its overdue balance EXCEEDS the limit
     * (strictly greater). When no overdue balance limit is configured the Buyer
     * is never blocked on this check.
     *
     * @param  Model  $buyer  The Distributor/Dealer placing a new order.
     * @param  string|null  $overrideReason  An authorized override reason; when non-empty the
     *                                        block is lifted and recorded in the Audit_Log (R26.5).
     *
     * @throws OverdueBalanceExceededException when the overdue balance exceeds the limit and no override is given.
     */
    public function assertNotOverdueBlocked(Model $buyer, ?string $overrideReason = null): void
    {
        $terms = PaymentTerms::fromBuyer($buyer);

        if (! $terms->hasOverdueBalanceLimit()) {
            return;
        }

        $overdue = $this->scale($this->overdueBalance($buyer));
        $limit = $this->scale((string) $terms->overdueBalanceLimit);

        if ($this->compare($overdue, $limit) <= 0) {
            return;
        }

        if ($this->applyOverride($buyer, 'overdue_block_override', $overrideReason, [
            'check' => 'overdue_balance',
            'overdue_balance' => $overdue,
            'overdue_balance_limit' => $limit,
        ])) {
            return;
        }

        throw new OverdueBalanceExceededException($overdue, $limit);
    }

    /**
     * Whether a single invoice is overdue relative to a Buyer's overdue hold
     * days as of `$asOf` (R26.3, Property 25).
     *
     * An unpaid invoice is overdue if and only if its age in whole days SINCE
     * issue exceeds the configured hold days (strictly greater — an invoice
     * exactly at the hold-day boundary is not yet overdue). A fully-paid invoice
     * is never overdue, and when the Buyer has no hold-days configured no
     * invoice is auto-flagged by age.
     *
     * @param  int  $overdueHoldDays  The Buyer's configured overdue hold days.
     * @param  Carbon|null  $asOf  The reference "now"; defaults to the current time.
     */
    public function isInvoiceOverdue(Invoice $invoice, int $overdueHoldDays, ?Carbon $asOf = null): bool
    {
        $asOf ??= Carbon::now();

        // A fully-settled invoice is never overdue regardless of age.
        if ($this->compare($this->invoiceOutstanding($invoice), $this->scale('0')) <= 0) {
            return false;
        }

        $issuedAt = $invoice->created_at instanceof Carbon
            ? $invoice->created_at
            : Carbon::parse((string) $invoice->created_at);

        // Age in whole days since issue. An invoice issued in the future (clock
        // skew / backdated reference) has a non-positive age and is not overdue.
        if ($asOf->lessThanOrEqualTo($issuedAt)) {
            return false;
        }

        $ageInDays = (int) floor($issuedAt->diffInDays($asOf, true));

        return $ageInDays > $overdueHoldDays;
    }

    /**
     * Flag every invoice for `$buyer` that is now overdue, and clear the flag on
     * any invoice that is no longer overdue (e.g. after a payment settled it),
     * returning the number of invoices whose flag changed (R26.3).
     *
     * Idempotent: invoices already in the correct state are left untouched. When
     * the Buyer has no overdue hold days configured, nothing is flagged.
     *
     * @param  Model  $buyer  The Distributor/Dealer whose invoices are evaluated.
     * @param  Carbon|null  $asOf  The reference "now"; defaults to the current time.
     * @return int The number of invoices whose `overdue` flag changed.
     */
    public function flagOverdueInvoices(Model $buyer, ?Carbon $asOf = null): int
    {
        $terms = PaymentTerms::fromBuyer($buyer);

        if (! $terms->hasOverdueHoldDays()) {
            return 0;
        }

        $asOf ??= Carbon::now();
        $holdDays = (int) $terms->overdueHoldDays;
        $changed = 0;

        foreach ($this->buyerInvoices($buyer) as $invoice) {
            $shouldBeOverdue = $this->isInvoiceOverdue($invoice, $holdDays, $asOf);

            if ((bool) $invoice->overdue === $shouldBeOverdue) {
                continue;
            }

            $invoice->overdue = $shouldBeOverdue;
            $invoice->save();
            $changed++;
        }

        return $changed;
    }

    /**
     * A Buyer's overdue balance: the total still owed on invoices currently
     * flagged overdue (R26.4). Derived from the invoices + allocations in the
     * tenant database so this task does not depend on the `overdue_amounts`
     * projection table; swapping in that table later only changes this method.
     *
     * @param  Model  $buyer  The Distributor/Dealer to total overdue invoices for.
     * @return string The overdue balance as a decimal string.
     */
    public function overdueBalance(Model $buyer): string
    {
        $total = $this->scale('0');

        foreach ($this->buyerInvoices($buyer)->where('overdue', true) as $invoice) {
            $total = $this->add($total, $this->invoiceOutstanding($invoice));
        }

        return $total;
    }

    /**
     * The amount still outstanding on a single invoice: its amount minus the sum
     * of its payment allocations, floored at zero. Used both to total the
     * overdue balance and to decide whether an invoice can still be overdue.
     */
    private function invoiceOutstanding(Invoice $invoice): string
    {
        $amount = $this->scale((string) $invoice->amount);

        $allocated = $this->scale((string) (PaymentAllocation::query()
            ->where('invoice_id', $invoice->getKey())
            ->sum('amount') ?? '0'));

        $remaining = $this->subtract($amount, $allocated);

        return $this->compare($remaining, $this->scale('0')) < 0
            ? $this->scale('0')
            : $remaining;
    }

    /**
     * Query the invoices belonging to a Buyer by joining through the Buyer's
     * orders (the invoice carries the SELLER morph, not the buyer, so the Buyer
     * link is the order). Returns an eager collection of the Buyer's invoices.
     *
     * @return \Illuminate\Support\Collection<int, Invoice>
     */
    private function buyerInvoices(Model $buyer): \Illuminate\Support\Collection
    {
        $orderIds = Order::query()
            ->where('buyer_type', $buyer->getMorphClass())
            ->where('buyer_id', (int) $buyer->getKey())
            ->pluck('id');

        if ($orderIds->isEmpty()) {
            return collect();
        }

        return Invoice::query()
            ->whereIn('order_id', $orderIds)
            ->get();
    }

    /**
     * Record an authorized override in the Audit_Log and report whether one was
     * applied (R26.5). An override is applied when `$reason` is a non-empty,
     * non-whitespace string; the action is then permitted and audited with the
     * reason in the dedicated `reason` column (R34.3). An absent/empty reason
     * applies no override, so the caller's block stays in force.
     *
     * @param  array<string, mixed>  $context  Non-sensitive context describing the lifted block.
     */
    private function applyOverride(Model $buyer, string $event, ?string $reason, array $context): bool
    {
        $trimmed = trim((string) $reason);

        if ($trimmed === '') {
            return false;
        }

        $this->audit->record(
            $event,
            auth()->user(),
            array_merge($context, [
                'buyer_type' => $buyer->getMorphClass(),
                'buyer_id' => (int) $buyer->getKey(),
            ]),
            reason: $trimmed,
        );

        return true;
    }

    /**
     * Compare two fixed-scale decimal strings: -1 if $a < $b, 0 if equal, 1 if
     * $a > $b. Uses bcmath when available; otherwise exact scaled-integer
     * arithmetic so the boundary comparison never loses precision.
     */
    private function compare(string $a, string $b): int
    {
        if (function_exists('bccomp')) {
            return bccomp($a, $b, self::SCALE);
        }

        $ai = $this->toScaledInt($a);
        $bi = $this->toScaledInt($b);

        return $ai <=> $bi;
    }

    /**
     * Add two fixed-scale decimal strings exactly.
     */
    private function add(string $a, string $b): string
    {
        if (function_exists('bcadd')) {
            return bcadd($a, $b, self::SCALE);
        }

        return $this->fromScaledInt($this->toScaledInt($a) + $this->toScaledInt($b));
    }

    /**
     * Subtract two fixed-scale decimal strings exactly ($a - $b).
     */
    private function subtract(string $a, string $b): string
    {
        if (function_exists('bcsub')) {
            return bcsub($a, $b, self::SCALE);
        }

        return $this->fromScaledInt($this->toScaledInt($a) - $this->toScaledInt($b));
    }

    /**
     * Normalize a numeric string to the fixed monetary scale (DECIMAL(15,2)).
     */
    private function scale(string $value): string
    {
        $value = trim($value);

        if ($value === '' || ! is_numeric($value)) {
            $value = '0';
        }

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
}
