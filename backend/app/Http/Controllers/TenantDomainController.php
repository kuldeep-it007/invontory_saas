<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomDomainRequest;
use App\Models\Domain;
use App\Services\CustomDomainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tenant self-service custom domains (Company_Admin only, via the route).
 *
 * Every lookup is scoped to the resolved tenant, so one company can never read
 * or change another company's domains by guessing an id.
 */
class TenantDomainController extends Controller
{
    public function __construct(private readonly CustomDomainService $domains) {}

    /** GET /api/tenant/domains - this workspace's addresses and DNS instructions. */
    public function index(): JsonResponse
    {
        $list = Domain::query()
            ->where('tenant_id', tenant('id'))
            ->orderByDesc('is_primary')
            ->orderBy('type')
            ->orderBy('id')
            ->get()
            ->map(fn (Domain $domain): array => $this->domains->describe($domain));

        return response()->json(['data' => $list]);
    }

    /** POST /api/tenant/domains - start a claim (status `pending`). */
    public function store(StoreCustomDomainRequest $request): JsonResponse
    {
        $domain = $this->domains->add(tenant(), (string) $request->validated('domain'), $request->user());

        return response()->json(['data' => $this->domains->describe($domain)], 201);
    }

    /** POST /api/tenant/domains/{domain}/verify - check DNS now. */
    public function verify(int $domain): JsonResponse
    {
        $verified = $this->domains->verify($this->own($domain), manual: true);

        return response()->json(['data' => $this->domains->describe($verified)]);
    }

    /** POST /api/tenant/domains/{domain}/primary - body `primary` (default true). */
    public function primary(Request $request, int $domain): JsonResponse
    {
        $updated = $this->domains->setPrimary(
            $this->own($domain),
            $request->boolean('primary', true),
            $request->user(),
        );

        return response()->json(['data' => $this->domains->describe($updated)]);
    }

    /** DELETE /api/tenant/domains/{domain} */
    public function destroy(Request $request, int $domain): JsonResponse
    {
        $this->domains->remove($this->own($domain), $request->user());

        return response()->json(null, 204);
    }

    private function own(int $id): Domain
    {
        return Domain::query()->where('tenant_id', tenant('id'))->findOrFail($id);
    }
}
