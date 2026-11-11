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
 * Sales report: sold value and quantity over confirmed orders, grouped by
 * period, Distributor, Dealer, Product, and Category (R28.3).
 *
 * A sale is realised when an order is confirmed, so the report aggregates the
 * {@see \App\Models\OrderLine} rows of orders whose status is at or beyond
 * `confirmed` (a cancelled or still-draft/placed order is not a sale). Each
 * line's sold value is `quantity * (unit_price - discount)` — the resolved
 * amount actually charged (R12.4, R12.5) — summed SQL-side (R29.3, R38.1).
 *
 * The report demonstrates the catalog rules end-to-end:
 *
 *   - SQL-SIDE AGGREGATION (R29.3, R38.1): SUM of quantity and of line value is
 *     computed in the database at the finest grain and rolled up by the
 *     caller-chosen grouping; no order lines are hydrated into PHP.
 *   - SELECT ONLY NEEDED COLUMNS (R38.1): only the grouping identifiers/labels
 *     and the two summed measures are selected.
 *   - LEFT JOIN + NULL-COALESCE (R29.7): `products`, `collections`, and
 *     `categories` are LEFT JOINed with COALESCEd labels, so a line whose
 *     product or category was later removed still contributes its value under a
 *     null label rather than being dropped.
 *   - FILTERS (R29.1): the date range (resolved by the service, R29.2) bounds
 *     the orders by `confirmed`/created time; product and category filters
 *     narrow the lines; and the buyer scope the service injects for a
 *     Distributor/Dealer principal (R28.10) maps onto the order's polymorphic
 *     BUYER columns, confining the report to that buyer's purchases.
 *
 * Grouping keys cover the five dimensions R28.3 requires: `period`
 * (the sale date), `distributor`/`dealer`/`buyer` (the ordering party),
 * `product`, and `category`.
 */
class SalesReportDefinition extends AbstractReportDefinition
{
    /**
     * Order statuses that count as a realised sale. A sale exists once an order
     * is confirmed and remains counted through fulfilment; draft/placed orders
     * are not yet sales and cancelled orders are reversed out.
     *
     * @var list<string>
     */
    private const SALE_STATUSES = ['confirmed', 'dispatched', 'delivered', 'closed'];

    public function type(): ReportType
    {
        return ReportType::SALES;
    }

    public function groupableColumns(): array
    {
        return ['period', 'buyer', 'distributor', 'dealer', 'product', 'category'];
    }

    public function sortableColumns(): array
    {
        return ['period', 'buyer', 'product', 'category', 'quantity', 'sales_value'];
    }

    protected function groupingColumnMap(): array
    {
        return [
            'period' => 'period',
            // Distributor/Dealer/buyer all roll up by the order's buyer morph;
            // which concrete type a row represents is carried in buyer_type.
            'buyer' => 'orders.buyer_id',
            'distributor' => 'orders.buyer_id',
            'dealer' => 'orders.buyer_id',
            'product' => 'order_lines.product_id',
            'category' => 'categories.id',
        ];
    }

    protected function sortColumnMap(): array
    {
        return [
            'period' => 'period',
            'buyer' => 'orders.buyer_id',
            'product' => 'product_sku',
            'category' => 'category_name',
            'quantity' => 'quantity_sold',
            'sales_value' => 'sales_value',
        ];
    }

    public function baseQuery(ReportFilters $filters): Builder
    {
        $connection = ReportQueryService::CONNECTION;

        $query = $this->table('order_lines')
            ->join('orders', 'orders.id', '=', 'order_lines.order_id')
            // LEFT JOIN the catalog hierarchy so a line whose product/category
            // was later removed still contributes under a null label (R29.7).
            ->leftJoin('products', 'products.id', '=', 'order_lines.product_id')
            ->leftJoin('collections', 'collections.id', '=', 'products.collection_id')
            ->leftJoin('categories', 'categories.id', '=', 'collections.category_id')
            ->select([
                // A sale-date period key (YYYY-MM-DD) grouping by day; coarser
                // period roll-ups remain a straightforward extension.
                $this->raw("DATE(orders.created_at) as period"),
                'orders.buyer_type as buyer_type',
                'orders.buyer_id as buyer_id',
                'order_lines.product_id as product_id',
                $this->raw('COALESCE(products.sku, NULL) as product_sku'),
                $this->raw('COALESCE(products.name, NULL) as product_name'),
                $this->raw('COALESCE(categories.id, NULL) as category_id'),
                $this->raw('COALESCE(categories.name, NULL) as category_name'),
                // SQL-side measures (R29.3, R38.1): total quantity sold and the
                // sold value = qty * (unit_price - discount).
                $this->raw('COALESCE(SUM(order_lines.quantity), 0) as quantity_sold'),
                $this->raw('COALESCE(SUM(order_lines.quantity * (order_lines.unit_price - order_lines.discount)), 0) as sales_value'),
            ])
            // Finest grain: a row per (period, buyer, product, category). The
            // service layers coarser caller-chosen roll-ups via applyGrouping().
            ->groupBy(
                $this->raw('DATE(orders.created_at)'),
                'orders.buyer_type',
                'orders.buyer_id',
                'order_lines.product_id',
                'products.sku',
                'products.name',
                'categories.id',
                'categories.name',
            );

        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * Apply the report's honored filters (R29.1): only confirmed-and-beyond
     * orders count as sales; the buyer scope the service injects for a buyer
     * principal (R28.10); explicit product/category filters; and the resolved
     * date-range bounds on the order date.
     */
    private function applyFilters(Builder $query, ReportFilters $filters): void
    {
        $query->whereIn('orders.status', self::SALE_STATUSES);

        if ($filters->buyerType !== null && $filters->buyerId !== null) {
            $query->where('orders.buyer_type', $filters->buyerType)
                ->where('orders.buyer_id', $filters->buyerId);
        }

        if ($filters->sellerType !== null && $filters->sellerId !== null) {
            $query->where('orders.seller_type', $filters->sellerType)
                ->where('orders.seller_id', $filters->sellerId);
        }

        if ($filters->productId !== null) {
            $query->where('order_lines.product_id', $filters->productId);
        }

        if ($filters->categoryId !== null) {
            $query->where('categories.id', $filters->categoryId);
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
