<?php

declare(strict_types=1);

namespace App\Reports\Definitions;

use App\Reports\ReportFilters;
use App\Reports\ReportType;
use App\Services\ReportQueryService;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * Payments / collections report: amounts collected over the period, grouped by
 * period (and optionally by Buyer and method) (R28.6).
 *
 * The report aggregates the manually recorded {@see \App\Models\Payment} rows,
 * summing collected `amount` and counting payments at the (period, buyer,
 * method) grain, SQL-side (R29.3, R38.1). The period key is the payment date
 * (`paid_at`), the business date a collection occurred (distinct from when it
 * was recorded).
 *
 * Catalog rules observed:
 *
 *   - SQL-SIDE AGGREGATION (R29.3, R38.1): SUM of collected amount and COUNT of
 *     payments are computed in the database; no payments are hydrated into PHP.
 *   - SELECT ONLY NEEDED COLUMNS (R38.1): only the period key, buyer morph,
 *     method, and the two measures are selected.
 *   - FILTERS (R29.1): the resolved date range (R29.2) bounds payments by
 *     `paid_at`, and the buyer scope the service injects for a
 *     Distributor/Dealer principal (R28.10) maps onto the payment's polymorphic
 *     BUYER columns so a buyer sees only its own collections.
 *   - ROBUSTNESS (R29.7): the report aggregates `payments` directly with no
 *     outer relation to drop, so a missing relation cannot error it.
 *
 * The `paid_at` index backs the date-range scans this report drives (R28.6,
 * R38.4).
 */
class PaymentsReportDefinition extends AbstractReportDefinition
{
    public function type(): ReportType
    {
        return ReportType::PAYMENTS;
    }

    public function groupableColumns(): array
    {
        return ['period', 'buyer', 'method'];
    }

    public function sortableColumns(): array
    {
        return ['period', 'buyer', 'method', 'collected', 'payment_count'];
    }

    protected function groupingColumnMap(): array
    {
        return [
            'period' => 'period',
            'buyer' => 'payments.buyer_id',
            'method' => 'payments.method',
        ];
    }

    protected function sortColumnMap(): array
    {
        return [
            'period' => 'period',
            'buyer' => 'payments.buyer_id',
            'method' => 'payments.method',
            'collected' => 'collected',
            'payment_count' => 'payment_count',
        ];
    }

    public function baseQuery(ReportFilters $filters): Builder
    {
        $query = $this->table('payments')
            ->select([
                $this->raw('DATE(payments.paid_at) as period'),
                'payments.buyer_type as buyer_type',
                'payments.buyer_id as buyer_id',
                'payments.method as method',
                // SQL-side measures (R29.3, R38.1): collected amount and count.
                $this->raw('COALESCE(SUM(payments.amount), 0) as collected'),
                $this->raw('COUNT(payments.id) as payment_count'),
            ])
            // Finest grain: a row per (period, buyer, method). The service
            // layers coarser caller-chosen roll-ups via applyGrouping().
            ->groupBy(
                $this->raw('DATE(payments.paid_at)'),
                'payments.buyer_type',
                'payments.buyer_id',
                'payments.method',
            );

        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * Apply the report's honored filters (R29.1): the buyer scope the service
     * injects for a buyer principal (R28.10) and the resolved date-range bounds
     * on the payment date.
     */
    private function applyFilters(Builder $query, ReportFilters $filters): void
    {
        if ($filters->buyerType !== null && $filters->buyerId !== null) {
            $query->where('payments.buyer_type', $filters->buyerType)
                ->where('payments.buyer_id', $filters->buyerId);
        }

        if ($filters->from !== null) {
            $query->where('payments.paid_at', '>=', $filters->from->toDateString());
        }

        if ($filters->to !== null) {
            $query->where('payments.paid_at', '<=', $filters->to->toDateString());
        }
    }

    /**
     * A raw SQL expression on the active tenant connection's query grammar.
     */
    private function raw(string $expression): Expression
    {
        return DB::connection(ReportQueryService::CONNECTION)->raw($expression);
    }
}
