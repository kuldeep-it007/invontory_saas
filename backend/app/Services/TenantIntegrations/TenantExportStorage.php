<?php

declare(strict_types=1);

namespace App\Services\TenantIntegrations;

use App\Exceptions\StorageUnavailableException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Where report export files live when the tenant has its own bucket.
 *
 * Keys are `tenants/<id>/exports/reports/<file>`: the same tenant prefix the
 * media files use. A file path that starts with that prefix lives in the
 * tenant bucket; any other path is a legacy file on the local disk.
 */
class TenantExportStorage
{
    /** Whether the active tenant has its own storage switched on. */
    public function active(): bool
    {
        return function_exists('tenancy')
            && tenancy()->initialized
            && config('filesystems.disks.'.IntegrationConfig::DISK) !== null;
    }

    public function disk(): Filesystem
    {
        return Storage::disk(IntegrationConfig::DISK);
    }

    /** Whether a stored export path belongs to the active tenant's bucket. */
    public function owns(?string $path): bool
    {
        return $path !== null && str_starts_with($path, 'tenants/'.tenant('id').'/') && ! str_contains($path, '..');
    }

    public static function downloadUrl(int $exportId): string
    {
        return '/api/reports/exports/'.$exportId.'/download';
    }

    /**
     * Upload a locally generated file to the tenant bucket and delete the local copy.
     *
     * @return string The bucket key.
     */
    public function publish(string $absolutePath, string $relativePath): string
    {
        $key = $this->keyFor($relativePath);

        $this->upload($absolutePath, $key);
        @unlink($absolutePath);

        return $key;
    }

    /** Copy (do not move) a local file to the tenant bucket; returns its key. */
    public function copyLocal(string $absolutePath, string $relativePath): string
    {
        $key = $this->keyFor($relativePath);

        $this->upload($absolutePath, $key);

        return $key;
    }

    private function keyFor(string $relativePath): string
    {
        return sprintf('tenants/%s/%s', tenant('id'), ltrim($relativePath, '/'));
    }

    private function upload(string $absolutePath, string $key): void
    {
        $stream = fopen($absolutePath, 'rb');

        try {
            $this->disk()->writeStream($key, $stream);
        } catch (Throwable $e) {
            report($e);
            throw new StorageUnavailableException($e);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
