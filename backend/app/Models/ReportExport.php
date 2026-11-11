<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantAuditable;
use App\Reports\ExportFormat;
use App\Reports\ExportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Tenant-owned report export (tenant database `report_exports` table — R29.4,
 * R29.5).
 *
 * One row per export request, tracked through its asynchronous lifecycle
 * (pending → processing → completed | failed). A large export is produced by a
 * queued streaming job that flips this row to `completed` and populates
 * {@see $download_url} on success (R29.5), so a caller polls this record to
 * know when the download link is ready.
 *
 * The row persists the EFFECTIVE spec and format so the queued job rebuilds the
 * exact scoped, validated query the HTTP request resolved — the job never
 * re-reads the request (R28.10, R29.2).
 *
 * Isolation is physical, so there is no `tenant_id` column. The model is
 * {@see TenantAuditable} so its create/update is recorded in the tenant
 * `audit_logs` table (R34.1).
 *
 * @property int $id
 * @property string $report_type
 * @property string $format
 * @property array<string, mixed> $spec
 * @property string $status
 * @property int $row_count
 * @property string|null $file_path
 * @property string|null $download_url
 * @property string|null $error
 * @property int|null $requested_by
 * @property Carbon|null $completed_at
 */
class ReportExport extends Model implements Auditable
{
    use TenantAuditable;

    protected $table = 'report_exports';

    protected $fillable = [
        'report_type',
        'format',
        'spec',
        'status',
        'row_count',
        'file_path',
        'download_url',
        'error',
        'requested_by',
        'completed_at',
    ];

    protected $casts = [
        'spec' => 'array',
        'row_count' => 'integer',
        'completed_at' => 'datetime',
    ];

    /**
     * The export format as its typed enum (R29.4).
     */
    public function format(): ExportFormat
    {
        return ExportFormat::from($this->format);
    }

    /**
     * The export status as its typed enum (R29.5).
     */
    public function status(): ExportStatus
    {
        return ExportStatus::from($this->status);
    }

    /**
     * Whether the export is finished and a download link is available (R29.5).
     */
    public function isCompleted(): bool
    {
        return $this->status()->isTerminal() && $this->status() === ExportStatus::COMPLETED;
    }
}
