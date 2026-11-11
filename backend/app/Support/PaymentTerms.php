<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Typed view over a Buyer's Payment_Terms (R26.1).
 *
 * Payment_Terms are stored as a flexible JSON blob on each Buyer record
 * (Distributor / Dealer `payment_terms` column) so the terms schema can evolve
 * without a migration. This value object is the single place that parses that
 * blob into the four typed terms the credit/overdue enforcement layer reasons
 * about (R26.1):
 *
 *   • `creditLimit`         — the maximum projected Outstanding_Balance a Buyer
 *                             may reach; `null` means "no credit limit set"
 *                             (unlimited credit, R26.2).
 *   • `overdueBalanceLimit` — the maximum overdue balance a Buyer may carry
 *                             before being blocked from placing new Orders;
 *                             `null` means "no overdue cap" (R26.4).
 *   • `overdueHoldDays`     — the number of days an unpaid invoice may age
 *                             before it is flagged overdue; `null` means "never
 *                             auto-flag by age" (R26.3).
 *   • `paymentType`         — the selling relationship's payment type label
 *                             (e.g. `credit`, `cash`, `advance`), a free-form
 *                             string carried for reporting / policy use (R26.1).
 *
 * Monetary limits are kept as fixed-scale DECIMAL(15,2) decimal strings so they
 * never lose precision against the equally-exact ledger balances they are
 * compared with. A missing, empty, or non-numeric limit parses to `null`
 * (absent), which the enforcement service treats as "no limit" — the safe,
 * non-blocking default so an unconfigured Buyer is never silently blocked.
 *
 * The object is immutable: construct it once from a Buyer (or a raw array) and
 * read the typed accessors.
 */
final class PaymentTerms
{
    /**
     * The DECIMAL(15,2) scale shared with the monetary ledger so limit
     * comparisons line up exactly with balances.
     */
    private const SCALE = 2;

    /**
     * @param  string|null  $creditLimit  Credit limit as a decimal string, or null when unset (unlimited).
     * @param  string|null  $overdueBalanceLimit  Overdue balance limit as a decimal string, or null when unset.
     * @param  int|null  $overdueHoldDays  Overdue hold days (>= 0), or null when unset.
     * @param  string|null  $paymentType  Free-form payment type label, or null when unset.
     */
    public function __construct(
        public readonly ?string $creditLimit,
        public readonly ?string $overdueBalanceLimit,
        public readonly ?int $overdueHoldDays,
        public readonly ?string $paymentType,
    ) {}

    /**
     * Build the terms from a Buyer model, reading its `payment_terms` JSON
     * (cast to an array on the Distributor/Dealer models). A Buyer with no terms
     * yet yields an all-absent instance (no limits), so it is never blocked.
     */
    public static function fromBuyer(Model $buyer): self
    {
        /** @var mixed $raw */
        $raw = $buyer->getAttribute('payment_terms');

        return self::fromArray(is_array($raw) ? $raw : []);
    }

    /**
     * Parse a raw Payment_Terms array into the typed value object. Unknown
     * keys are ignored; missing/empty/invalid limits become `null` (absent).
     *
     * @param  array<string, mixed>  $terms
     */
    public static function fromArray(array $terms): self
    {
        return new self(
            creditLimit: self::parseAmount($terms['credit_limit'] ?? null),
            overdueBalanceLimit: self::parseAmount($terms['overdue_balance_limit'] ?? null),
            overdueHoldDays: self::parseDays($terms['overdue_hold_days'] ?? null),
            paymentType: self::parseString($terms['payment_type'] ?? null),
        );
    }

    /**
     * Whether a credit limit is configured (R26.2). When false, projected
     * outstanding is never blocked on credit.
     */
    public function hasCreditLimit(): bool
    {
        return $this->creditLimit !== null;
    }

    /**
     * Whether an overdue balance limit is configured (R26.4). When false, the
     * Buyer is never blocked from new Orders on overdue balance.
     */
    public function hasOverdueBalanceLimit(): bool
    {
        return $this->overdueBalanceLimit !== null;
    }

    /**
     * Whether an overdue hold-days window is configured (R26.3). When false,
     * invoices are never auto-flagged overdue by age.
     */
    public function hasOverdueHoldDays(): bool
    {
        return $this->overdueHoldDays !== null;
    }

    /**
     * Normalize a raw limit to a fixed-scale decimal string, or null when it is
     * absent, empty, or non-numeric (treated as "no limit set").
     */
    private static function parseAmount(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        if (! is_string($value) || ! is_numeric(trim($value))) {
            return null;
        }

        $value = trim($value);

        if (function_exists('bcadd')) {
            return bcadd($value, '0', self::SCALE);
        }

        return number_format((float) $value, self::SCALE, '.', '');
    }

    /**
     * Normalize a raw hold-days value to a non-negative integer, or null when
     * absent or invalid. A negative value clamps to 0.
     */
    private static function parseDays(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $days = (int) $value;

        return $days < 0 ? 0 : $days;
    }

    /**
     * Normalize a raw payment-type label to a trimmed string, or null when
     * absent/empty.
     */
    private static function parseString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
