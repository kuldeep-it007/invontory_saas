<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreDealerRequest;
use App\Http\Requests\UpdateDealerRequest;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Services\DealerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dealer management endpoints (R10.2, R10.3, R10.4, R10.5).
 *
 * Thin controller: validates input and delegates all business rules to
 * {@see DealerService}. Dealers are nested under a route-model-bound
 * Distributor. Every action runs against the active tenant database; the list
 * endpoint is paginated and column-scoped (design efficiency rules).
 */
class DealerController extends Controller
{
    public function __construct(private readonly DealerService $dealers) {}

    /**
     * GET /api/distributors/{distributor}/dealers — paginated dealers of a
     * distributor. Filtered on the indexed `distributor_id` FK column (R38.4).
     */
    public function index(Request $request, Distributor $distributor): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        $dealers = $distributor->dealers()
            ->select(['id', 'distributor_id', 'name', 'contact_email', 'gstin', 'created_at'])
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json($dealers);
    }

    /**
     * GET /api/distributors/{distributor}/dealers/{dealer} — a single dealer.
     */
    public function show(Distributor $distributor, Dealer $dealer): JsonResponse
    {
        $this->assertBelongsToDistributor($distributor, $dealer);

        return response()->json(['data' => $dealer]);
    }

    /**
     * POST /api/distributors/{distributor}/dealers — create a dealer under the
     * distributor, enforcing `max_dealers` and `max_dealers_per_distributor`
     * (R10.2, R10.4).
     */
    public function store(StoreDealerRequest $request, Distributor $distributor): JsonResponse
    {
        $dealer = $this->dealers->create($distributor, $request->validated());

        return response()->json(['data' => $dealer], 201);
    }

    /**
     * PATCH /api/distributors/{distributor}/dealers/{dealer} — update
     * attributes, including Payment_Terms; the change is audited (R10.5).
     */
    public function update(UpdateDealerRequest $request, Distributor $distributor, Dealer $dealer): JsonResponse
    {
        $this->assertBelongsToDistributor($distributor, $dealer);

        $dealer = $this->dealers->update($dealer, $request->validated());

        return response()->json(['data' => $dealer]);
    }

    /**
     * Guard the nested relationship: a dealer addressed under a distributor
     * must actually belong to it, else 404 (prevents cross-distributor access).
     */
    private function assertBelongsToDistributor(Distributor $distributor, Dealer $dealer): void
    {
        abort_unless(
            (int) $dealer->distributor_id === (int) $distributor->getKey(),
            404,
            'The requested dealer was not found for this distributor.',
        );
    }
}
