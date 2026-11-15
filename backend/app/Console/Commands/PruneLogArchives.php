<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Logging\LogArchiver;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('logs:prune')]
#[Description('Delete archived logs older than the retention period (never less than 365 days)')]
class PruneLogArchives extends Command
{
    public function handle(LogArchiver $archiver): int
    {
        $this->info('Deleted '.$archiver->prune().' archive file(s) past retention ('.config('logging.archive.retention_days').' days).');

        return self::SUCCESS;
    }
}
