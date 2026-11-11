<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Distributor;
use App\Models\OrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Company master dashboard metrics (R33.1).
 *
 * The dashboard is a single, read-only snapshot of the tenant's key operational
 * metrics for the Company_Admin landing page (R33.1). It is NOT a report — it
 * is a small set of headline counts/totals a human scans "at a glance" — so it
 * is composed here from the active tenant database rather than driven through
 * the paginated {@see ReportQueryService} catalog (which exists to list and
 * export large, filterable, grouped result sets — R28/R29). Where a figure is
 * already owned by another service it is REUSED rather than recomputed:
 *
 *   - the plan usage block comes straight from {@see QuotaService::usage()}
 *     (R5.5), so the dashboard and the usage widget can never disagree;
 *   - the low-stock headline reuses {@see LowStockService} semantics (quantity
 *     at or below a configured threshold — R19.2) for the breach count.
 *
 * Every query runs on the active tenant connection (physical isolation,
 * R28.10) and is milestone-safe: a metric whose backing table does not yet
 * exist contributes 0 rather than erroring, mirroring {@see QuotaService}.
 */
class DashboardService
{
    /** The Laravel connection name for the active tenant database (R28.10). */
    public const CONNECTION = 'tenant';

    public function __construct(private readonly QuotaService $quota) {}

    /**
     * Build the company master dashboard snapshot (R33.1).
     *
     * Returns a coherent set of headline metrics the Company_Admin dashboard
     * renders: order counts by lifecycle state (including the "pending" bucket
     * surfaced by the pending-orders view — R33.2), today's/period sales and
     * collection figures, outstanding balance, inventory headlines (distinct
     * products, total on-hand units, low-stock breaches), and the plan
     * usage-vs-limits block reused verbatim from {@see QuotaService} (R5.5).
     *
     * @return array{
     *     orders: array<string, int>,
     *     sales: array{confirmed_orders: int, confirmed_value: float},
     *     payments: array{collected: float},
     *     outstanding: array{total: float},
     *     inventory: array{products: int, units_on_hand: float, low_stock: int},
     *     usage: array<string, mixed>
     * }
     */
    public function snapshot(?array $buyer = null): array
    {
        return [
            'scope' => match (true) {
                $buyer === null => 'tenant',
                $buyer[1] === null => 'company',
                default => 'party',
            },
            'orders' => $this->orderCountsByStatus($buyer),
            'sales' => $this->salesHeadline($buyer),
            'payments' => $this->paymentsHeadline($buyer),
            'outstanding' => $this->outstandingHeadline($buyer),
            'inventory' => $this->inventoryHeadline($buyer),
            // Plan/subscription usage is tenant-level data; a Distributor or
            // Dealer never sees it.
            'usage' => ($buyer === null || $buyer[1] === null) ? $this->quota->usage() : [],
        ];
    }

    /**
     * Confine a query to a single party. `$buyer` is the `[morphClass, id]`
     * binding from {@see \App\Models\User::reportBuyerBinding()}; null leaves
     * the query tenant-wide (company-side users).
     *
     * @param  array{0: string, 1: int|null}|null  $buyer  `[class, id]` for a party, `[Company::class, null]` for the Company, null for tenant-wide.
     */
    private function forParty($query, ?array $buyer, string $kind = 'orders', string $alias = '')
    {
        if ($buyer === null) {
            return $query;
        }

        [$type, $id] = $buyer;

        // Company scope: `[Company::class, null]`. The Company's own data is
        // what it SOLD (orders against it), what its distributors owe/paid
        // (payments + ledger), and the stock it owns — never the
        // distributor→dealer tier beneath it.
        if ($id === null) {
            return match ($kind) {
                'orders' => $query->where($alias.'seller_type', $type),
                'buyer' => $query->where($alias.'buyer_type', Distributor::class),
                'stock' => $query->where($alias.'stockable_type', $type),
            };
        }

        // Party scope: a Distributor/Dealer sees only what it bought, paid,
        // owes and owns.
        return match ($kind) {
            'stock' => $query->where($alias.'stockable_type', $type)->where($alias.'stockable_id', $id),
            default => $query->where($alias.'buyer_type', $type)->where($alias.'buyer_id', $id),
        };
    }

    /**
     * Order counts keyed by lifecycle status, plus a `pending` convenience
     * bucket of orders placed but not yet confirmed or fulfilled (R33.2) and a
     * `total` across every status. Every status in {@see OrderStatus} is present
     * (0 when none), so the dashboard renders a stable shape.
     *
     * @return array<string, int>
     */
    private function orderCountsByStatus(?array $buyer): array
    {
        $counts = array_fill_keys(OrderStatus::all(), 0);
        $counts['pending'] = 0;
        $counts['total'] = 0;

        if (! $this->tableExists('orders')) {
            return $counts;
        }

        $rows = $this->forParty(DB::connection(self::CONNECTION)->table('orders'), $buyer)
            ->select('status', DB::connection(self::CONNECTION)->raw('COUNT(*) as aggregate'))
            ->groupBy('status')
            ->get();

        foreach ($rows as $row) {
            $status = (string) $row->status;
            $count = (int) $row->aggregate;
            $counts[$status] = ($counts[$status] ?? 0) + $count;
            $counts['total'] += $count;
        }

        // "Pending" = placed but not yet confirmed or fulfilled (R33.2): the
        // same definition the pending-orders inventory view lists.
        $counts['pending'] = $counts[OrderStatus::PLACED] ?? 0;

        return $counts;
    }

    /**
     * Sales headline: number of confirmed+ orders and their summed total value
     * (R33.1). A confirmed order is any order past the Placed state and not
     * Cancelled — the same orders the sales report counts.
     *
     * @return array{confirmed_orders: int, confirmed_value: float}
     */
    private function salesHeadline(?array $buyer): array
    {
        if (! $this->tableExists('orders')) {
            return ['confirmed_orders' => 0, 'confirmed_value' => 0.0];
        }

        $row = $this->forParty(DB::connection(self::CONNECTION)->table('orders'), $buyer)
            ->whereIn('status', $this->fulfilledStatuses())
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(total), 0) as value')
            ->first();

        return [
            'confirmed_orders' => (int) ($row->order_count ?? 0),
            'confirmed_value' => (float) ($row->value ?? 0),
        ];
    }

    /**
     * Payments headline: total collected across all recorded payments (R33.1).
     *
     * @return array{collected: float}
     */
    private function paymentsHeadline(?array $buyer): array
    {
        if (! $this->tableExists('payments')) {
            return ['collected' => 0.0];
        }

        $collected = $this->forParty(DB::connection(self::CONNECTION)->table('payments'), $buyer, 'buyer')
            ->sum('amount');

        return ['collected' => (float) $collected];
    }

    /**
     * Outstanding headline: the sum of each buyer's latest transaction-ledger
     * balance — the tenant-wide Outstanding_Balance (R25.2, R33.1). Falls back
     * to 0 when the ledger table does not exist yet.
     *
     * @return array{total: float}
     */
    private function outstandingHeadline(?array $buyer): array
    {
        if (! $this->tableExists('transaction_ledger')) {
            return ['total' => 0.0];
        }

        $connection = DB::connection(self::CONNECTION);

        // Each buyer's Outstanding_Balance is its LATEST ledger entry's
        // balance_after (R25.2). Sum those latest balances across all buyers for
        // the tenant-wide figure.
        $latestPerBuyer = $connection->table('transaction_ledger as tl')
            ->select('tl.balance_after')
            ->whereRaw('tl.id = (
                select max(inner_tl.id) from transaction_ledger as inner_tl
                where inner_tl.buyer_type = tl.buyer_type and inner_tl.buyer_id = tl.buyer_id
            )');
        $this->forParty($latestPerBuyer, $buyer, 'buyer', 'tl.');

        $total = $connection->query()
            ->fromSub($latestPerBuyer, 'latest')
            ->sum('balance_after');

        return ['total' => (float) $total];
    }

    /**
     * Inventory headline (R33.1): distinct products carrying stock, total units
     * on hand across every owner/stock point, and the number of (product,
     * stock point) rows at or below their configured low-stock threshold
     * (R19.2).
     *
     * @return array{products: int, units_on_hand: float, low_stock: int}
     */
    private function inventoryHeadline(?array $buyer): array
    {
        if (! $this->tableExists('stock')) {
            return ['products' => 0, 'units_on_hand' => 0.0, 'low_stock' => 0];
        }

        $connection = DB::connection(self::CONNECTION);

        // A party sees only the stock it owns (a Dealer typically holds none).
        $row = $this->forParty($connection->table('stock'), $buyer, 'stock')
            ->selectRaw('COUNT(DISTINCT product_id) as products, COALESCE(SUM(quantity), 0) as units')
            ->first();

        // Low-stock breaches: a configured threshold with quantity at or below
        // it (R19.2) — the same predicate LowStockService reports on.
        $lowStock = (int) $this->forParty($connection->table('stock'), $buyer, 'stock')
            ->whereNotNull('low_stock_threshold')
            ->whereColumn('quantity', '<=', 'low_stock_threshold')
            ->count();

        return [
            'products' => (int) ($row->products ?? 0),
            'units_on_hand' => (float) ($row->units ?? 0),
            'low_stock' => $lowStock,
        ];
    }

    /**
     * The lifecycle statuses that count as a realised sale: Confirmed and every
     * state beyond it, excluding Cancelled (R33.1).
     *
     * @return list<string>
     */
    private function fulfilledStatuses(): array
    {
        return [
            OrderStatus::CONFIRMED,
            OrderStatus::DISPATCHED,
            OrderStatus::DELIVERED,
            OrderStatus::CLOSED,
        ];
    }

    /**
     * Whether a table physically exists in the active tenant database, so a
     * metric whose backing table has not been migrated yet contributes 0 rather
     * than erroring (milestone-safe, mirroring {@see QuotaService}).
     */
    private function tableExists(string $table): bool
    {
        return Schema::connection(self::CONNECTION)->hasTable($table);
    }
}
