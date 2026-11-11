<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockPointRequest;
use App\Http\Requests\UpdateStockPointRequest;
use App\Models\StockPoint;
use App\Services\StockPointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Company stock-point management + stock listing HTTP surface (R13.4, R14).
 *
 * Thin controller: delegates to {@see StockPointService}. Listing is a read
 * surface (`inventory.view`); creating a stock point mutates configuration, so
 * it requires `inventory.adjust` + an active subscription. Runs on the tenant
 * pipeline (active tenant Company).
 */
class StockPointController extends Controller
{
    public function __construct(private readonly StockPointService $points) {}

    /**
     * GET /api/stock-points — the Company's stock points.
     */
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->points->list()]);
    }

    /**
     * POST /api/stock-points — create a Company stock point.
     */
    public function store(StoreStockPointRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->points->create($request->validated())], 201);
    }

    /**
     * PATCH /api/stock-points/{stockPoint} — update a Company stock point's
     * name and/or sort order (R13.4, R13.8).
     */
    public function update(UpdateStockPointRequest $request, StockPoint $stockPoint): JsonResponse
    {
        return response()->json(['data' => $this->points->update($stockPoint, $request->validated())]);
    }

    /**
     * GET /api/stock — the Company's per-(product, point) stock rows, for the
     * stock-by-point view.
     */
    public function stock(): JsonResponse
    {
        return response()->json(['data' => $this->points->stockRows()]);
    }

    /**
     * GET /api/stock/distributors — distributor-owned stock rows for the
     * "Distributor Stock Management" view (R13.4, R13.5).
     *
     * Scoped by WHO is asking: a logged-in Distributor user sees ONLY its own
     * party's stock; a Company-side user (no distributor link) sees every
     * distributor's stock for oversight. This keeps each role confined to the
     * stock it is entitled to view.
     */
    public function distributorStock(Request $request): JsonResponse
    {
        $distributorId = $request->user()?->distributor_id;

        // Optional listing filters (kept in the URL by the UI): product,
        // distributor (company view only) and low-stock only.
        $filters = [
            'product_id' => $request->query('product_id'),
            'distributor_id' => $request->query('distributor_id'),
            'low' => filter_var($request->query('low'), FILTER_VALIDATE_BOOLEAN),
        ];

        // A distributor is always confined to its OWN stock; any distributor_id
        // in the query is ignored for them.
        $rows = $distributorId !== null
            ? $this->points->stockRowsForDistributor((int) $distributorId, $filters)
            : $this->points->distributorStockRows($filters);

        return response()->json(['data' => $rows]);
    }
}
