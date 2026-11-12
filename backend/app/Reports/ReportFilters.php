<?php

declare(strict_types=1);

namespace App\Reports;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * The immutable filter set accepted by every report (R29.1).
 *
 * A report request may constrain its result by:
 *   - a date range (`from`..`to`, inclusive),
 *   - a buyer or seller (polymorphic morph type + id),
 *   - a product or category,
 *   - a stock point,
 *   - a status.
 *
 * Every field is OPTIONAL. The one defaulting rule lives here: when NO date
 * range is supplied, {@see withDefaultDateRange()} resolves it to the previous
 * calendar month — the first instant of its first day through the last instant
 * of its last day (R29.2). Resolving the default in the value object (not in
 * each report) means every report, standard or AI-driven, inherits the exact
 * same previous-calendar-month default from the one shared query layer.
 *
 * Dates are held as {@see CarbonImmutable} so a resolved range can never be
 * mutated out from under a report after authorization/defaulting.
 */
final class ReportFilters
{
    public function __construct(
        public readonly ?CarbonImmutable $from = null,
        public readonly ?CarbonImmutable $to = null,
        public readonly ?string $buyerType = null,
        public readonly ?int $buyerId = null,
        public readonly ?string $sellerType = null,
        public readonly ?int $sellerId = null,
        public readonly ?int $productId = null,
        public readonly ?int $categoryId = null,
        public readonly ?int $stockPointId = null,
        public readonly ?string $status = null,
    ) {}

    /**
     * Build a filter set from a loosely-typed array (e.g. a decoded request
     * body or an AI-produced spec). Unknown keys are ignored; date values may be
     * any {@see DateTimeInterface} or a parseable date string and are normalized
     * to {@see CarbonImmutable}. Ids are coerced to int (or null).
     *
     * @param  array<string, mixed>  $filters
     */
    public static function fromArray(array $filters): self
    {
        return new self(
            from: self::toDate($filters['from'] ?? $filters['dateFrom'] ?? null),
            // A date-only "to" (e.g. 2026-11-12) means the END of that day, so the
            // range is genuinely inclusive as documented.
            to: self::toDate($filters['to'] ?? $filters['dateTo'] ?? null, endOfDay: true),
            buyerType: self::toStringOrNull($filters['buyerType'] ?? null),
            buyerId: self::toIntOrNull($filters['buyerId'] ?? null),
            sellerType: self::toStringOrNull($filters['sellerType'] ?? null),
            sellerId: self::toIntOrNull($filters['sellerId'] ?? null),
            productId: self::toIntOrNull($filters['productId'] ?? null),
            categoryId: self::toIntOrNull($filters['categoryId'] ?? null),
            stockPointId: self::toIntOrNull($filters['stockPointId'] ?? null),
            status: self::toStringOrNull($filters['status'] ?? null),
        );
    }

    /**
     * Whether the request supplied a date range. A range is considered present
     * only when BOTH ends are set — a half-open request still defaults to the
     * previous calendar month so the window is always well-defined (R29.2).
     */
    public function hasDateRange(): bool
    {
        return $this->from !== null && $this->to !== null;
    }

    /**
     * Return a copy whose date range is guaranteed set: the supplied range when
     * present, otherwise the previous calendar month relative to `$relativeTo`
     * (default: now) — first day 00:00:00.000000 through last day
     * 23:59:59.999999, inclusive (R29.2).
     *
     * The month is computed by stepping back one calendar month from the
     * reference date and taking that month's first and last day, so a request on
     * the 1st, the 31st, or across a year boundary (e.g. January → previous
     * December) all resolve correctly.
     */
    public function withDefaultDateRange(?DateTimeInterface $relativeTo = null): self
    {
        if ($this->hasDateRange()) {
            return $this;
        }

        $reference = $relativeTo !== null
            ? CarbonImmutable::instance(CarbonImmutable::parse($relativeTo->format(DateTimeInterface::ATOM)))
            : CarbonImmutable::now();

        $previousMonth = $reference->subMonthNoOverflow()->startOfMonth();

        return new self(
            from: $previousMonth->startOfMonth(),
            to: $previousMonth->endOfMonth(),
            buyerType: $this->buyerType,
            buyerId: $this->buyerId,
            sellerType: $this->sellerType,
            sellerId: $this->sellerId,
            productId: $this->productId,
            categoryId: $this->categoryId,
            stockPointId: $this->stockPointId,
            status: $this->status,
        );
    }

    /**
     * Return a copy with the buyer scope forced to the given morph type + id.
     * Used by role scoping to confine a Distributor/Dealer report to its own
     * account data regardless of what the request asked for (R28.10).
     */
    public function scopedToBuyer(string $buyerType, int $buyerId): self
    {
        return new self(
            from: $this->from,
            to: $this->to,
            buyerType: $buyerType,
            buyerId: $buyerId,
            sellerType: $this->sellerType,
            sellerId: $this->sellerId,
            productId: $this->productId,
            categoryId: $this->categoryId,
            stockPointId: $this->stockPointId,
            status: $this->status,
        );
    }

    private static function toDate(mixed $value, bool $endOfDay = false): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance(CarbonImmutable::parse($value->format(DateTimeInterface::ATOM)));
        }

        $parsed = CarbonImmutable::parse((string) $value);

        // Only a bare date (no time component) is widened to the end of the day;
        // an explicit time such as "2026-11-12 10:00:00" is respected as given.
        if ($endOfDay && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) $value)) === 1) {
            return $parsed->endOfDay();
        }

        return $parsed;
    }

    private static function toIntOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private static function toStringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
