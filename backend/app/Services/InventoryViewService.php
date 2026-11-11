<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OrderStatus;
use App\Reports\ReportAccessContext;
use App\Reports\ReportQuerySpec;
use App\Reports\ReportResult;
use App\Reports\ReportType;
use InvalidArgumentException;

/**
 * The inventory operational views backing `GET /inventory/views/{view}`
 * (R33.2).
 *
 * Requirement 33.2 names three views a Company_Admin monitors operations with:
 *
 *   - STOCK_ON_HAND — current on-hand quantity per owner, stock point, product.
 *   - ITEM_DETAIL   — the per-product breakdown (every stock row for one
 *     product across its stock points) plus its on-hand total.
 *   - PENDING_ORDERS — Orders placed but NOT yet confirmed or fulfilled, i.e.
 *     orders still in the Placed state awaiting the seller's action.
 *
 * The two STOCK views are READS of the exact same aggregate the standard
 * stock-on-hand report produces, so they are driven through the single shared
 * {@see ReportQueryService} ({@see ReportType::STOCK_ON_HAND}) rather than a
 * second, divergent query path — a view and the report can never disagree, and
 * the view inherits the engine's tenant+role scoping (R28.10), indexed SQL-side
 * aggregation, and pagination (R29.3) for free. ITEM_DETAIL is simply the
 * stock-on-hand aggregate constrained to one product (the engine's `productId`
 * filter).
 *
 * PENDING_ORDERS is an order LISTING, so it maps onto the
 * {@see ReportType::ORDER_STATUS} report constrained to `status = placed` — the
 * precise "placed but not yet confirmed or fulfilled" set (R33.2) — again reusing
 * the one query layer rather than re-querying orders here.
 *
 * Centralising the three views on the report engine keeps this service thin: it
 * resolves the requested view name to a validated {@see ReportQuerySpec} and
 * delegates execution. The whole view surface is therefore physically confined
 * to the active tenant database (R28.10) and paginated like every other list
 * endpoint.
 */
class InventoryViewService
{
    /** Stock-on-hand view: current quantity per owner/stock point/product (R33.2). */
    public const VIEW_STOCK_ON_HAND = 'stock-on-hand';

    /** Item-detail view: one product's stock across its stock points (R33.2). */
    public const VIEW_ITEM_DETAIL = 'item-detail';

    /** Pending-orders view: placed-but-not-confirmed/fulfilled orders (R33.2). */
    public const VIEW_PENDING_ORDERS = 'pending-orders';

    public function __construct(private readonly ReportQueryService $reports) {}

    /**
     * The allow-listed view names this service serves (R33.2). The controller
     * rejects anything outside this set with a 404 before any query runs.
     *
     * @return list<string>
     */
    public static function views(): array
    {
        return [
            self::VIEW_STOCK_ON_HAND,
            self::VIEW_ITEM_DETAIL,
            self::VIEW_PENDING_ORDERS,
        ];
    }

    /**
     * Whether `$view` is an allow-listed inventory view name (R33.2).
     */
    public static function supports(string $view): bool
    {
        return in_array($view, self::views(), true);
    }

    /**
     * Resolve a view name + request filters to the {@see ReportQuerySpec} that
     * backs it (R33.2). Exposed so the export pipeline can render the SAME view
     * data a read returns (R33.3) without duplicating the mapping.
     *
     * @param  array<string, mixed>  $filters  Request filters (date range, stock point, product, …).
     *
     * @throws InvalidArgumentException when the view name is not allow-listed.
     */
    public function specFor(string $view, array $filters = [], ?int $limit = null, ?int $page = null): ReportQuerySpec
    {
        return match ($view) {
            self::VIEW_STOCK_ON_HAND => $this->stockSpec($filters, $limit, $page),
            self::VIEW_ITEM_DETAIL => $this->itemDetailSpec($filters, $limit, $page),
            self::VIEW_PENDING_ORDERS => $this->pendingOrdersSpec($filters, $limit, $page),
            default => throw new InvalidArgumentException(sprintf('Unknown inventory view [%s].', $view)),
        };
    }

    /**
     * Run an inventory view and return a paginated page (R33.2). Delegates to
     * the shared {@see ReportQueryService} so the view inherits the previous-
     * month default (R29.2), tenant+role scoping (R28.10), allow-list
     * validation (R31.2), and SQL-side pagination (R29.3).
     *
     * @param  array<string, mixed>  $filters
     *
     * @throws InvalidArgumentException when the view name is not allow-listed.
     * @throws \Illuminate\Auth\Access\AuthorizationException when the principal may not read the data (R28.10).
     */
    public function run(string $view, ReportAccessContext $context, array $filters = [], ?int $limit = null, ?int $page = null): ReportResult
    {
        $spec = $this->specFor($view, $filters, $limit, $page);

        return $this->reports->run($spec, $context);
    }

    /**
     * The stock-on-hand view spec: the stock-on-hand report with the request's
     * honoured filters (stock point, product) and pagination (R33.2).
     *
     * @param  array<string, mixed>  $filters
     */
    private function stockSpec(array $filters, ?int $limit, ?int $page): ReportQuerySpec
    {
        return ReportQuerySpec::fromArray([
            'type' => ReportType::STOCK_ON_HAND->value,
            'filters' => $this->snapshotWindow($filters),
            'limit' => $limit,
            'page' => $page,
        ]);
    }

    /**
     * The item-detail view spec (R33.2): the stock-on-hand aggregate constrained
     * to a SINGLE product, so the view shows that one item's stock across its
     * stock points. A product id is required — item-detail is meaningless
     * without it.
     *
     * @param  array<string, mixed>  $filters
     *
     * @throws InvalidArgumentException when no product id is supplied.
     */
    private function itemDetailSpec(array $filters, ?int $limit, ?int $page): ReportQuerySpec
    {
        $productId = $filters['productId'] ?? $filters['product_id'] ?? null;

        if ($productId === null || $productId === '') {
            throw new InvalidArgumentException('The item-detail view requires a product_id.');
        }

        $filters['productId'] = (int) $productId;

        return ReportQuerySpec::fromArray([
            'type' => ReportType::STOCK_ON_HAND->value,
            'filters' => $this->snapshotWindow($filters),
            'limit' => $limit,
            'page' => $page,
        ]);
    }

    /**
     * Resolve the date window for a point-in-time STOCK view (R33.2).
     *
     * The stock-on-hand and item-detail views are "as of now" SNAPSHOTS, not
     * period reports: they list the stock currently on hand. The shared report
     * engine defaults an OMITTED date range to the previous calendar month
     * (R29.2) — correct for sales/payments, but wrong for a snapshot, because it
     * would bound the view to stock that existed by the end of LAST month and
     * silently exclude everything created since. So when the caller has not
     * supplied a COMPLETE range (both ends), default the window to end "now" and
     * start at the epoch, i.e. "all stock as of now". An explicit full range the
     * caller supplies (e.g. a historical snapshot) is left untouched.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function snapshotWindow(array $filters): array
    {
        $hasFrom = (isset($filters['from']) && $filters['from'] !== '')
            || (isset($filters['dateFrom']) && $filters['dateFrom'] !== '');
        $hasTo = (isset($filters['to']) && $filters['to'] !== '')
            || (isset($filters['dateTo']) && $filters['dateTo'] !== '');

        // A complete, caller-supplied range is honoured as-is (e.g. a historical
        // "as of" snapshot). Only fill in the default when the range is partial
        // or absent, so the previous-month default never narrows the snapshot.
        if ($hasFrom && $hasTo) {
            return $filters;
        }

        $filters['from'] = $filters['from'] ?? $filters['dateFrom'] ?? '1970-01-01 00:00:00';
        $filters['to'] = $filters['to'] ?? $filters['dateTo'] ?? now()->endOfDay()->toDateTimeString();

        return $filters;
    }

    /**
     * The pending-orders view spec (R33.2): the order-status listing constrained
     * to `status = placed` — exactly the orders placed but NOT yet confirmed or
     * fulfilled. The request's `status` filter is overridden so the view's
     * contract (only pending orders) cannot be widened by a caller.
     *
     * @param  array<string, mixed>  $filters
     */
    private function pendingOrdersSpec(array $filters, ?int $limit, ?int $page): ReportQuerySpec
    {
        // Force the pending contract: placed but not confirmed/fulfilled (R33.2).
        $filters['status'] = OrderStatus::PLACED;

        return ReportQuerySpec::fromArray([
            'type' => ReportType::ORDER_STATUS->value,
            'filters' => $filters,
            'limit' => $limit,
            'page' => $page,
        ]);
    }
}
