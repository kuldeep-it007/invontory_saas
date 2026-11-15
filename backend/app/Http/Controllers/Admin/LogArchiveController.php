<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LogArchive;
use App\Models\PlatformAuditLog;
use App\Services\Logging\LogArchiver;
use App\Services\Logging\LogArchiveSearch;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Platform_Admin access to the archived logs in the master bucket: list,
 * search, download one file, export a zip for an external audit. Every search,
 * download and export is itself recorded in the platform audit log.
 */
class LogArchiveController extends Controller
{
    private const MAX_SEARCH_DAYS = 31;

    private const MAX_EXPORT_DAYS = 93;

    public function __construct(private readonly LogArchiveSearch $search) {}

    /** GET /api/admin/log-archives */
    public function index(Request $request): JsonResponse
    {
        $filters = $this->filters($request, 366);
        $page = $this->search->query($filters)->paginate(max(1, min((int) $request->integer('per_page', 50), 200)));

        return response()->json([
            'data' => $page->getCollection()->map(fn (LogArchive $a): array => $this->describe($a))->values(),
            'meta' => ['total' => $page->total(), 'per_page' => $page->perPage(), 'current_page' => $page->currentPage()],
        ]);
    }

    /** GET /api/admin/log-archives/search */
    public function search(Request $request): JsonResponse
    {
        $filters = $this->filters($request, self::MAX_SEARCH_DAYS);
        $result = $this->search->search($filters);

        PlatformAuditLog::record('logs.searched', $request, $filters['tenant_id'] ?? null, [], [
            'filters' => array_filter($filters), 'matches' => count($result['matches']),
        ]);

        return response()->json(['data' => $result]);
    }

    /** GET /api/admin/log-archives/{archive}/download */
    public function download(Request $request, LogArchive $archive, LogArchiver $archiver): StreamedResponse
    {
        PlatformAuditLog::record('logs.downloaded', $request, $archive->tenant_id, [], ['path' => $archive->path]);

        return $archiver->disk()->download($archive->path, basename($archive->path), ['Content-Type' => 'application/gzip']);
    }

    /** GET /api/admin/log-archives/export - zip + checksum manifest */
    public function export(Request $request): BinaryFileResponse
    {
        $filters = $this->filters($request, self::MAX_EXPORT_DAYS);
        $archives = $this->search->query($filters)->limit(LogArchiveSearch::MAX_FILES)->get();

        if ($archives->isEmpty()) {
            throw ValidationException::withMessages(['from' => 'No archived logs match these filters.']);
        }

        PlatformAuditLog::record('logs.exported', $request, $filters['tenant_id'] ?? null, [], [
            'filters' => array_filter($filters), 'files' => $archives->count(),
        ]);

        $name = sprintf('logs-%s-%s-to-%s.zip', $filters['tenant_id'] ?? ($filters['scope'] ?? 'all'), $filters['from'], $filters['to']);

        return response()->download($this->search->zip($archives), $name, ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request, int $maxDays): array
    {
        $data = $request->validate([
            'scope' => ['nullable', Rule::in([LogArchive::SCOPE_TENANT, LogArchive::SCOPE_PLATFORM])],
            'tenant_id' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_-]{1,64}$/'],
            'kind' => ['nullable', Rule::in([LogArchive::KIND_APP, LogArchive::KIND_AUDIT])],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'q' => ['nullable', 'string', 'max:200'],
            'event' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'string', 'max:64'],
            'request_id' => ['nullable', 'string', 'max:128'],
            'level' => ['nullable', Rule::in(['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'])],
        ]);

        if (CarbonImmutable::parse($data['from'])->diffInDays(CarbonImmutable::parse($data['to'])) + 1 > $maxDays) {
            throw ValidationException::withMessages(['to' => "Choose a range of at most {$maxDays} days."]);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(LogArchive $archive): array
    {
        return [
            'id' => $archive->id,
            'scope' => $archive->scope,
            'tenant_id' => $archive->tenant_id,
            'tenant_slug' => $archive->tenant_slug,
            'kind' => $archive->kind,
            'log_date' => $archive->log_date->toDateString(),
            'path' => $archive->path,
            'size' => $archive->size,
            'lines' => $archive->lines,
            'sha256' => $archive->sha256,
        ];
    }
}
