<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantAuditable;
use App\Reports\ExportFormat;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Tenant-owned scheduled / subscription report (tenant database
 * `report_schedules` table — R29.6).
 *
 * When a schedule is due, the Report_Engine runs its report defaulting the date
 * range to the previous calendar month and emails the result to the subscribed
 * recipients (R29.6). The stored spec deliberately carries NO date range so
 * every run inherits the previous-calendar-month default from the shared query
 * layer (R29.2).
 *
 * Isolation is physical, so there is no `tenant_id` column. The model is
 * {@see TenantAuditable} so creating/updating a subscription is recorded in the
 * tenant `audit_logs` table (R34.1).
 *
 * @property int $id
 * @property string|null $name
 * @property string $report_type
 * @property string $format
 * @property array<string, mixed> $spec
 * @property list<string> $recipients
 * @property string $cadence
 * @property bool $active
 * @property Carbon|null $next_run_at
 * @property Carbon|null $last_run_at
 * @property int|null $created_by
 */
class ReportSchedule extends Model implements Auditable
{
    use TenantAuditable;

    /** Supported cadences for a scheduled report. */
    public const CADENCE_DAILY = 'daily';

    public const CADENCE_WEEKLY = 'weekly';

    public const CADENCE_MONTHLY = 'monthly';

    /** @var list<string> */
    public const CADENCES = [self::CADENCE_DAILY, self::CADENCE_WEEKLY, self::CADENCE_MONTHLY];

    protected $table = 'report_schedules';

    protected $fillable = [
        'name',
        'report_type',
        'format',
        'spec',
        'recipients',
        'cadence',
        'active',
        'next_run_at',
        'last_run_at',
        'created_by',
    ];

    protected $casts = [
        'spec' => 'array',
        'recipients' => 'array',
        'active' => 'boolean',
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
    ];

    /**
     * The export format emailed to recipients (R29.4).
     */
    public function format(): ExportFormat
    {
        return ExportFormat::from($this->format);
    }

    /**
     * Advance {@see $next_run_at} by one cadence period from the given
     * reference (default: the current next_run_at or now). Used after a
     * schedule runs so it becomes due again at the next period boundary.
     */
    public function advanceSchedule(?CarbonImmutable $from = null): CarbonImmutable
    {
        $base = $from
            ?? ($this->next_run_at ? CarbonImmutable::instance($this->next_run_at) : CarbonImmutable::now());

        return match ($this->cadence) {
            self::CADENCE_DAILY => $base->addDay(),
            self::CADENCE_WEEKLY => $base->addWeek(),
            default => $base->addMonthNoOverflow(),
        };
    }
}
