<?php

namespace Rosetta\Http\Controllers;

use Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Rosetta\Services\Notifications\NotificationsServiceInterface;

class NotificationsController extends Controller
{
    protected $notificationsService;
    public function __construct(NotificationsServiceInterface $notificationsService)
    {
        $this->notificationsService = $notificationsService;
    }
    public function markAsRead(Request $request, $notificationId)
    {
        $this->notificationsService->markAsRead($notificationId, Auth::user());
    }
    public function markAllAsRead(Request $request)
    {
        $this->notificationsService->markAllAsRead(Auth::user());
    }
    public function sendNotification(Request $request, $companyId)
    {
        $this->notificationsService->send(Auth::user(), $request->all(), $companyId);
    }
}
