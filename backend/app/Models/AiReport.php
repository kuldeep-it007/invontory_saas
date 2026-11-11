<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantAuditable;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Tenant-owned persisted AI report (tenant database `ai_reports` table — R32).
 *
 * A row captures a single natural-language report generation: the user's
 * prompt, the resolved {@see \App\Reports\ReportQuerySpec} (as JSON), and the
 * returned `dataset` rows. The follow-on summary and follow-up endpoints
 * reference this row by id and compute their answers SOLELY from the stored
 * `dataset` (R32.1, R32.2) — they never re-query the database, so a summary or
 * follow-up can never widen the data beyond what the report already returned.
 *
 * Isolation is physical, so there is no `tenant_id` column; the row lives in
 * the active tenant database and the generating principal is captured in
 * `created_by` for role scoping (R32.4). The model is {@see TenantAuditable},
 * so creating a report (and caching its summary) is recorded in the tenant
 * `audit_logs` table — one of the two audit paths that satisfy R32.5 for the
 * generation step (the AiReportService additionally records an explicit
 * `ai_report.*` security event for every request).
 *
 * @property int $id
 * @property string $prompt
 * @property string $report_type
 * @property array<string, mixed> $spec
 * @property list<array<string, mixed>> $dataset
 * @property string|null $summary
 * @property int|null $created_by
 */
class AiReport extends Model implements Auditable
{
    use TenantAuditable;

    protected $table = 'ai_reports';

    protected $fillable = [
        'prompt',
        'report_type',
        'spec',
        'dataset',
        'summary',
        'created_by',
    ];

    protected $casts = [
        'spec' => 'array',
        'dataset' => 'array',
    ];

    /**
     * The dataset rows the summary/follow-up must be computed from (R32.1,
     * R32.2). Always a list of associative row arrays, even when empty.
     *
     * @return list<array<string, mixed>>
     */
    public function datasetRows(): array
    {
        $rows = $this->dataset;

        return is_array($rows) ? array_values($rows) : [];
    }
}
