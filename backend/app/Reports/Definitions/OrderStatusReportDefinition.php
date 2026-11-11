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
 * Order-status and delivery-challan listing report (R28.7).
 *
 * A LISTING — one report row per Order — of each order's current lifecycle
 * status, buyer, seller, type, total, and (when one exists) its invoice
 * number, which doubles as the delivery-challan reference for dispatched/
 * delivered orders. There is no aggregation beyond selecting only the columns a
 * listing row renders (R38.1); the `status` filter and date range narrow which
 * orders appear.
 *
 * Catalog rules observed:
 *
 *   - SELECT ONLY NEEDED COLUMNS (R38.1): the SELECT lists exactly the order
 *     identity, buyer/seller morph, status, type, total, invoice reference, and
 *     timestamp a listing row needs.
 *   - LEFT JOIN + NULL-COALESCE (R29.7): the `invoices` row is LEFT JOINed and
 *     its series/number COALESCEd, so an order with no invoice yet (not confirmed)
 *     still lists with a null challan reference rather than being dropped.
 *   - FILTERS (R29.1): the `status` filter selects a specific lifecycle state
 *     (e.g. only `dispatched` for a delivery-challan listing); the resolved
 *     date range (R29.2) bounds the orders; and the buyer scope the service
 *     injects for a Distributor/Dealer principal (R28.10) maps onto the order's
 *     polymorphic BUYER columns so a buyer sees only its own orders.
 *   - INDEXED (R29.3, R38.4): the `status` index and the buyer/seller morph
 *     indexes back the filtered listings this report drives.
 */
class OrderStatusReportDefinition extends AbstractReportDefinition
{
    public function type(): ReportType
    {
        return ReportType::ORDER_STATUS;
    }

    /**
     * {@inheritDoc}
     *
     * A listing exposes no caller-chosen grouping — rolling orders up would
     * hide the individual rows the report exists to list.
     */
    public function groupableColumns(): array
    {
        return [];
    }

    public function sortableColumns(): array
    {
        return ['created_at', 'status', 'total', 'invoice_number'];
    }

    protected function sortColumnMap(): array
    {
        return [
            'created_at' => 'orders.created_at',
            'status' => 'orders.status',
            'total' => 'orders.total',
            'invoice_number' => 'invoice_number',
        ];
    }

    public function baseQuery(ReportFilters $filters): Builder
    {
        $query = $this->table('orders')
            // LEFT JOIN the invoice so an order with no invoice yet still lists
            // with a null challan reference (R29.7).
            ->leftJoin('invoices', 'invoices.order_id', '=', 'orders.id')
            ->select([
                'orders.id as id',
                'orders.buyer_type as buyer_type',
                'orders.buyer_id as buyer_id',
                'orders.seller_type as seller_type',
                'orders.seller_id as seller_id',
                'orders.status as status',
                'orders.order_type as order_type',
                'orders.total as total',
                // NULL-COALESCE the invoice/challan reference so an un-invoiced
                // order reports a null reference rather than dropping (R29.7).
                $this->raw('COALESCE(invoices.series, NULL) as invoice_series'),
                $this->raw('COALESCE(invoices.number, NULL) as invoice_number'),
                'orders.created_at as created_at',
            ]);

        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * Apply the report's honored filters (R29.1): the `status` filter (used for
     * delivery-challan listings scoped to dispatched/delivered orders), the
     * buyer scope the service injects for a buyer principal (R28.10), and the
     * resolved date-range bounds on the order date.
     */
    private function applyFilters(Builder $query, ReportFilters $filters): void
    {
        if ($filters->status !== null) {
            $query->where('orders.status', $filters->status);
        }

        if ($filters->buyerType !== null && $filters->buyerId !== null) {
            $query->where('orders.buyer_type', $filters->buyerType)
                ->where('orders.buyer_id', $filters->buyerId);
        }

        if ($filters->sellerType !== null && $filters->sellerId !== null) {
            $query->where('orders.seller_type', $filters->sellerType)
                ->where('orders.seller_id', $filters->sellerId);
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
