<?php

declare(strict_types=1);

namespace App\Services\Logging;

use App\Models\LogArchive;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;
use ZipArchive;

/**
 * Finds, searches and exports archived log files for external audits.
 * Bounded on purpose (files, bytes, matches) so one request can't exhaust the server.
 */
class LogArchiveSearch
{
    public const MAX_MATCHES = 1000;

    public const MAX_FILES = 400;

    public const MAX_BYTES = 300 * 1024 * 1024;

    public function __construct(private readonly LogArchiver $archiver) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<LogArchive>
     */
    public function query(array $filters): Builder
    {
        return LogArchive::query()
            ->when(($filters['scope'] ?? null) !== null, fn (Builder $q) => $q->where('scope', $filters['scope']))
            ->when(($filters['tenant_id'] ?? null) !== null, fn (Builder $q) => $q->where('tenant_id', $filters['tenant_id']))
            ->when(($filters['kind'] ?? null) !== null, fn (Builder $q) => $q->where('kind', $filters['kind']))
            ->whereDate('log_date', '>=', $filters['from'])
            ->whereDate('log_date', '<=', $filters['to'])
            ->orderBy('log_date')
            ->orderBy('id');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{matches: list<array<string, mixed>>, files_scanned: int, truncated: bool}
     */
    public function search(array $filters): array
    {
        $matches = [];
        $bytes = 0;
        $scanned = 0;
        $truncated = false;

        foreach ($this->query($filters)->limit(self::MAX_FILES)->get() as $archive) {
            $raw = gzdecode((string) $this->archiver->disk()->get($archive->path));
            if ($raw === false) {
                continue;
            }
            $scanned++;
            $bytes += strlen($raw);

            foreach (explode("\n", $raw) as $line) {
                if ($line === '' || ! $this->lineMatches($line, $filters)) {
                    continue;
                }
                $matches[] = ['file' => $archive->path, 'log_date' => $archive->log_date->toDateString(), 'entry' => json_decode($line, true) ?? $line];
                if (count($matches) >= self::MAX_MATCHES) {
                    return ['matches' => $matches, 'files_scanned' => $scanned, 'truncated' => true];
                }
            }

            if ($bytes > self::MAX_BYTES) {
                $truncated = true;
                break;
            }
        }

        return ['matches' => $matches, 'files_scanned' => $scanned, 'truncated' => $truncated];
    }

    /**
     * Zip the selected archive files plus a manifest with SHA-256 checksums, so
     * an auditor can verify nothing was changed. Returns the temp zip path.
     *
     * @param  Collection<int, LogArchive>  $archives
     */
    public function zip(Collection $archives): string
    {
        $path = tempnam(sys_get_temp_dir(), 'log-export-').'.zip';
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the export file.');
        }

        $manifest = [];
        foreach ($archives as $archive) {
            $contents = (string) $this->archiver->disk()->get($archive->path);
            $zip->addFromString($archive->path, $contents);
            $manifest[] = [
                'path' => $archive->path,
                'tenant_id' => $archive->tenant_id,
                'kind' => $archive->kind,
                'log_date' => $archive->log_date->toDateString(),
                'sha256' => $archive->sha256,
                'sha256_verified' => hash('sha256', $contents) === $archive->sha256,
                'size' => $archive->size,
            ];
        }
        $zip->addFromString('manifest.json', (string) json_encode([
            'generated_at' => now('Asia/Kolkata')->toIso8601String(),
            'files' => $manifest,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->close();

        return $path;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function lineMatches(string $line, array $filters): bool
    {
        $q = $filters['q'] ?? null;
        if (is_string($q) && $q !== '' && stripos($line, $q) === false) {
            return false;
        }

        $structured = array_filter([
            'event' => $filters['event'] ?? null,
            'user_id' => $filters['user_id'] ?? null,
            'request_id' => $filters['request_id'] ?? null,
            'level' => $filters['level'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        if ($structured === []) {
            return true;
        }

        $entry = json_decode($line, true);
        if (! is_array($entry)) {
            return false;
        }

        foreach ($structured as $field => $wanted) {
            $value = $entry[$field] ?? null;
            if ($field === 'event') {
                if (! is_string($value) || ! str_starts_with($value, (string) $wanted)) {
                    return false;
                }
            } elseif ((string) $value !== (string) $wanted && strcasecmp((string) $value, (string) $wanted) !== 0) {
                return false;
            }
        }

        return true;
    }
}
