<?php

declare(strict_types=1);

namespace App\Services\Logging;

use App\Auth\Roles;
use App\Logging\MaskSensitiveData;
use App\Models\AuditLog;
use App\Models\LogArchive;
use App\Models\PlatformAuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MediaStorage;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Ships logs to the master bucket and keeps them for the retention period.
 *
 *   tenant-logs/{tenant_id}/{tenant_name}/{Y}/{m}/{d}/app-{date}.jsonl.gz
 *   tenant-logs/{tenant_id}/{tenant_name}/{Y}/{m}/{d}/audit-{date}.jsonl.gz
 *   platform-logs/{Y}/{m}/{d}/app-{date}.jsonl.gz
 *   platform-logs/{Y}/{m}/{d}/audit-{date}.jsonl.gz
 *
 * Every file is gzip-compressed, checksummed (SHA-256) and indexed in
 * `log_archives`. Objects are never overwritten. A local file is deleted only
 * after its upload is read back and verified. Failed uploads are retried, then
 * left in place for the next run, and reported.
 */
class LogArchiver
{
    private const ATTEMPTS = 3;

    /** @var array<string, string> tenant id => slug */
    private array $slugs = [];

    public function __construct(private readonly MediaStorage $media) {}

    /**
     * Ship every finished day of local archive files.
     *
     * @return array{uploaded: int, failed: int, skipped: int}
     */
    public function shipLocalFiles(): array
    {
        $result = ['uploaded' => 0, 'failed' => 0, 'skipped' => 0];
        $base = (string) config('logging.archive.path');
        $today = $this->today()->toDateString();

        foreach ($this->localFiles($base) as [$file, $tenantId, $date]) {
            if ($date >= $today) {
                continue; // Still being written.
            }

            $outcome = $this->archiveFile(
                fn (): string => (string) file_get_contents($file),
                $tenantId,
                LogArchive::KIND_APP,
                CarbonImmutable::parse($date, $this->timezone()),
            );

            if ($outcome !== 'failed') {
                @unlink($file);
            }
            $result[$outcome === 'stored' ? 'uploaded' : $outcome]++;
        }

        return $result;
    }

    /**
     * Export one IST day of platform and tenant audit rows. Idempotent: a day
     * already archived for a scope is skipped.
     *
     * @return array{uploaded: int, failed: int, skipped: int}
     */
    public function exportAuditTables(CarbonImmutable $day): array
    {
        $result = ['uploaded' => 0, 'failed' => 0, 'skipped' => 0];
        [$from, $to] = $this->utcBounds($day);

        $outcome = $this->archiveFile(
            fn (): string => $this->rowsToJsonl(
                PlatformAuditLog::query()->whereBetween('created_at', [$from, $to])->orderBy('id')->get()->toArray()
            ),
            null,
            LogArchive::KIND_AUDIT,
            $day,
        );
        $result[$outcome === 'stored' ? 'uploaded' : $outcome]++;

        foreach (Tenant::query()->orderBy('id')->cursor() as $tenant) {
            $tenantId = (string) $tenant->getTenantKey();
            if ($this->alreadyArchived($tenantId, LogArchive::KIND_AUDIT, $day)) {
                $result['skipped']++;

                continue;
            }

            try {
                $rows = $tenant->run(fn (): array => Schema::hasTable('audit_logs')
                    ? AuditLog::query()->whereBetween('created_at', [$from, $to])->orderBy('id')->get()->toArray()
                    : []);
            } catch (Throwable $e) {
                report($e);
                $result['failed']++;

                continue;
            }

            $outcome = $this->archiveFile(fn (): string => $this->rowsToJsonl($rows), $tenantId, LogArchive::KIND_AUDIT, $day);
            $result[$outcome === 'stored' ? 'uploaded' : $outcome]++;
        }

        return $result;
    }

    /**
     * Delete archives older than the retention period (never less than 365 days).
     */
    public function prune(): int
    {
        $cutoff = $this->today()->subDays((int) config('logging.archive.retention_days'))->toDateString();
        $disk = $this->disk();
        $deleted = 0;

        LogArchive::query()->where('log_date', '<', $cutoff)->orderBy('id')->each(function (LogArchive $archive) use ($disk, &$deleted): void {
            try {
                $disk->delete($archive->path);
                LogArchive::$allowRetentionDelete = true;
                $archive->delete();
                $deleted++;
            } catch (Throwable $e) {
                report($e);
            } finally {
                LogArchive::$allowRetentionDelete = false;
            }
        });

        if ($deleted > 0) {
            PlatformAuditLog::record('logs.pruned', null, null, [], ['files' => $deleted, 'older_than' => $cutoff]);
        }

        return $deleted;
    }

    /**
     * The bucket path for one archive file. Public for tests and docs.
     */
    public function pathFor(?string $tenantId, string $kind, CarbonImmutable $day): string
    {
        $dated = $day->format('Y/m/d');
        $file = sprintf('%s-%s.jsonl.gz', $kind, $day->toDateString());

        if ($tenantId === null) {
            return sprintf('%s/%s/%s', config('logging.archive.platform_prefix'), $dated, $file);
        }

        return sprintf('%s/%s/%s/%s/%s', config('logging.archive.tenant_prefix'), $tenantId, $this->slugFor($tenantId), $dated, $file);
    }

    public function disk(): Filesystem
    {
        return $this->media->platformDisk();
    }

    /**
     * @param  callable(): string  $contents
     * @return 'stored'|'skipped'|'failed'
     */
    private function archiveFile(callable $contents, ?string $tenantId, string $kind, CarbonImmutable $day): string
    {
        if ($this->alreadyArchived($tenantId, $kind, $day)) {
            return 'skipped';
        }

        $raw = $contents();
        $path = $this->pathFor($tenantId, $kind, $day);
        $gz = (string) gzencode($raw, 6);
        $hash = hash('sha256', $gz);
        $disk = $this->disk();

        if ($disk->exists($path)) {
            // Never overwrite an archived object; keep both if they differ.
            if (hash('sha256', (string) $disk->get($path)) !== $hash) {
                $path = preg_replace('/\.jsonl\.gz$/', '-'.Str::lower(Str::random(6)).'.jsonl.gz', $path);
            }
        }

        for ($attempt = 1; $attempt <= self::ATTEMPTS; $attempt++) {
            try {
                if (! $disk->exists($path)) {
                    $disk->put($path, $gz, ['ContentType' => 'application/gzip']);
                }

                if (hash('sha256', (string) $disk->get($path)) !== $hash) {
                    throw new RuntimeException('Uploaded archive did not match the local file.');
                }

                LogArchive::query()->firstOrCreate(['path' => $path], [
                    'scope' => $tenantId === null ? LogArchive::SCOPE_PLATFORM : LogArchive::SCOPE_TENANT,
                    'tenant_id' => $tenantId,
                    'tenant_slug' => $tenantId === null ? null : $this->slugFor($tenantId),
                    'kind' => $kind,
                    'log_date' => $day->toDateString(),
                    'size' => strlen($gz),
                    'sha256' => $hash,
                    'lines' => $raw === '' ? 0 : substr_count($raw, "\n"),
                ]);

                return 'stored';
            } catch (Throwable $e) {
                if ($attempt === self::ATTEMPTS) {
                    $this->reportFailure($path, $e);

                    return 'failed';
                }
                Sleep::for(2 ** $attempt)->seconds();
            }
        }

        return 'failed';
    }

    private function alreadyArchived(?string $tenantId, string $kind, CarbonImmutable $day): bool
    {
        return LogArchive::query()
            ->where('scope', $tenantId === null ? LogArchive::SCOPE_PLATFORM : LogArchive::SCOPE_TENANT)
            ->where('tenant_id', $tenantId)
            ->where('kind', $kind)
            ->whereDate('log_date', $day->toDateString())
            ->exists();
    }

    /**
     * @return iterable<array{0: string, 1: string|null, 2: string}> [file, tenant id, Y-m-d]
     */
    private function localFiles(string $base): iterable
    {
        foreach (glob($base.'/platform/*.jsonl') ?: [] as $file) {
            yield [$file, null, basename($file, '.jsonl')];
        }

        foreach (glob($base.'/tenants/*/*.jsonl') ?: [] as $file) {
            $tenantId = basename(dirname($file));
            if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $tenantId) === 1) {
                yield [$file, $tenantId, basename($file, '.jsonl')];
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function rowsToJsonl(array $rows): string
    {
        $masker = new MaskSensitiveData;
        $out = '';
        foreach ($rows as $row) {
            $out .= json_encode($masker->maskArray($row), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)."\n";
        }

        return $out;
    }

    /** Folder-safe tenant name: company name, else subdomain, else "tenant". */
    private function slugFor(string $tenantId): string
    {
        if (! isset($this->slugs[$tenantId])) {
            $tenant = Tenant::query()->find($tenantId);
            $name = $tenant?->company_name ?: ($tenant?->subdomain ?: 'tenant');
            $this->slugs[$tenantId] = Str::limit(Str::slug((string) $name), 60, '') ?: 'tenant';
        }

        return $this->slugs[$tenantId];
    }

    private function reportFailure(string $path, Throwable $e): void
    {
        Log::error('logs.archive_failed', ['event' => 'logs.archive_failed', 'path' => $path, 'error' => mb_substr($e->getMessage(), 0, 300)]);

        try {
            PlatformAuditLog::record('logs.archive_failed', null, null, [], ['path' => $path, 'error' => mb_substr($e->getMessage(), 0, 300)]);
        } catch (Throwable) {
            // The log line above still records it.
        }

        $to = config('logging.archive.alert_email') ?: $this->platformAdminEmails();
        if ($to !== [] && $to !== '' && $to !== null) {
            try {
                Mail::raw("A log archive upload failed and will be retried on the next run.\n\nFile: {$path}\nError: ".mb_substr($e->getMessage(), 0, 300), function ($message) use ($to): void {
                    $message->to($to)->subject('[Inventory Platform] Log archive upload failed');
                });
            } catch (Throwable) {
                // Mail outages must not stop the archive run.
            }
        }
    }

    /**
     * Fallback alert recipients when LOG_ALERT_EMAIL is not set: every Platform_Admin.
     *
     * @return list<string>
     */
    private function platformAdminEmails(): array
    {
        try {
            return User::role(Roles::PLATFORM_ADMIN)->pluck('email')->filter()->values()->all();
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} UTC bounds of an IST day. */
    private function utcBounds(CarbonImmutable $day): array
    {
        $start = $day->setTimezone($this->timezone())->startOfDay();

        return [$start->utc(), $start->endOfDay()->utc()];
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone());
    }

    private function timezone(): string
    {
        return (string) config('logging.archive.timezone', 'Asia/Kolkata');
    }
}
