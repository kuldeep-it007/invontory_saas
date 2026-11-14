<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Roles;
use App\Models\ReportExport;
use App\Models\ReportSchedule;
use App\Reports\ExportFormat;
use App\Reports\ReportAccessContext;
use App\Reports\ReportQuerySpec;
use App\Reports\ReportType;
use App\Services\ReportExportService;
use App\Services\TenantIntegrations\TenantExportStorage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export + scheduled/subscription report HTTP surface (R29.4, R29.5, R29.6):
 *
 *   - POST /api/reports/export   — export a report to Excel/CSV/printable PDF
 *   - POST /api/reports/schedule — create a scheduled/subscription report
 *
 * BOTH routes are mounted behind `feature:exports`, so the whole surface is
 * reachable only when the tenant's plan enables the `exports` Feature_Flag
 * (R29.4) — the controller itself therefore assumes exports are permitted. It
 * stays thin: it validates the requested report type and format against the
 * allow-listed enums, maps the request body to a {@see ReportQuerySpec} and
 * {@see ReportAccessContext}, and delegates all behaviour (previous-month
 * default, scoping, large-export async handling, scheduling) to the
 * {@see ReportExportService}.
 */
class ReportExportController extends Controller
{
    public function __construct(private readonly ReportExportService $exports) {}

    /**
     * POST /api/reports/export — request an export of a report (R29.4, R29.5).
     *
     * The body carries the report `type` and export `format` (both allow-listed)
     * plus the same filters/grouping/sort a read accepts. A large result is
     * processed asynchronously and the response returns the export handle in a
     * non-terminal state with a 202; a small export is produced inline and
     * returns 201 with the download link already populated (R29.5).
     */
    public function export(Request $request): JsonResponse
    {
        $type = $this->resolveType((string) $request->input('type', ''));
        $format = $this->resolveFormat((string) $request->input('format', 'excel'));

        $spec = ReportQuerySpec::fromArray([
            'type' => $type->value,
            'filters' => $this->filtersFrom($request),
            'grouping' => (array) $request->input('grouping', []),
            'sort' => (array) $request->input('sort', []),
            'limit' => $request->input('limit'),
            'page' => $request->input('page'),
        ]);

        $context = ReportAccessContext::fromUser($request->user());

        try {
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

        $completed = $export->isCompleted();

        return response()->json([
            'data' => [
                'id' => $export->id,
                'report_type' => $export->report_type,
                'format' => $export->format,
                'status' => $export->status,
                'row_count' => $export->row_count,
                'download_url' => $export->download_url,
            ],
        ], $completed ? Response::HTTP_CREATED : Response::HTTP_ACCEPTED);
    }

    /**
     * GET /api/reports/exports/{export}/download - stream a finished export.
     *
     * Files live either in the tenant's own bucket (	enants/<id>/...) or, for
     * tenants without one, on the local disk. Only the person who requested the
     * export, or a Company_Admin, may download it; anyone else gets a 404 so the
     * existence of other people's exports is not revealed.
     */
    public function download(Request $request, TenantExportStorage $storage, int $export): StreamedResponse
    {
        $record = ReportExport::query()->findOrFail($export);
        $user = $request->user();
        $allowed = $user !== null
            && ($this->principalId($request) === $record->requested_by || $user->hasRole(Roles::COMPANY_ADMIN));

        if (! $allowed || ! $record->isCompleted() || $record->file_path === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $path = $record->file_path;
        if ($storage->owns($path)) {
            $disk = $storage->active() ? $storage->disk() : null;
        } else {
            // Legacy file on the local disk; only our own export folder is ever served.
            $disk = str_starts_with($path, 'exports/reports/') && ! str_contains($path, '..') ? Storage::disk('local') : null;
        }

        if ($disk === null || ! $disk->exists($path)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $disk->download($path, basename($path), ['Content-Type' => $record->format()->mimeType()]);
    }

    /**
     * POST /api/reports/schedule — create a scheduled/subscription report
     * (R29.6).
     *
     * The schedule stores the report `type`, `format`, `recipients`, `cadence`,
     * and an OPTIONAL spec fragment — any date range in it is stripped so every
     * run defaults to the previous calendar month (R29.2, R29.6). At least one
     * recipient is required.
     */
    public function schedule(Request $request): JsonResponse
    {
        $type = $this->resolveType((string) $request->input('type', ''));
        $format = $this->resolveFormat((string) $request->input('format', 'excel'));

        $recipients = $this->recipientsFrom($request);
        if ($recipients === []) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'At least one recipient email is required.');
        }

        $cadence = (string) $request->input('cadence', ReportSchedule::CADENCE_MONTHLY);

        try {
            $schedule = $this->exports->schedule(
                type: $type,
                recipients: $recipients,
                format: $format,
                cadence: $cadence,
                specInput: [
                    'filters' => $this->filtersFrom($request),
                    'grouping' => (array) $request->input('grouping', []),
                    'sort' => (array) $request->input('sort', []),
                ],
                name: $request->input('name'),
                createdBy: $this->principalId($request),
            );
        } catch (InvalidArgumentException $e) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, $e->getMessage());
        }

        return response()->json([
            'data' => [
                'id' => $schedule->id,
                'name' => $schedule->name,
                'report_type' => $schedule->report_type,
                'format' => $schedule->format,
                'recipients' => $schedule->recipients,
                'cadence' => $schedule->cadence,
                'active' => $schedule->active,
                'next_run_at' => $schedule->next_run_at?->toIso8601String(),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Resolve an allow-listed report type or 422 (R31.2).
     */
    private function resolveType(string $value): ReportType
    {
        $type = ReportType::tryFromValue($value);

        if ($type === null) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, sprintf('Unknown report type [%s].', $value));
        }

        return $type;
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
     * The report filters (R29.1) from the request body. Absent keys are omitted
     * so the service applies the previous-calendar-month default when no range
     * is given (R29.2).
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
                'sellerType' => $request->input('seller_type'),
                'sellerId' => $request->input('seller_id'),
                'productId' => $request->input('product_id'),
                'categoryId' => $request->input('category_id'),
                'stockPointId' => $request->input('stock_point_id'),
                'status' => $request->input('status'),
            ],
            static fn ($value): bool => $value !== null && $value !== '',
        );
    }

    /**
     * The recipient email list from the request, normalized to a list of
     * non-empty strings.
     *
     * @return list<string>
     */
    private function recipientsFrom(Request $request): array
    {
        $recipients = $request->input('recipients', []);

        if (is_string($recipients)) {
            $recipients = explode(',', $recipients);
        }

        $clean = [];
        foreach ((array) $recipients as $email) {
            $email = is_string($email) ? trim($email) : '';
            if ($email !== '') {
                $clean[] = $email;
            }
        }

        return $clean;
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
