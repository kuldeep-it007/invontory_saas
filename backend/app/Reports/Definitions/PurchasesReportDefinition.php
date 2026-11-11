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
 * Purchases report: purchased value and order count over confirmed orders,
 * grouped by period and Buyer (R28.4).
 *
 * A purchase mirrors a sale from the BUYER's side: it aggregates the orders a
 * Buyer (Distributor or Dealer) placed and that were confirmed, summing each
 * order's `total` (the amount the buyer committed to pay) and counting the
 * orders. Aggregation is SQL-side at the (period, buyer) grain (R29.3, R38.1).
 *
 * Catalog rules observed:
 *
 *   - SQL-SIDE AGGREGATION (R29.3, R38.1): SUM of order totals and COUNT of
 *     orders are computed in the database; no orders are hydrated into PHP.
 *   - SELECT ONLY NEEDED COLUMNS (R38.1): only the period key, the buyer morph
 *     identifiers, and the two measures are selected.
 *   - FILTERS (R29.1): the resolved date range (R29.2) bounds the orders by
 *     date, and the buyer scope the service injects for a Distributor/Dealer
 *     principal (R28.10) maps onto the order's polymorphic BUYER columns so the
 *     report shows only that buyer's purchases.
 *   - ROBUSTNESS (R29.7): the report aggregates `orders` directly and does not
 *     depend on any outer relation, so there is no relation to drop; a missing
 *     catalog relation cannot error this report.
 *
 * Operating on `orders` rather than `order_lines` keeps a purchase valued at
 * the order's authoritative `total` (the figure the account ledger and credit
 * checks use), so the purchases report reconciles with the buyer's account.
 */
class PurchasesReportDefinition extends AbstractReportDefinition
{
    /**
     * Order statuses that count as a realised purchase — confirmed and beyond,
     * mirroring the sales report so the two reconcile.
     *
     * @var list<string>
     */
    private const PURCHASE_STATUSES = ['confirmed', 'dispatched', 'delivered', 'closed'];

    public function type(): ReportType
    {
        return ReportType::PURCHASES;
    }

    public function groupableColumns(): array
    {
        return ['period', 'buyer'];
    }

    public function sortableColumns(): array
    {
        return ['period', 'buyer', 'purchases_value', 'order_count'];
    }

    protected function groupingColumnMap(): array
    {
        return [
            'period' => 'period',
            'buyer' => 'orders.buyer_id',
        ];
    }

    protected function sortColumnMap(): array
    {
        return [
            'period' => 'period',
            'buyer' => 'orders.buyer_id',
            'purchases_value' => 'purchases_value',
            'order_count' => 'order_count',
        ];
    }

    public function baseQuery(ReportFilters $filters): Builder
    {
        $query = $this->table('orders')
            ->select([
                $this->raw('DATE(orders.created_at) as period'),
                'orders.buyer_type as buyer_type',
                'orders.buyer_id as buyer_id',
                // SQL-side measures (R29.3, R38.1): total purchased value and
                // the number of orders behind it.
                $this->raw('COALESCE(SUM(orders.total), 0) as purchases_value'),
                $this->raw('COUNT(orders.id) as order_count'),
            ])
            // Finest grain: a row per (period, buyer). Coarser caller-chosen
            // roll-ups are layered on by the service via applyGrouping().
            ->groupBy(
                $this->raw('DATE(orders.created_at)'),
                'orders.buyer_type',
                'orders.buyer_id',
            );

        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * Apply the report's honored filters (R29.1): only confirmed-and-beyond
     * orders count as purchases; the buyer scope the service injects for a
     * buyer principal (R28.10); and the resolved date-range bounds.
     */
    private function applyFilters(Builder $query, ReportFilters $filters): void
    {
        $query->whereIn('orders.status', self::PURCHASE_STATUSES);

        if ($filters->buyerType !== null && $filters->buyerId !== null) {
            $query->where('orders.buyer_type', $filters->buyerType)
                ->where('orders.buyer_id', $filters->buyerId);
        }

        if ($filters->from !== null) {
            $query->where('orders.created_at', '>=', $filters->from);
        }

        if ($filters->to !== null) {
            $query->where('orders.created_at', '<=', $filters->to);
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
