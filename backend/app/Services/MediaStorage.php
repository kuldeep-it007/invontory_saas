<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores user-uploaded files (logos, favicons, profile pictures) on the
 * configured uploads disk — Oracle Object Storage in production — and turns
 * them into URLs the browser can load.
 *
 * Layout in the bucket: `tenants/<tenant id>/<kind>/<random>.<ext>`.
 *   • Every file is namespaced by tenant, so tenants never share objects.
 *   • Names are random and never reused, so a file can be cached forever and
 *     a URL cannot be guessed from another.
 *
 * The bucket stays PRIVATE: the browser never talks to object storage and the
 * access keys never leave the server. Files are served by GET /api/media/...,
 * which streams them from the bucket (see MediaController). The stored URL is
 * same-origin (`/api/media/...`), so it works on every tenant domain.
 */
class MediaStorage
{
    /** Public URL prefix that MediaController serves. */
    public const URL_PREFIX = '/api/media/';

    /** Allowed file kinds (also the folder names). */
    public const KINDS = ['logo', 'favicon', 'avatar'];

    /** Extensions we store and serve, with the Content-Type to serve them as. */
    public const TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
    ];

    public function disk(): Filesystem
    {
        return Storage::disk((string) config('filesystems.uploads_disk', 'uploads_local'));
    }

    /**
     * Store an already-validated upload for the active tenant and return the
     * URL to save on the record (e.g. the `logo` setting).
     */
    public function store(UploadedFile $file, string $kind): string
    {
        $ext = $this->extensionFor($file);
        $path = sprintf('tenants/%s/%s/%s.%s', $this->tenantId(), $kind, Str::random(32), $ext);

        $this->disk()->put($path, (string) file_get_contents($file->getRealPath()), [
            'ContentType' => self::TYPES[$ext],
            'CacheControl' => 'public, max-age=31536000, immutable',
        ]);

        return self::URL_PREFIX.$path;
    }

    /**
     * Delete a previously stored file, given the URL saved on the record. URLs
     * that are not ours (an external logo URL, an inline data: URL, null) and
     * files belonging to another tenant are ignored.
     */
    public function deleteUrl(?string $url): void
    {
        $path = $this->pathFromUrl($url);

        if ($path === null || ! $this->belongsToActiveTenant($path)) {
            return;
        }

        try {
            $this->disk()->delete($path);
        } catch (\Throwable $e) {
            // An orphaned object is harmless; never fail the user's request.
            report($e);
        }
    }

    /** The storage path inside one of our URLs, or null if the URL is not ours. */
    public function pathFromUrl(?string $url): ?string
    {
        if ($url === null || ! str_starts_with($url, self::URL_PREFIX)) {
            return null;
        }

        $path = substr($url, strlen(self::URL_PREFIX));

        return $this->isValidPath($path) ? $path : null;
    }

    /**
     * Whether a path has exactly our shape — no "..", no other folders, a known
     * kind and extension. Anything else is rejected before touching storage.
     */
    public function isValidPath(string $path): bool
    {
        $kinds = implode('|', self::KINDS);
        $exts = implode('|', array_keys(self::TYPES));

        return preg_match('#^tenants/[A-Za-z0-9_-]{1,64}/('.$kinds.')/[A-Za-z0-9]{32}\.('.$exts.')$#', $path) === 1;
    }

    public function belongsToActiveTenant(string $path): bool
    {
        return str_starts_with($path, 'tenants/'.$this->tenantId().'/');
    }

    public function contentType(string $path): string
    {
        return self::TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
    }

    /**
     * The extension we would store this file as, decided by sniffing its BYTES
     * (never the filename or the browser-sent type), or null if it is not one
     * of the image types we accept. SVG is never accepted.
     */
    public function detectExtension(mixed $file): ?string
    {
        if (! $file instanceof UploadedFile || ! is_file((string) $file->getRealPath())) {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file->getRealPath());

        return match ($mime) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/x-icon', 'image/vnd.microsoft.icon' => 'ico',
            default => null,
        };
    }

    /**
     * A validation rule: the file's real content must be one of $extensions
     * (as returned by detectExtension). Pair it with `mimes` / `max` etc.
     *
     * @param  list<string>  $extensions
     */
    public function contentRule(array $extensions, string $message): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($extensions, $message): void {
            if (! in_array($this->detectExtension($value), $extensions, true)) {
                $fail($message);
            }
        };
    }

    private function extensionFor(UploadedFile $file): string
    {
        // Controllers validate with contentRule() first; this is a safety net.
        return $this->detectExtension($file)
            ?? throw new \InvalidArgumentException('Unsupported file type.');
    }

    private function tenantId(): string
    {
        $id = function_exists('tenant') ? tenant('id') : null;

        if ($id === null || $id === '') {
            throw new \RuntimeException('Uploads require an active tenant.');
        }

        return (string) $id;
    }
}
