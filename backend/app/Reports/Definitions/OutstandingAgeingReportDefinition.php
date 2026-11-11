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
 * Outstanding-and-ageing report: each Buyer's unpaid invoice balance split
 * into the ageing buckets current, 1-30, 31-60, 61-90, and 90-plus days
 * (R28.5).
 *
 * The report values each invoice's OUTSTANDING amount — the invoice `amount`
 * less everything allocated to it from payments
 * (`amount - COALESCE(SUM(payment_allocations.amount), 0)`) — and ages it by
 * the number of days between the invoice date and the reporting window's end
 * (the resolved `to`, which defaults to the end of the previous calendar month
 * — R29.2). Each invoice's outstanding falls into exactly ONE bucket by its
 * age, and the buckets are summed SQL-side per Buyer (R29.3, R38.1):
 *
 *   - current   : age <= 0 days (not yet due relative to the window end)
 *   - 1-30      : 1..30 days
 *   - 31-60     : 31..60 days
 *   - 61-90     : 61..90 days
 *   - 90-plus   : > 90 days
 *
 * Because every invoice contributes to exactly one bucket, the five bucket
 * sums plus the `total_outstanding` reconcile: total == sum of the five buckets
 * (Property 29). A fully-paid invoice contributes zero to every bucket.
 *
 * Catalog rules observed:
 *
 *   - SQL-SIDE AGGREGATION (R29.3, R38.1): the per-invoice outstanding and the
 *     bucketed sums are computed in the database via conditional SUMs over the
 *     datediff; nothing is aged in PHP.
 *   - LEFT JOIN + NULL-COALESCE (R29.7): `payment_allocations` is LEFT JOINed
 *     (an invoice with no payments simply has zero allocated), and the owning
 *     `orders` row is LEFT JOINed so an invoice whose order was removed still
 *     reports under a null buyer rather than being dropped.
 *   - FILTERS (R29.1): the buyer scope the service injects for a
 *     Distributor/Dealer principal (R28.10) maps onto the order's polymorphic
 *     BUYER columns, and the resolved window end drives the ageing reference.
 */
class OutstandingAgeingReportDefinition extends AbstractReportDefinition
{
    public function type(): ReportType
    {
        return ReportType::OUTSTANDING_AGEING;
    }

    /**
     * {@inheritDoc}
     *
     * The report is already grouped per Buyer; `buyer` lets a caller request
     * that same (and only that) roll-up explicitly.
     */
    public function groupableColumns(): array
    {
        return ['buyer'];
    }

    public function sortableColumns(): array
    {
        return ['buyer', 'total_outstanding', 'bucket_90_plus'];
    }

    protected function groupingColumnMap(): array
    {
        return [
            'buyer' => 'orders.buyer_id',
        ];
    }

    protected function sortColumnMap(): array
    {
        return [
            'buyer' => 'orders.buyer_id',
            'total_outstanding' => 'total_outstanding',
            'bucket_90_plus' => 'bucket_90_plus',
        ];
    }

    public function baseQuery(ReportFilters $filters): Builder
    {
        // The ageing reference: invoices are aged relative to the reporting
        // window's end (R29.2 guarantees it is set by the time this runs).
        $asOf = $filters->to;
        $asOfSql = $asOf !== null
            ? "'".$asOf->format('Y-m-d H:i:s')."'"
            : 'NOW()';

        // Age in whole days between the invoice date and the as-of date.
        $age = "DATEDIFF({$asOfSql}, invoices.created_at)";

        // Per-invoice outstanding = amount - allocated. Grouping is per invoice
        // first (so each invoice's allocations sum once) then rolled to buyer in
        // the outer aggregation; here we compute the outstanding inline and bind
        // the allocation sum via a correlated LEFT JOIN aggregate.
        $outstanding = 'GREATEST(invoices.amount - COALESCE(alloc.allocated, 0), 0)';

        $allocations = DB::connection(ReportQueryService::CONNECTION)
            ->table('payment_allocations')
            ->select('invoice_id', $this->raw('SUM(amount) as allocated'))
            ->groupBy('invoice_id');

        $query = $this->table('invoices')
            // Each invoice's total allocated across all payments (R24.3). LEFT
            // JOIN so an unpaid invoice contributes zero allocated (R29.7).
            ->leftJoinSub($allocations, 'alloc', 'alloc.invoice_id', '=', 'invoices.id')
            // The owning order carries the Buyer. LEFT JOIN so an invoice whose
            // order was removed still reports (R29.7).
            ->leftJoin('orders', 'orders.id', '=', 'invoices.order_id')
            ->select([
                'orders.buyer_type as buyer_type',
                'orders.buyer_id as buyer_id',
                // Conditional bucket sums over the per-invoice outstanding, aged
                // by days to the window end — each invoice lands in one bucket.
                $this->raw("COALESCE(SUM(CASE WHEN {$age} <= 0 THEN {$outstanding} ELSE 0 END), 0) as bucket_current"),
                $this->raw("COALESCE(SUM(CASE WHEN {$age} BETWEEN 1 AND 30 THEN {$outstanding} ELSE 0 END), 0) as bucket_1_30"),
                $this->raw("COALESCE(SUM(CASE WHEN {$age} BETWEEN 31 AND 60 THEN {$outstanding} ELSE 0 END), 0) as bucket_31_60"),
                $this->raw("COALESCE(SUM(CASE WHEN {$age} BETWEEN 61 AND 90 THEN {$outstanding} ELSE 0 END), 0) as bucket_61_90"),
                $this->raw("COALESCE(SUM(CASE WHEN {$age} > 90 THEN {$outstanding} ELSE 0 END), 0) as bucket_90_plus"),
                $this->raw("COALESCE(SUM({$outstanding}), 0) as total_outstanding"),
            ])
            // Finest grain: a row per Buyer. applyGrouping() may re-assert the
            // same buyer roll-up when a caller requests it explicitly.
            ->groupBy('orders.buyer_type', 'orders.buyer_id');

        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * Apply the report's honored filters (R29.1): the buyer scope the service
     * injects for a buyer principal (R28.10). The ageing report is a snapshot
     * as of the window end, so the date range sets the ageing reference rather
     * than filtering which invoices appear.
     */
    private function applyFilters(Builder $query, ReportFilters $filters): void
    {
        if ($filters->buyerType !== null && $filters->buyerId !== null) {
            $query->where('orders.buyer_type', $filters->buyerType)
                ->where('orders.buyer_id', $filters->buyerId);
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
