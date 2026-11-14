<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ReportExport;
use App\Services\MediaStorage;
use App\Services\TenantIntegrations\IntegrationConfig;
use App\Services\TenantIntegrations\TenantExportStorage;
use App\Services\TenantIntegrations\TenantIntegrationStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * One-off copy of a tenant's existing files into its own bucket.
 *
 * Copies (never moves) so nothing is lost if the new bucket misbehaves:
 *   - uploaded media (`tenants/<id>/...`) from the platform uploads disk, under
 *     the same keys, so every stored `/api/media/...` URL keeps working;
 *   - completed report exports from the tenant's local disk, re-pointing the
 *     export row at the bucket copy and the download endpoint.
 *
 * Safe to run again: objects already in the bucket are skipped. Progress is
 * written to the tenant's storage settings for the UI. Runs inside the tenant
 * (the queue bootstrapper restores tenancy), so the new disk is already active.
 */
class CopyTenantStorage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function handle(TenantIntegrationStore $store, MediaStorage $media, TenantExportStorage $exports): void
    {
        $tenantId = (string) tenant('id');
        $progress = ['status' => 'running', 'copied' => 0, 'skipped' => 0, 'failed' => 0, 'started_at' => now()->toIso8601String()];
        $store->patch(TenantIntegrationStore::STORAGE, $tenantId, ['copy' => $progress]);

        try {
            $target = Storage::disk(IntegrationConfig::DISK);
            $source = $media->platformDisk();

            foreach ($source->allFiles('tenants/'.$tenantId) as $path) {
                if (! $media->isValidPath($path)) {
                    continue;
                }
                $this->copyObject($path, $source, $target, $media, $progress);
            }

            $this->copyExports($exports, $progress);

            $progress['status'] = $progress['failed'] > 0 ? 'completed_with_errors' : 'completed';
        } catch (Throwable $e) {
            report($e);
            $progress['status'] = 'failed';
            $progress['error'] = 'The copy stopped early. Check the storage settings and try saving again.';
        }

        $progress['finished_at'] = now()->toIso8601String();
        $store->patch(TenantIntegrationStore::STORAGE, $tenantId, ['copy' => $progress]);
    }

    /**
     * @param  array<string, mixed>  $progress
     */
    private function copyObject(string $path, mixed $source, mixed $target, MediaStorage $media, array &$progress): void
    {
        try {
            if ($target->exists($path)) {
                $progress['skipped']++;

                return;
            }

            $stream = $source->readStream($path);
            $target->writeStream($path, $stream, [
                'ContentType' => $media->contentType($path),
                'CacheControl' => 'public, max-age=31536000, immutable',
            ]);
            if (is_resource($stream)) {
                fclose($stream);
            }
            $progress['copied']++;
        } catch (Throwable $e) {
            report($e);
            $progress['failed']++;
        }
    }

    /**
     * @param  array<string, mixed>  $progress
     */
    private function copyExports(TenantExportStorage $exports, array &$progress): void
    {
        $local = Storage::disk('local');

        ReportExport::query()
            ->whereNotNull('file_path')
            ->where('file_path', 'not like', 'tenants/%')
            ->orderBy('id')
            ->each(function (ReportExport $export) use ($exports, $local, &$progress): void {
                try {
                    if (! $local->exists($export->file_path)) {
                        return;
                    }

                    $key = $exports->copyLocal($local->path($export->file_path), $export->file_path);
                    $export->update(['file_path' => $key, 'download_url' => TenantExportStorage::downloadUrl($export->id)]);
                    $progress['copied']++;
                } catch (Throwable $e) {
                    report($e);
                    $progress['failed']++;
                }
            });
    }
}
