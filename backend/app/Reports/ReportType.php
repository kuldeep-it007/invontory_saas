<?php

declare(strict_types=1);

namespace App\Reports;

/**
 * The closed set of report types the {@see ReportQueryService} can execute
 * (design "Reporting Engine" — the `type` field of a ReportQuerySpec).
 *
 * This enum is the single allow-list of report `type` values shared by both the
 * standard report catalog (task 11.6) and the AI layer (task 13): a natural
 * language request is mapped to a {@see ReportQuerySpec} whose type MUST be one
 * of these cases, and anything outside the set is rejected before any query runs
 * (R31.2). Keeping the catalog here — rather than as free-form strings — is what
 * makes "allow-listed" enforceable in one place for every caller.
 *
 * Each case corresponds to a standard report in Requirement 28:
 *   - STOCK_ON_HAND        per owner and stock point (R28.1)
 *   - STOCK_LEDGER         stock movement listing (R28.2)
 *   - SALES                grouped by period/distributor/dealer/product/category (R28.3)
 *   - PURCHASES            grouped by period and buyer (R28.4)
 *   - OUTSTANDING_AGEING   ageing buckets current/1-30/31-60/61-90/90+ (R28.5)
 *   - PAYMENTS             payments/collections grouped by period (R28.6)
 *   - ORDER_STATUS         order-status / delivery-challan listing (R28.7)
 *   - LOW_STOCK            threshold-breach report (R28.8)
 *   - PLAN_USAGE           plan-usage per tenant (R28.9)
 */
enum ReportType: string
{
    case STOCK_ON_HAND = 'stock_on_hand';
    case STOCK_LEDGER = 'stock_ledger';
    case SALES = 'sales';
    case PURCHASES = 'purchases';
    case OUTSTANDING_AGEING = 'outstanding_ageing';
    case PAYMENTS = 'payments';
    case ORDER_STATUS = 'order_status';
    case LOW_STOCK = 'low_stock';
    case PLAN_USAGE = 'plan_usage';

    /**
     * Resolve a report type from its wire value, returning null when the value
     * is not an allow-listed type. Callers (the HTTP layer, the AI validator)
     * use this to reject an unknown `type` BEFORE building a spec, so an invalid
     * type never reaches query execution (R31.2, R31.5).
     */
    public static function tryFromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }

    /**
     * All report-type wire values as a flat list — the allow-list the schema
     * validator checks a requested `type` against.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
