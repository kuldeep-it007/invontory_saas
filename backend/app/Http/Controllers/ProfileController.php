<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * The signed-in user's own profile picture.
 *
 *   • POST   /api/me/avatar  — upload (multipart field `avatar`)
 *   • DELETE /api/me/avatar  — remove
 *
 * Any authenticated tenant user may change only their OWN picture; no other
 * ability is required (mirrors GET /me). The browser crops/resizes the image
 * before upload, but the server never trusts that: it checks the real file
 * content is a JPEG/PNG/WebP image within size and dimension limits, then
 * stores it as a data URL on the user row (SVG is rejected — it can carry
 * script).
 */
class ProfileController extends Controller
{
    /** Hard cap on the stored image (bytes). The client normally sends ~10–40 KB. */
    private const MAX_BYTES = 512 * 1024;

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => [
                'required',
                'file',
                // `mimes` checks the detected content type, not the filename.
                'mimes:jpeg,png,webp',
                'max:'.(self::MAX_BYTES / 1024),
                'dimensions:min_width=32,min_height=32,max_width=2048,max_height=2048',
            ],
        ], [
            'avatar.mimes' => 'The picture must be a JPEG, PNG or WebP image.',
            'avatar.max' => 'The picture must be smaller than 512 KB.',
            'avatar.dimensions' => 'The picture must be between 32 and 2048 pixels on each side.',
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('avatar');
        $mime = (string) $file->getMimeType();

        $user = $request->user();
        $user->forceFill([
            'avatar' => 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($file->getRealPath())),
        ])->save();

        return response()->json(['data' => ['avatar' => $user->avatar]]);
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(['avatar' => null])->save();

        return response()->json(['data' => ['avatar' => null]]);
    }
}
