<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Controllers\StockController;
use App\Models\Stock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Low-stock thresholds, alerts, and reporting (R19.1, R19.2, R19.3).
 *
 * Focused single-responsibility service the thin
 * {@see StockController} delegates to. It owns the three
 * sides of the low-stock feature:
 *
 *   • CONFIGURE a per-(product, stock_point) low-stock threshold on a stock row
 *     (R19.1). The threshold lives on the `stock` row itself (one row per
 *     owner/product/stock_point), so configuring it is a scoped update of that
 *     row's `low_stock_threshold`. A null threshold clears the configuration,
 *     meaning the row can never breach.
 *   • Decide whether a given quantity BREACHES its threshold — a low-stock
 *     alert is raised exactly WHEN the on-hand quantity is at or below the
 *     configured threshold (R19.2). This is the single predicate the report,
 *     the alert flag, and callers share, so the "alert" and the "appears in the
 *     report" conditions are provably the same (design Property 14).
 *   • Produce the low-stock REPORT: every breaching row, paginated and
 *     eager-loaded, with owner/product/stock-point context for export (R19.3,
 *     R28.8).
 *
 * Runs against the active tenant database (stock is physically isolated), so
 * the report is automatically restricted to the authenticated user's tenant
 * (R28.10).
 */
class LowStockService
{
    /**
     * The low-stock report: all stock rows at or below their configured
     * threshold (R19.3, R28.8), paginated and eager-loaded so the listing never
     * N+1s and callers get the product, owner, and stock point needed to render
     * or export a row. Ordered by stock point then product for a stable,
     * consistent page order.
     *
     * The breaching set is computed SQL-side via {@see Stock::scopeBreachingLowStock()}
     * so only rows that actually breach are materialised; a row with no
     * configured threshold is excluded (R19.1).
     *
     * Visibility layer (R28.10): a company-side principal reads the whole
     * tenant's breaching rows (confined only by the active tenant database),
     * while a buyer-side principal — a Distributor — is confined to its OWN
     * stock, i.e. only the rows held at a stock point it owns. The caller
     * resolves that (ownerType, ownerId) binding and passes it here; when both
     * are set the report force-scopes to that owner via
     * {@see Stock::scopeOwnedBy()}, so a distributor can never see another
     * owner's stock.
     *
     * @return LengthAwarePaginator<int, Stock>
     */
    public function report(int $perPage = 25, ?string $ownerType = null, ?int $ownerId = null): LengthAwarePaginator
    {
        return Stock::query()
            ->breachingLowStock()
            ->when(
                $ownerType !== null && $ownerId !== null,
                static fn ($query) => $query->ownedBy((string) $ownerType, (int) $ownerId),
            )
            ->with(['product:id,sku,name', 'stockPoint:id,name,sort_order', 'owner'])
            ->orderBy('stock_point_id')
            ->orderBy('product_id')
            ->paginate($perPage);
    }

    /**
     * Set (or clear) the low-stock threshold on a single stock row (R19.1).
     *
     * The threshold is per-(product, stock_point) because a stock row IS the
     * unique (owner, product, stock_point) tuple. Passing null clears the
     * threshold so the row can no longer raise a low-stock alert. The updated
     * row is returned refreshed, including whether it is now breaching so the
     * caller can surface the alert immediately (R19.2).
     */
    public function setThreshold(Stock $stock, ?string $threshold): Stock
    {
        $stock->low_stock_threshold = $threshold;
        $stock->save();

        return $stock->refresh();
    }

    /**
     * Whether a stock row currently raises a low-stock alert: true iff a
     * threshold is configured AND the on-hand quantity is at or below it
     * (R19.2). Delegates to the model so the alert predicate and the report's
     * SQL filter stay in lockstep (design Property 14).
     */
    public function isAlerting(Stock $stock): bool
    {
        return $stock->isLowStock();
    }
}
