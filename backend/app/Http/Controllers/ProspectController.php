<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreProspectRequest;
use App\Models\Prospect;
use App\Services\ProspectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sign-up and prospect approval endpoints (R9.1–R9.4).
 *
 * Thin controller: validates input and delegates all business rules to
 * {@see ProspectService}. The sign-up endpoint is public (tenant resolved from
 * the host, no authentication); the listing and approve/reject actions run in
 * the authenticated tenant pipeline and are gated by the `distributors.manage`
 * ability in the route definitions. Every action runs against the active
 * tenant database (physical isolation).
 */
class ProspectController extends Controller
{
    public function __construct(private readonly ProspectService $prospects) {}

    /**
     * GET /api/prospects — paginated prospects, optionally filtered by status
     * (defaults to the pending-approvals queue). Selects only the columns the
     * listing needs and filters on the indexed `status` column (R38.4).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        $status = $request->string('status')->toString();

        $prospects = Prospect::query()
            ->select(['id', 'name', 'contact_email', 'intended_buyer_type', 'status', 'serving_distributor_id', 'created_at'])
            ->when(
                $status !== '',
                fn ($query) => $query->where('status', $status),
                fn ($query) => $query->where('status', Prospect::STATUS_PENDING),
            )
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json($prospects);
    }

    /**
     * POST /api/sign-up — public prospect sign-up; records a `pending` prospect
     * (R9.1). The tenant is resolved from the request host by the public tenant
     * pipeline; no authentication is required.
     */
    public function store(StoreProspectRequest $request): JsonResponse
    {
        $prospect = $this->prospects->signUp($request->validated());

        return response()->json(['data' => $prospect], 201);
    }

    /**
     * POST /api/prospects/{prospect}/approve — approve a pending prospect,
     * creating the corresponding Distributor/Dealer, auditing the approval, and
     * queuing an account-created email (R9.2, R9.4). Gated by
     * `distributors.manage` in the route.
     */
    public function approve(Prospect $prospect): JsonResponse
    {
        $buyer = $this->prospects->approve($prospect);

        return response()->json([
            'data' => [
                'prospect' => $prospect->refresh(),
                'buyer' => $buyer,
            ],
        ]);
    }

    /**
     * POST /api/prospects/{prospect}/reject — reject a pending prospect, marking
     * it `rejected` and auditing the rejection (R9.3). Gated by
     * `distributors.manage` in the route.
     */
    public function reject(Prospect $prospect): JsonResponse
    {
        $prospect = $this->prospects->reject($prospect);

        return response()->json(['data' => $prospect]);
    }
}
