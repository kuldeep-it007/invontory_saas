<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\MediaStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET /api/media/{path} — serve an uploaded file (logo, favicon, profile
 * picture) from the private uploads bucket.
 *
 * Public on purpose: the login page shows the company's logo and favicon
 * before anyone signs in. Safety comes from three checks — the path must have
 * our exact shape (no traversal, known kinds/extensions only), it must belong
 * to the tenant of the domain being visited, and the name is a 32-character
 * random string that cannot be guessed.
 */
class MediaController extends Controller
{
    public function __construct(private readonly MediaStorage $media) {}

    public function show(string $path): StreamedResponse
    {
        if (! $this->media->isValidPath($path) || ! $this->media->belongsToActiveTenant($path)) {
            abort(404);
        }

        $disk = $this->media->disk();

        if (! $disk->exists($path)) {
            abort(404);
        }

        $stream = $disk->readStream($path);

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $this->media->contentType($path),
            // Names are random and never reused, so the file never changes.
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
        ]);
    }
}
