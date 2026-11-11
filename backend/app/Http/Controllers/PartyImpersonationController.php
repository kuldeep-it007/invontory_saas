<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ImpersonatePartyRequest;
use App\Services\PartyImpersonationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tenant-side party impersonation (R8.6, R34.3).
 *
 * Lets a tenant actor "act as" a buyer party (Distributor or Dealer) so they
 * can browse that party's catalog and place orders on its behalf. Thin
 * controller: delegates the hierarchy check + audit to
 * {@see PartyImpersonationService}. Runs on the tenant pipeline.
 */
class PartyImpersonationController extends Controller
{
    public function __construct(private readonly PartyImpersonationService $impersonation) {}

    /**
     * GET /api/impersonation/targets — the parties the current actor may
     * impersonate (for building the impersonation menu).
     */
    public function targets(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->impersonation->targetsFor($request->user())]);
    }

    /**
     * POST /api/impersonation — begin impersonating a buyer party (R8.6). The
     * hierarchy is enforced server-side; an unauthorized target yields 403.
     */
    public function store(ImpersonatePartyRequest $request): JsonResponse
    {
        $context = $this->impersonation->impersonate(
            $request->user(),
            $request->validated('target_type'),
            (int) $request->validated('target_id'),
        );

        return response()->json(['data' => $context], 201);
    }
}
