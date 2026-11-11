<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Roles;
use App\Models\Company;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Reports\ExportFormat;
use App\Reports\ReportAccessContext;
use App\Services\DashboardService;
use App\Services\InventoryViewService;
use App\Services\ReportExportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Dashboards & inventory views HTTP surface (R33.1, R33.2, R33.3):
 *
 *   - GET  /dashboard                      — company master dashboard metrics
 *   - GET  /inventory/views/{view}         — stock-on-hand / item-detail / pending-orders
 *   - POST /inventory/views/{view}/export  — export an inventory view (R33.3)
 *
 * Thin controller: it resolves the requested view against the allow-listed set
 * {@see InventoryViewService::views()}, maps the request to the filters/
 * pagination the view accepts, and delegates all behaviour to the
 * {@see DashboardService} (R33.1), the {@see InventoryViewService} (R33.2), and
 * the shared {@see ReportExportService} (R33.3). No business logic lives here.
 *
 * The READ endpoints run on the tenant pipeline behind the `dashboard.view`
 * ability (granted to Read-only too — R8.4), so dashboards and views are
 * physically confined to the active tenant database (R28.10). The EXPORT
 * endpoints are mounted behind the `exports` Feature_Flag and the Company_Admin
 * role: R33.3 requires that WHERE `exports` is enabled, any Company_Admin may
 * export dashboard/inventory data WITHOUT further authorization — so the route
 * gate (feature flag + role) is the whole check and the controller performs no
 * additional per-action authorization.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly InventoryViewService $views,
        private readonly ReportExportService $exports,
    ) {}

    /**
     * GET /api/dashboard — the company master dashboard snapshot (R33.1).
     *
     * Returns the headline operational metrics (order counts incl. the pending
     * bucket, sales/collection figures, outstanding balance, inventory
     * headlines, plan usage) for the active tenant.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->dashboard->snapshot($this->resolveScope($request))]);
    }

    /**
     * Decide whose data the dashboard shows:
     *   • optional `party_type`/`party_id` ("acting as") → that party, but only
     *     if the caller may act as it (Company → any party; Distributor → its
     *     own dealers; anyone → themselves);
     *   • a Distributor/Dealer panel user → its OWN party;
     *   • a Distributor/Dealer role with no party link → nothing (never leaks
     *     the Company's or the tenant's figures);
     *   • any company-side user → the Company's own data.
     *
     * @return array{0: string, 1: int|null}
     */
    private function resolveScope(Request $request): array
    {
        $user = $request->user();
        $own = $user?->reportBuyerBinding();

        $partyType = $request->query('party_type');
        $partyId = $request->query('party_id');

        if (in_array($partyType, ['distributor', 'dealer'], true) && is_numeric($partyId)) {
            $class = $partyType === 'dealer' ? Dealer::class : Distributor::class;
            $id = (int) $partyId;

            $isSelf = $own !== null && $own[0] === $class && $own[1] === $id;
            $isCompany = $user !== null && ($user->hasRole(Roles::COMPANY_ADMIN) || $user->hasRole(Roles::COMPANY_STAFF));
            $isOwnDealer = $partyType === 'dealer'
                && $user?->distributor_id !== null
                && Dealer::query()->whereKey($id)->where('distributor_id', $user->distributor_id)->exists();

            if (! ($isSelf || $isCompany || $isOwnDealer)) {
                throw new AccessDeniedHttpException('You can only view your own dashboard.');
            }

            return [$class, $id];
        }

        if ($own !== null) {
            return $own;
        }

        if ($user !== null && ($user->hasRole(Roles::DISTRIBUTOR) || $user->hasRole(Roles::DEALER))) {
            // Unlinked panel user: bind to a non-existent party so nothing shows.
            return [Distributor::class, 0];
        }

        return [Company::class, null];
    }

    /**
     * GET /api/inventory/views/{view} — run an inventory view (R33.2).
     *
     * `{view}` MUST be one of `stock-on-hand`, `item-detail`, `pending-orders`
     * (anything else is a 404). The result is a paginated page from the shared
     * report engine, so it carries the standard list envelope with pagination
     * metadata. A view that needs a required filter (item-detail → product_id)
     * yields a 422; a principal not permitted to read the data yields a 403
     * (R28.10).
     */
    public function view(Request $request, string $view): JsonResponse
    {
        $this->assertKnownView($view);

        $context = ReportAccessContext::fromUser($request->user());

        try {
            $result = $this->views->run(
                view: $view,
                context: $context,
                filters: $this->filtersFrom($request),
                limit: $this->intOrNull($request->query('limit')),
                page: $this->intOrNull($request->query('page')),
            );
        } catch (AuthorizationException $e) {
            abort(Response::HTTP_FORBIDDEN, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, $e->getMessage());
        }

        return response()->json($result->toArray());
    }

    /**
     * POST /api/inventory/views/{view}/export — export an inventory view's data
     * (R33.3).
     *
     * Mounted behind `feature:exports` + the Company_Admin role, so reaching
     * this action already satisfies R33.3's "any Company_Admin, no further
     * authorization" rule — the controller simply resolves the view to its spec
     * and hands it to the export service (the same pipeline a report export
     * uses: inline for a small view, a queued streaming job for a large one —
     * R29.5). A large export returns 202 with a pending handle to poll; a small
     * one returns 201 with the download link populated.
     */
    public function exportView(Request $request, string $view): JsonResponse
    {
        $this->assertKnownView($view);

        $format = $this->resolveFormat((string) $request->input('format', 'excel'));
        $context = ReportAccessContext::fromUser($request->user());

        try {
            $spec = $this->views->specFor(
                view: $view,
                filters: $this->filtersFrom($request),
                limit: $this->intOrNull($request->input('limit')),
                page: $this->intOrNull($request->input('page')),
            );

            $export = $this->exports->export(
                spec: $spec,
                context: $context,
                format: $format,
                requestedBy: $this->principalId($request),
            );
        } catch (AuthorizationException $e) {
            abort(Response::HTTP_FORBIDDEN, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, $e->getMessage());
        }

        return $this->exportResponse($export);
    }

    /**
     * Resolve + guard the requested view name against the allow-list, aborting
     * with a 404 for an unknown view before any work is done (R33.2).
     */
    private function assertKnownView(string $view): void
    {
        if (! InventoryViewService::supports($view)) {
            abort(Response::HTTP_NOT_FOUND, sprintf(
                'Unknown inventory view [%s]. Allowed: %s.',
                $view,
                implode(', ', InventoryViewService::views()),
            ));
        }
    }

    /**
     * Shape the export handle into the standard response envelope: 201 when the
     * file was produced inline (download link ready), 202 when it was queued
     * (poll the handle for completion) — R29.5.
     */
    private function exportResponse(\App\Models\ReportExport $export): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $export->id,
                'report_type' => $export->report_type,
                'format' => $export->format,
                'status' => $export->status,
                'row_count' => $export->row_count,
                'download_url' => $export->download_url,
            ],
        ], $export->isCompleted() ? Response::HTTP_CREATED : Response::HTTP_ACCEPTED);
    }

    /**
     * Collect the filters an inventory view honours from the request. Absent
     * keys are omitted so the report engine applies its defaults (R29.2). The
     * accepted set mirrors the stock-on-hand/order-status reports the views run
     * on: date range, product, stock point, and (for stock views) the buyer
     * owner scope.
     *
     * @return array<string, mixed>
     */
    private function filtersFrom(Request $request): array
    {
        return array_filter(
            [
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'buyerType' => $request->input('buyer_type'),
                'buyerId' => $request->input('buyer_id'),
                'productId' => $request->input('product_id'),
                'stockPointId' => $request->input('stock_point_id'),
                'status' => $request->input('status'),
            ],
            static fn ($value): bool => $value !== null && $value !== '',
        );
    }

    /**
     * Resolve an allow-listed export format or 422 (R29.4).
     */
    private function resolveFormat(string $value): ExportFormat
    {
        $format = ExportFormat::tryFromValue($value);

        if ($format === null) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, sprintf(
                'Unknown export format [%s]. Allowed: %s.',
                $value,
                implode(', ', ExportFormat::values()),
            ));
        }

        return $format;
    }

    /**
     * Coerce a request value to a positive int or null (for limit/page).
     */
    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * The authenticated principal's id for audit, when resolvable.
     */
    private function principalId(Request $request): ?int
    {
        $user = $request->user();

        if ($user !== null && method_exists($user, 'getKey')) {
            $key = $user->getKey();

            return is_numeric($key) ? (int) $key : null;
        }

        return null;
    }
}
