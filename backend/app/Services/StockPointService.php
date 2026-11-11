<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Distributor;
use App\Models\Stock;
use App\Models\StockPoint;
use Illuminate\Support\Collection;

/**
 * Company stock-point management + stock listing (R13.4, R14).
 *
 * The Stock operations screen is a Company_Admin surface (R14), so stock points
 * and stock rows here are owned by the active tenant's singleton {@see Company}
 * — mirroring {@see StockOperationsService}. This service lists and creates the
 * Company's stock points, and lists its per-(product, point) stock rows so the
 * UI can render a "stock by point" view and offer product/point dropdowns.
 */
class StockPointService
{
    /**
     * The Company's stock points, ordered for display (primary first).
     *
     * @return Collection<int, StockPoint>
     */
    public function list(): Collection
    {
        $company = $this->owner();

        return StockPoint::query()
            ->where('owner_type', $company->getMorphClass())
            ->where('owner_id', (int) $company->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'sort_order']);
    }

    /**
     * Create a Company-owned stock point.
     *
     * @param  array{name: string, sort_order?: int|null}  $data
     */
    public function create(array $data): StockPoint
    {
        $point = new StockPoint([
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
        $point->owner()->associate($this->owner());
        $point->save();

        return $point;
    }

    /**
     * Update a Company-owned stock point's display name and/or sort order
     * (R13.4, R13.8). Ownership is asserted so the Company can only edit its
     * own points; a point owned by another party yields a 404 upstream.
     *
     * @param  array{name?: string, sort_order?: int|null}  $data
     */
    public function update(StockPoint $point, array $data): StockPoint
    {
        $company = $this->owner();

        abort_unless(
            $point->owner_type === $company->getMorphClass()
                && (int) $point->owner_id === (int) $company->getKey(),
            404,
            'The requested stock point was not found.',
        );

        if (array_key_exists('name', $data) && $data['name'] !== null) {
            $point->name = $data['name'];
        }
        if (array_key_exists('sort_order', $data) && $data['sort_order'] !== null) {
            $point->sort_order = (int) $data['sort_order'];
        }
        $point->save();

        return $point->refresh();
    }

    /**
     * The Company's stock rows with product + point context, for the stock-by-
     * point listing. Eager-loads product and stock point so the view never
     * N+1s, newest/most-stocked first.
     *
     * @return Collection<int, Stock>
     */
    public function stockRows(): Collection
    {
        $company = $this->owner();

        return Stock::query()
            ->with(['product:id,sku,name', 'stockPoint:id,name'])
            ->where('stockable_type', $company->getMorphClass())
            ->where('stockable_id', (int) $company->getKey())
            ->orderBy('stock_point_id')
            ->orderBy('product_id')
            ->get();
    }

    /**
     * Distributor-owned stock rows across the tenant — the "Distributor Stock
     * Management" view (R13.4, R13.5). When a Distributor buys from the Company
     * and the order is confirmed, stock moves Company→Distributor and lands as
     * a Stock row owned by the Distributor (`stockable_type = Distributor`).
     * This lists every such row with its owning distributor, product, and stock
     * point so the Company can oversee stock held across its distribution tree.
     *
     * Eager-loads the polymorphic owner, product, and stock point so the view
     * never N+1s; grouped by distributor, then point, then product.
     *
     * @return Collection<int, Stock>
     */
    public function distributorStockRows(array $filters = []): Collection
    {
        return Stock::query()
            ->with(['owner', 'product:id,sku,name', 'stockPoint:id,name'])
            ->where('stockable_type', (new Distributor())->getMorphClass())
            ->when(! empty($filters['distributor_id']), fn ($q) => $q->where('stockable_id', (int) $filters['distributor_id']))
            ->tap(fn ($q) => $this->applyStockFilters($q, $filters))
            ->orderBy('stockable_id')
            ->orderBy('stock_point_id')
            ->orderBy('product_id')
            ->get();
    }

    /**
     * The stock rows owned by ONE specific distributor — the "My Stock" view a
     * logged-in Distributor sees of its OWN inventory (R13.5). Scoped strictly
     * to the given distributor id so a distributor can never see another's
     * stock. Eager-loads product and point so the view never N+1s.
     *
     * @return Collection<int, Stock>
     */
    public function stockRowsForDistributor(int $distributorId, array $filters = []): Collection
    {
        return Stock::query()
            ->with(['product:id,sku,name', 'stockPoint:id,name'])
            ->where('stockable_type', (new Distributor())->getMorphClass())
            ->where('stockable_id', $distributorId)
            ->tap(fn ($q) => $this->applyStockFilters($q, $filters))
            ->orderBy('stock_point_id')
            ->orderBy('product_id')
            ->get();
    }

    /**
     * Shared optional filters for the distributor stock listings: `product_id`,
     * and `low` (only rows at or below their configured low-stock threshold).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Stock>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyStockFilters($query, array $filters): void
    {
        if (! empty($filters['product_id'])) {
            $query->where('product_id', (int) $filters['product_id']);
        }

        if (! empty($filters['low'])) {
            $query->breachingLowStock();
        }
    }

    /**
     * The active tenant's singleton Company — the owner of stock operations.
     */
    private function owner(): Company
    {
        return Company::query()->firstOrFail();
    }
}
