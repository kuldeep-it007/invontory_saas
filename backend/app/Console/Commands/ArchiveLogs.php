<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Logging\LogArchiver;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('logs:archive {--days=7 : Also export audit tables for this many past days that are not archived yet}')]
#[Description('Ship finished log files and daily audit-table exports to the master bucket (idempotent; safe to run repeatedly)')]
class ArchiveLogs extends Command
{
    public function handle(LogArchiver $archiver): int
    {
        $files = $archiver->shipLocalFiles();
        $this->info(sprintf('Log files: %d uploaded, %d skipped, %d failed.', $files['uploaded'], $files['skipped'], $files['failed']));

        $timezone = (string) config('logging.archive.timezone', 'Asia/Kolkata');
        $failed = $files['failed'];
        $days = max(1, (int) $this->option('days'));

        for ($back = 1; $back <= $days; $back++) {
            $day = CarbonImmutable::now($timezone)->subDays($back)->startOfDay();
            $audit = $archiver->exportAuditTables($day);
            $failed += $audit['failed'];
            if ($audit['uploaded'] > 0 || $audit['failed'] > 0) {
                $this->info(sprintf('Audit export %s: %d uploaded, %d skipped, %d failed.', $day->toDateString(), $audit['uploaded'], $audit['skipped'], $audit['failed']));
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
