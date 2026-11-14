<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Services\CustomDomainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Platform_Admin view over every tenant's domains, with a kill switch for
 * custom domains. Runs on the central `platform.admin` pipeline (no tenancy).
 */
class DomainAdminController extends Controller
{
    public function __construct(private readonly CustomDomainService $domains) {}

    /** GET /api/admin/domains?status=&tenant_id=&q= */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['sometimes', Rule::in([Domain::STATUS_PENDING, Domain::STATUS_ACTIVE, Domain::STATUS_FAILED, Domain::STATUS_DISABLED])],
            'tenant_id' => ['sometimes', 'string', 'max:64'],
            'q' => ['sometimes', 'string', 'max:100'],
        ]);

        $perPage = max(1, min((int) $request->integer('per_page', 20), 100));

        $page = Domain::query()
            ->custom()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('tenant_id'), fn ($query) => $query->where('tenant_id', $request->query('tenant_id')))
            ->when($request->filled('q'), fn ($query) => $query->where('domain', 'like', '%'.addcslashes((string) $request->query('q'), '%_\\').'%'))
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Domain $domain): array => $this->domains->describe($domain))->values(),
            'meta' => ['total' => $page->total(), 'per_page' => $page->perPage(), 'current_page' => $page->currentPage()],
        ]);
    }

    /** POST /api/admin/domains/{domain}/disable */
    public function disable(Request $request, Domain $domain): JsonResponse
    {
        return response()->json(['data' => $this->domains->describe($this->domains->disable($domain, $request->user()))]);
    }

    /** POST /api/admin/domains/{domain}/enable */
    public function enable(Request $request, Domain $domain): JsonResponse
    {
        return response()->json(['data' => $this->domains->describe($this->domains->enable($domain, $request->user()))]);
    }
}
