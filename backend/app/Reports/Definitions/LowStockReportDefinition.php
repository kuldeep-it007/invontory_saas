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
 * Low-stock and threshold-breach report (R28.8).
 *
 * A LISTING of every stock row whose configured per-(product, stock_point)
 * low-stock threshold is set AND whose on-hand quantity is at or below that
 * threshold — the exact breach condition the low-stock alert uses
 * (`quantity <= low_stock_threshold`, R19.2, R19.3). A stock row with no
 * threshold configured is not a breach and does not appear.
 *
 * Catalog rules observed:
 *
 *   - SELECT ONLY NEEDED COLUMNS (R38.1): the SELECT lists exactly the owner,
 *     product, stock point, on-hand quantity, and threshold a breach row
 *     renders.
 *   - LEFT JOIN + NULL-COALESCE (R29.7): the related `products` and
 *     `stock_points` rows are LEFT JOINed with COALESCEd labels, so a breaching
 *     stock row whose product/stock point was later removed still lists with a
 *     null label rather than being dropped.
 *   - FILTERS (R29.1): an explicit product/stock-point filter narrows the
 *     listing, and the owner (buyer) scope the service injects for a
 *     Distributor/Dealer principal (R28.10) maps onto the polymorphic stock
 *     OWNER columns so a buyer sees only its own breaches. The report is a
 *     current snapshot, so the date range does not filter which rows appear.
 *   - INDEXED (R38.4): the stock composite/owner and product indexes back the
 *     breach scan.
 */
class LowStockReportDefinition extends AbstractReportDefinition
{
    public function type(): ReportType
    {
        return ReportType::LOW_STOCK;
    }

    /**
     * {@inheritDoc}
     *
     * A breach listing exposes no caller-chosen grouping — every breaching
     * (owner, product, stock point) row is shown individually.
     */
    public function groupableColumns(): array
    {
        return [];
    }

    public function sortableColumns(): array
    {
        return ['product', 'stock_point', 'quantity', 'threshold'];
    }

    protected function sortColumnMap(): array
    {
        return [
            'product' => 'product_sku',
            'stock_point' => 'stock_point_name',
            'quantity' => 'stock.quantity',
            'threshold' => 'stock.low_stock_threshold',
        ];
    }

    public function baseQuery(ReportFilters $filters): Builder
    {
        $query = $this->table('stock')
            // LEFT JOIN the related product and stock point so a breaching row
            // whose relation was removed is still listed (R29.7).
            ->leftJoin('products', 'products.id', '=', 'stock.product_id')
            ->leftJoin('stock_points', 'stock_points.id', '=', 'stock.stock_point_id')
            ->select([
                'stock.id as id',
                'stock.stockable_type as owner_type',
                'stock.stockable_id as owner_id',
                'stock.product_id as product_id',
                'stock.stock_point_id as stock_point_id',
                $this->raw('COALESCE(products.sku, NULL) as product_sku'),
                $this->raw('COALESCE(products.name, NULL) as product_name'),
                $this->raw('COALESCE(stock_points.name, NULL) as stock_point_name'),
                'stock.quantity as quantity',
                'stock.low_stock_threshold as threshold',
            ])
            // The breach condition: a threshold IS configured and the on-hand
            // quantity is at or below it (R19.2, R19.3).
            ->whereNotNull('stock.low_stock_threshold')
            ->whereColumn('stock.quantity', '<=', 'stock.low_stock_threshold');

        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * Apply the report's honored filters (R29.1): the owner (buyer) scope the
     * service injects for a buyer principal (R28.10) and explicit
     * product/stock-point filters. Low-stock is a current snapshot, so the date
     * range does not filter which rows appear.
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
    }

    /**
     * A raw SQL expression on the active tenant connection's query grammar.
     */
    private function raw(string $expression): Expression
    {
        return DB::connection(ReportQueryService::CONNECTION)->raw($expression);
    }
}
