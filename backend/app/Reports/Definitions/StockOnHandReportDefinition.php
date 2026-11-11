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
 * Stock-on-hand report: current on-hand quantity per owner and stock point
 * (R28.1).
 *
 * This is the first concrete report plugged into the shared
 * {@see ReportQueryService}. It demonstrates every rule the
 * engine enforces end-to-end:
 *
 *   - INDEXED, SQL-SIDE AGGREGATION (R29.3, R38.1): sums `stock.quantity`
 *     grouped by owner + stock point + product directly in SQL, so no rows are
 *     hydrated to aggregate in PHP. The grouping columns are the leading columns
 *     of the stock table's composite index.
 *   - SELECT ONLY NEEDED COLUMNS (R38.1): the SELECT lists exactly the
 *     identifier, label, and summed-quantity columns the report renders —
 *     nothing more.
 *   - LEFT JOIN + NULL-COALESCE (R29.7): the related `products` and
 *     `stock_points` rows are LEFT JOINed and their display columns wrapped in
 *     COALESCE, so a stock row whose product or stock point is missing (e.g.
 *     hard-deleted) still appears with a null/placeholder label instead of
 *     being dropped or raising an error.
 *   - FILTERS (R29.1): product and stock-point filters are honored; the date
 *     range (always resolved by the service, R29.2) bounds the snapshot to rows
 *     created on/before the window end so the report reflects stock as of the
 *     reporting period.
 *
 * The buyer scope the service injects for a Distributor/Dealer principal
 * (R28.10) maps onto the polymorphic stock OWNER columns, confining the report
 * to that owner's stock.
 */
class StockOnHandReportDefinition extends AbstractReportDefinition
{
    public function type(): ReportType
    {
        return ReportType::STOCK_ON_HAND;
    }

    /**
     * {@inheritDoc}
     *
     * Callers may further roll the snapshot up by owner, stock point, or
     * product. The base query already groups at the finest (owner, point,
     * product) grain; these keys let a caller request a coarser roll-up.
     */
    public function groupableColumns(): array
    {
        return ['owner', 'stock_point', 'product'];
    }

    public function sortableColumns(): array
    {
        return ['stock_point', 'product', 'quantity'];
    }

    protected function groupingColumnMap(): array
    {
        return [
            'owner' => 'stock.stockable_type',
            'stock_point' => 'stock.stock_point_id',
            'product' => 'stock.product_id',
        ];
    }

    protected function sortColumnMap(): array
    {
        return [
            'stock_point' => 'stock_point_name',
            'product' => 'product_sku',
            'quantity' => 'quantity_on_hand',
        ];
    }

    public function baseQuery(ReportFilters $filters): Builder
    {
        $query = $this->table('stock')
            // LEFT JOIN the related product and stock point so a stock row whose
            // relation is missing/removed is still reported (R29.7).
            ->leftJoin('products', 'products.id', '=', 'stock.product_id')
            ->leftJoin('stock_points', 'stock_points.id', '=', 'stock.stock_point_id')
            ->select([
                'stock.stockable_type as owner_type',
                'stock.stockable_id as owner_id',
                'stock.stock_point_id as stock_point_id',
                'stock.product_id as product_id',
                // NULL-COALESCE related labels so a missing relation yields a
                // null/placeholder rather than dropping the row (R29.7).
                $this->raw('COALESCE(products.sku, NULL) as product_sku'),
                $this->raw('COALESCE(products.name, NULL) as product_name'),
                $this->raw('COALESCE(stock_points.name, NULL) as stock_point_name'),
                // SQL-side aggregation of on-hand quantity (R29.3, R38.1).
                $this->raw('COALESCE(SUM(stock.quantity), 0) as quantity_on_hand'),
            ])
            // Finest grain: a row per (owner, stock point, product). Coarser
            // roll-ups are added by the service via applyGrouping().
            ->groupBy(
                'stock.stockable_type',
                'stock.stockable_id',
                'stock.stock_point_id',
                'stock.product_id',
                'products.sku',
                'products.name',
                'stock_points.name',
            );

        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * Apply the report's honored filters (R29.1): the owner (buyer) scope the
     * service injects for a buyer principal, an explicit product/stock-point
     * filter, and the window-end bound from the resolved date range.
     */
    private function applyFilters(Builder $query, ReportFilters $filters): void
    {
        if ($filters->buyerType !== null && $filters->buyerId !== null) {
            $query->where('stock.stockable_type', $filters->buyerType)
                ->where('stock.stockable_id', $filters->buyerId);
        }

        if ($filters->productId !== null) {
            $query->where('stock.product_id', $filters->productId);
        }

        if ($filters->stockPointId !== null) {
            $query->where('stock.stock_point_id', $filters->stockPointId);
        }

        // Stock-on-hand is a point-in-time snapshot: bound it to rows that
        // existed by the end of the reporting window (R29.2 resolves the
        // window). `created_at` on the stock row is indexed implicitly via the
        // table's keys; the bound keeps the snapshot consistent with the period.
        if ($filters->to !== null) {
            $query->where('stock.created_at', '<=', $filters->to);
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
