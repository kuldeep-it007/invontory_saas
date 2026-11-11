<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreDistributorRequest;
use App\Http\Requests\UpdateDistributorRequest;
use App\Models\Distributor;
use App\Services\DistributorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Distributor management endpoints (R10.1, R10.3, R10.5).
 *
 * Thin controller: validates input and delegates all business rules to
 * {@see DistributorService}. Every action runs against the active tenant
 * database (resolved by the tenancy middleware). The list endpoint is paginated
 * and selects only the columns the index view needs (design efficiency rules).
 */
class DistributorController extends Controller
{
    public function __construct(private readonly DistributorService $distributors) {}

    /**
     * GET /api/distributors — paginated list of the tenant's distributors.
     *
     * Selects only the columns needed by the listing and counts dealers via an
     * eager aggregate to avoid N+1 (design efficiency rules).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        $distributors = Distributor::query()
            ->select(['id', 'company_id', 'name', 'contact_email', 'gstin', 'created_at'])
            ->withCount('dealers')
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json($distributors);
    }

    /**
     * GET /api/distributors/{distributor} — a single distributor with its
     * dealer count.
     */
    public function show(Distributor $distributor): JsonResponse
    {
        $distributor->loadCount('dealers');

        return response()->json(['data' => $distributor]);
    }

    /**
     * POST /api/distributors — create a distributor under the tenant Company,
     * enforcing the `max_distributors` quota (R10.1, R5.1, R5.2).
     */
    public function store(StoreDistributorRequest $request): JsonResponse
    {
        $distributor = $this->distributors->create($request->validated());

        return response()->json(['data' => $distributor], 201);
    }

    /**
     * PATCH /api/distributors/{distributor} — update attributes, including
     * Payment_Terms; the change is audited (R10.5).
     */
    public function update(UpdateDistributorRequest $request, Distributor $distributor): JsonResponse
    {
        $distributor = $this->distributors->update($distributor, $request->validated());

        return response()->json(['data' => $distributor]);
    }
}
