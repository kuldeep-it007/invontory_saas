<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SetLowStockThresholdRequest;
use App\Http\Requests\SetStockQuantityRequest;
use App\Http\Requests\StoreDistributorStockRequest;
use App\Http\Requests\StoreStockAdjustmentRequest;
use App\Http\Requests\StoreStockInwardRequest;
use App\Models\Distributor;
use App\Models\Stock;
use App\Reports\ReportAccessContext;
use App\Services\LowStockService;
use App\Services\StockOperationsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Stock operations HTTP surface (R14, R19).
 *
 * Thin controller: it validates input via form requests and delegates all
 * business logic to focused services — stock inward and manual adjustments to
 * {@see StockOperationsService} (R14), and low-stock thresholds, alerts, and
 * reporting to {@see LowStockService} (R19). It runs on the tenant pipeline, so
 * every action is confined to the active tenant database (R28.10).
 */
class StockController extends Controller
{
    public function __construct(
        private readonly StockOperationsService $operations,
        private readonly LowStockService $lowStock,
    ) {}

    /**
     * POST /api/stock/inward — record stock inward (R14.1, R14.2).
     *
     * Increases the on-hand Stock and appends one immutable Stock_Ledger entry
     * in a single atomic transaction. Authorized by the route pipeline
     * (`permission:inventory.adjust` + `subscription.active`). Returns the
     * ledger entry written.
     */
    public function inward(StoreStockInwardRequest $request): JsonResponse
    {
        $ledger = $this->operations->inward(
            $request->validated(),
            $request->user()?->getKey(),
        );

        return response()->json(['data' => $ledger], 201);
    }

    /**
     * POST /api/stock/adjust — record a manual stock adjustment (R14.3, R14.4,
     * R14.5, R34.3).
     *
     * The signed quantity (positive to increase, negative to decrease) is
     * applied through the stock authority and a Stock_Ledger entry is written;
     * the adjustment and its (required, non-empty) reason are recorded in the
     * Audit_Log. A missing/whitespace reason is rejected with a 422 by the form
     * request before reaching here. Returns the ledger entry written.
     */
    public function adjust(StoreStockAdjustmentRequest $request): JsonResponse
    {
        $ledger = $this->operations->adjust(
            $request->validated(),
            $request->user()?->getKey(),
        );

        return response()->json(['data' => $ledger], 201);
    }

    /**
     * POST /api/stock/distributors — add stock held by a specific Distributor.
     * The row and ledger entry are owned by the distributor (kept separate from
     * Company stock). Returns the ledger entry written.
     */
    public function distributorInward(StoreDistributorStockRequest $request): JsonResponse
    {
        $data = $request->validated();

        // A distributor can only add stock to ITSELF, whatever the payload says.
        $own = $this->ownDistributorId($request);
        if ($own !== null) {
            $data['distributor_id'] = $own;
        }

        $ledger = $this->operations->inwardForDistributor(
            $data,
            $request->user()?->getKey(),
        );

        return response()->json(['data' => $ledger], 201);
    }

    /**
     * PATCH /api/stock/{stock}/quantity — edit a stock row to an absolute
     * on-hand quantity. Applied as a ledgered, audited adjustment (reason
     * required); returns the refreshed stock row and the ledger entry.
     */
    public function setQuantity(SetStockQuantityRequest $request, Stock $stock): JsonResponse
    {
        $data = $request->validated();
        $this->assertStockEditable($request, $stock);

        $ledger = $this->operations->setQuantity(
            $stock,
            (string) $data['quantity'],
            (string) $data['reason'],
            $request->user()?->getKey(),
        );

        return response()->json([
            'data' => $stock->fresh(['product:id,sku,name', 'stockPoint:id,name']),
            'ledger' => $ledger,
        ]);
    }

    /**
     * GET /api/stock/low — the low-stock report (R19.3, R28.8).
     *
     * Paginated and eager-loaded list of every stock row whose on-hand quantity
     * is at or below its configured low-stock threshold, with the product,
     * stock point, and owner needed to render or export each breaching row.
     *
     * Visibility (R28.10): company-side roles read the whole tenant's breaching
     * rows, while a Distributor is confined to its OWN stock — only the rows
     * held at a stock point it owns. The owner binding is resolved from the
     * authenticated principal via {@see ReportAccessContext}, reusing the same
     * buyer-scoping the reporting engine uses so visibility stays consistent. A
     * buyer-scoped principal with no resolvable binding has no readable scope
     * and is confined to an impossible owner so it sees none of the tenant's
     * stock (never tenant-wide).
     */
    public function low(Request $request): JsonResponse
    {
        $context = ReportAccessContext::fromUser($request->user());

        [$ownerType, $ownerId] = $this->visibilityOwner($context);

        return response()->json($this->lowStock->report(
            ownerType: $ownerType,
            ownerId: $ownerId,
        ));
    }

    /**
     * Resolve the (ownerType, ownerId) the low-stock report is confined to for
     * the given access context.
     *
     * - A tenant-wide (company-side) principal returns `[null, null]` — no
     *   owner filter, so the report spans the whole tenant database.
     * - A buyer-scoped principal (Distributor) returns its own
     *   `[buyerType, buyerId]` binding so it sees only its own stock. When it
     *   has no resolvable binding it is confined to a non-existent owner
     *   (`id = 0`) so it reads NONE of the tenant's stock rather than leaking
     *   tenant-wide data.
     *
     * @return array{0: string|null, 1: int|null}
     */
    private function visibilityOwner(ReportAccessContext $context): array
    {
        if (! $context->isBuyerScoped()) {
            return [null, null];
        }

        if (! $context->hasBuyerBinding()) {
            // No resolvable owner binding: confine to a non-existent owner of
            // the buyer's morph type (id 0 never exists) so the principal reads
            // NONE of the tenant's stock rather than leaking tenant-wide rows.
            return [(new Distributor)->getMorphClass(), 0];
        }

        return [$context->buyerType, $context->buyerId];
    }

    /**
     * PATCH /api/stock/{stock}/threshold — configure the per-(product,
     * stock_point) low-stock threshold on a stock row (R19.1).
     *
     * A null `low_stock_threshold` clears the configuration. The response
     * includes an `alert` flag reflecting whether the row is now at or below
     * its threshold, so the caller surfaces the low-stock alert immediately
     * (R19.2).
     */
    public function setThreshold(SetLowStockThresholdRequest $request, Stock $stock): JsonResponse
    {
        $this->assertStockEditable($request, $stock);

        $threshold = $request->validated()['low_stock_threshold'];

        $stock = $this->lowStock->setThreshold(
            $stock,
            $threshold === null ? null : (string) $threshold,
        );

        return response()->json([
            'data' => $stock,
            'alert' => $this->lowStock->isAlerting($stock),
        ]);
    }

    /**
     * The distributor id a Distributor PANEL user is confined to, or null for a
     * company-side user. A company-side user must hold `inventory.adjust`
     * (these routes also admit the Distributor role, so this is re-checked
     * here); a Distributor-role user without a linked distributor is refused.
     */
    private function ownDistributorId(Request $request): ?int
    {
        $user = $request->user();

        if ($user?->distributor_id !== null) {
            return (int) $user->distributor_id;
        }

        if ($user === null || ! $user->can('inventory.adjust')) {
            throw new AccessDeniedHttpException('You are not allowed to manage this stock.');
        }

        return null;
    }

    /**
     * A distributor may only edit rows owned by ITS OWN distributor; company-side
     * users keep their existing reach (any row they hold `inventory.adjust` for).
     */
    private function assertStockEditable(Request $request, Stock $stock): void
    {
        $own = $this->ownDistributorId($request);

        if ($own !== null
            && ($stock->stockable_type !== (new Distributor)->getMorphClass() || (int) $stock->stockable_id !== $own)) {
            throw new AccessDeniedHttpException('You can only manage your own stock.');
        }
    }}
