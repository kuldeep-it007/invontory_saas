<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\QuotaService;
use Illuminate\Http\JsonResponse;

/**
 * Usage-versus-limits endpoint (R5.5).
 *
 * Thin controller: delegates to {@see QuotaService::usage()} to build the
 * coherent snapshot of the active tenant's current resource counts against the
 * active plan's limits. Runs on the tenant pipeline, so tenancy is already
 * initialized and the snapshot reports THIS tenant's usage. The same payload
 * backs the dashboard usage widget on the frontend.
 */
class UsageController extends Controller
{
    public function __construct(private readonly QuotaService $quota) {}

    /**
     * GET /api/usage — the active tenant's usage vs active plan limits (R5.5).
     */
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->quota->usage()]);
    }
}
