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
 * Stock-ledger movement report: the immutable, append-only record of every
 * stock movement, listed in time order (R28.2).
 *
 * Unlike the aggregating reports in this catalog, the stock ledger is a
 * LISTING — one report row per `stock_ledger` entry — so there is no SQL
 * aggregation to perform beyond selecting only the columns a movement renders
 * (R38.1). It still follows every engine rule the catalog shares:
 *
 *   - SELECT ONLY NEEDED COLUMNS (R38.1): the SELECT lists exactly the owner,
 *     product, stock point, signed quantity, balance-after, movement metadata,
 *     and timestamp a movement row renders — nothing more.
 *   - LEFT JOIN + NULL-COALESCE (R29.7): the related `products` and
 *     `stock_points` rows are LEFT JOINed and their display columns wrapped in
 *     COALESCE, so a ledger entry whose product or stock point was later
 *     hard-deleted still appears with a null label rather than being dropped.
 *   - FILTERS (R29.1): the date range (always resolved by the service, R29.2)
 *     bounds the listing to movements recorded within the reporting window; the
 *     product and stock-point filters further narrow it; and the buyer scope
 *     the service injects for a Distributor/Dealer principal (R28.10) maps onto
 *     the polymorphic ledger OWNER columns, confining the report to that
 *     owner's movements.
 *   - INDEXED (R29.3, R38.4): the owner + product + `created_at` composite
 *     index and the `created_at` index back the per-owner, time-ordered scans
 *     this report drives.
 */
class StockLedgerReportDefinition extends AbstractReportDefinition
{
    public function type(): ReportType
    {
        return ReportType::STOCK_LEDGER;
    }

    /**
     * {@inheritDoc}
     *
     * The ledger is a flat listing, so it exposes no caller-chosen grouping —
     * rolling movements up would hide the individual entries the report exists
     * to show.
     */
    public function groupableColumns(): array
    {
        return [];
    }

    public function sortableColumns(): array
    {
        return ['created_at', 'product', 'movement_type', 'quantity'];
    }

    protected function sortColumnMap(): array
    {
        return [
            'created_at' => 'stock_ledger.created_at',
            'product' => 'product_sku',
            'movement_type' => 'stock_ledger.movement_type',
            'quantity' => 'stock_ledger.quantity',
        ];
    }

    public function baseQuery(ReportFilters $filters): Builder
    {
        $query = $this->table('stock_ledger')
            // LEFT JOIN the related product and stock point so a movement whose
            // relation was later removed is still listed (R29.7).
            ->leftJoin('products', 'products.id', '=', 'stock_ledger.product_id')
            ->leftJoin('stock_points', 'stock_points.id', '=', 'stock_ledger.stock_point_id')
            ->select([
                'stock_ledger.id as id',
                'stock_ledger.stockable_type as owner_type',
                'stock_ledger.stockable_id as owner_id',
                'stock_ledger.product_id as product_id',
                'stock_ledger.stock_point_id as stock_point_id',
                // NULL-COALESCE related labels so a missing relation yields a
                // null label rather than dropping the row (R29.7).
                $this->raw('COALESCE(products.sku, NULL) as product_sku'),
                $this->raw('COALESCE(products.name, NULL) as product_name'),
                $this->raw('COALESCE(stock_points.name, NULL) as stock_point_name'),
                'stock_ledger.movement_type as movement_type',
                'stock_ledger.quantity as quantity',
                'stock_ledger.balance_after as balance_after',
                'stock_ledger.reason as reason',
                'stock_ledger.reference as reference',
                'stock_ledger.actor_id as actor_id',
                'stock_ledger.created_at as created_at',
            ]);

        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * Apply the report's honored filters (R29.1): the owner (buyer) scope the
     * service injects for a buyer principal, an explicit product/stock-point
     * filter, and the resolved date-range bounds on the movement timestamp.
     */
    private function applyFilters(Builder $query, ReportFilters $filters): void
    {
        if ($filters->buyerType !== null && $filters->buyerId !== null) {
            $query->where('stock_ledger.stockable_type', $filters->buyerType)
                ->where('stock_ledger.stockable_id', $filters->buyerId);
        }

        if ($filters->productId !== null) {
            $query->where('stock_ledger.product_id', $filters->productId);
        }

        if ($filters->stockPointId !== null) {
            $query->where('stock_ledger.stock_point_id', $filters->stockPointId);
        }

        if ($filters->from !== null) {
            $query->where('stock_ledger.created_at', '>=', $filters->from);
        }

        if ($filters->to !== null) {
            $query->where('stock_ledger.created_at', '<=', $filters->to);
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
