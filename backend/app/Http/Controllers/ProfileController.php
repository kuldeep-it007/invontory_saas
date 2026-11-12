<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in user's own profile picture.
 *
 *   • POST   /api/me/avatar  — upload (multipart field `avatar`)
 *   • DELETE /api/me/avatar  — remove
 *
 * Any authenticated tenant user may change only their OWN picture; no other
 * ability is required (mirrors GET /me). The browser crops/resizes the image
 * before upload, but the server never trusts that: it checks the real file
 * content is a JPEG/PNG/WebP image within size and dimension limits (SVG is
 * rejected — it can carry script). The file goes to the uploads disk (Oracle
 * Object Storage); the user row keeps only its URL.
 */
class ProfileController extends Controller
{
    /** Hard cap on the uploaded image (KB). The client normally sends ~10–40 KB. */
    private const MAX_KB = 512;

    public function __construct(private readonly MediaStorage $media) {}

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => [
                'required',
                'file',
                // `mimes` checks the detected content type, not the filename.
                'mimes:jpeg,png,webp',
                'max:'.self::MAX_KB,
                'dimensions:min_width=32,min_height=32,max_width=2048,max_height=2048',
                // The bytes themselves must be an accepted image.
                $this->media->contentRule(['jpg', 'png', 'webp'], 'The picture must be a JPEG, PNG or WebP image.'),
            ],
        ], [
            'avatar.mimes' => 'The picture must be a JPEG, PNG or WebP image.',
            'avatar.max' => 'The picture must be smaller than 512 KB.',
            'avatar.dimensions' => 'The picture must be between 32 and 2048 pixels on each side.',
        ]);

        $user = $request->user();
        $previous = $user->avatar;

        $user->forceFill(['avatar' => $this->media->store($request->file('avatar'), 'avatar')])->save();
        // Old pictures stored inline (data: URLs) need no cleanup; deleteUrl ignores them.
        $this->media->deleteUrl($previous);

        return response()->json(['data' => ['avatar' => $user->avatar]]);
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();
        $previous = $user->avatar;

        $user->forceFill(['avatar' => null])->save();
        $this->media->deleteUrl($previous);

        return response()->json(['data' => ['avatar' => null]]);
    }
}
