<?php

declare(strict_types=1);

namespace App\Reports\Definitions;

use App\Reports\ReportFilters;
use App\Reports\ReportType;
use App\Services\ReportQueryService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plan-usage report per Tenant (R28.9): the active tenant's current count of
 * each quota-counted resource.
 *
 * Unlike the other catalog reports, plan usage is not an aggregation over one
 * business table — it is the per-resource row count of several tenant tables
 * (distributors, dealers, products, stock points, users, and the current
 * month's orders). The report builds a single UNION ALL over per-table
 * `COUNT(*)` subqueries so the whole thing is one indexed, paginated query on
 * the active tenant connection (R29.3, R38.1) — tenant scoping is physical, so
 * "per tenant" is automatic (R28.10).
 *
 * Each row is `{ resource, count }`. The plan LIMIT side of a quota lives in
 * the CENTRAL database (plans/plan_limits), which this tenant-connection query
 * deliberately does not reach into; the usage-versus-limits snapshot that joins
 * counts to central limits is served by `GET /usage`
 * ({@see \App\Services\QuotaService::usage()}). This report answers the
 * tenant-authoritative half: how many of each resource exist right now.
 *
 * ROBUSTNESS (R29.7): a counted table that does not exist yet in the current
 * milestone simply contributes a literal `0` count rather than erroring, so the
 * report always produces a complete row set.
 */
class PlanUsageReportDefinition extends AbstractReportDefinition
{
    /**
     * Map of resource key => the tenant-database table whose row count is the
     * usage for that resource (R28.9). `orders` is handled specially (counted
     * for the current calendar month) and is not a straightforward whole-table
     * count, so it is appended separately in {@see baseQuery()}.
     *
     * @var array<string, string>
     */
    private const COUNTED_TABLES = [
        'distributors' => 'distributors',
        'dealers' => 'dealers',
        'products' => 'products',
        'stock_points' => 'stock_points',
        'users' => 'users',
    ];

    public function type(): ReportType
    {
        return ReportType::PLAN_USAGE;
    }

    /**
     * {@inheritDoc}
     *
     * The report is already one row per resource; it exposes no caller-chosen
     * grouping.
     */
    public function groupableColumns(): array
    {
        return [];
    }

    public function sortableColumns(): array
    {
        return ['resource', 'count'];
    }

    protected function sortColumnMap(): array
    {
        return [
            'resource' => 'resource',
            'count' => 'count',
        ];
    }

    public function baseQuery(ReportFilters $filters): Builder
    {
        $connection = ReportQueryService::CONNECTION;

        /** @var Builder|null $union */
        $union = null;

        foreach (self::COUNTED_TABLES as $resource => $table) {
            $leg = $this->countLeg($connection, $resource, $table);
            $union = $union === null ? $leg : $union->unionAll($leg);
        }

        // Monthly orders: counted for the current calendar month (mirrors the
        // quota accounting), still as a single-row count leg.
        $monthlyOrders = $this->monthlyOrdersLeg($connection);
        $union = $union === null ? $monthlyOrders : $union->unionAll($monthlyOrders);

        // Wrap the UNION ALL in a derived table so the engine can apply sort and
        // pagination over the resource/count rows uniformly (R29.3).
        return DB::connection($connection)
            ->query()
            ->fromSub($union, 'plan_usage');
    }

    /**
     * A single-row count leg: `SELECT '<resource>' AS resource, <count> AS count`.
     * When the backing table does not exist yet, the count is a literal 0 so the
     * report stays complete and errors cannot occur (R29.7).
     */
    private function countLeg(string $connection, string $resource, string $table): Builder
    {
        if (! Schema::connection($connection)->hasTable($table)) {
            return DB::connection($connection)
                ->query()
                ->selectRaw('? as resource, 0 as count', [$resource]);
        }

        $countSub = DB::connection($connection)->table($table)->selectRaw('COUNT(*)');

        return DB::connection($connection)
            ->query()
            ->selectRaw('? as resource, ('.$countSub->toSql().') as count', [$resource]);
    }

    /**
     * The current-calendar-month order count leg (R5.3-style monthly usage),
     * or a literal 0 when the `orders` table does not exist yet (R29.7).
     */
    private function monthlyOrdersLeg(string $connection): Builder
    {
        if (! Schema::connection($connection)->hasTable('orders')) {
            return DB::connection($connection)
                ->query()
                ->selectRaw('? as resource, 0 as count', ['orders_this_month']);
        }

        $countSub = DB::connection($connection)
            ->table('orders')
            ->selectRaw('COUNT(*)')
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);

        return DB::connection($connection)
            ->query()
            ->selectRaw(
                '? as resource, ('.$countSub->toSql().') as count',
                array_merge(['orders_this_month'], $countSub->getBindings()),
            );
    }
}
