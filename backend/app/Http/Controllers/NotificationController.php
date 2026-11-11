<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * In-app notification centre endpoints (R27.6).
 *
 * Thin controller: resolves the authenticated tenant user and delegates every
 * business rule to {@see NotificationService}. All actions run in the
 * authenticated tenant pipeline against the active tenant database (physical
 * isolation) and are scoped to the caller's OWN notifications — a user only
 * ever lists, reads, and marks-all-read their own history (R27.6).
 */
class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * GET /api/notifications — the caller's paginated notification history,
     * newest-first, with the current unread count (R27.6). Paginated and run
     * against the active tenant database.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->getKey();
        $perPage = (int) $request->integer('per_page', 15);

        $notifications = $this->notifications->list($userId, $perPage);

        return response()->json([
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'last_page' => $notifications->lastPage(),
                'unread_count' => $this->notifications->unreadCount($userId),
            ],
        ]);
    }

    /**
     * POST /api/notifications/{notification}/read — mark a SINGLE notification
     * read for the caller (R27.6). Scoped to the caller's own notifications; a
     * notification belonging to another user is not found (404).
     */
    public function read(Request $request, int $notification): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        $marked = $this->notifications->markRead($userId, $notification);

        return response()->json([
            'data' => $marked->only(['id', 'type', 'title', 'body', 'data', 'read_at']),
            'meta' => ['unread_count' => $this->notifications->unreadCount($userId)],
        ]);
    }

    /**
     * POST /api/notifications/read-all — mark ALL of the caller's unread
     * notifications read, leaving zero unread (R27.6).
     */
    public function readAll(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        $marked = $this->notifications->markAllRead($userId);

        return response()->json([
            'data' => ['marked_read' => $marked],
            'meta' => ['unread_count' => $this->notifications->unreadCount($userId)],
        ]);
    }
}
