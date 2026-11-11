<?php
namespace Rosetta\Services\Notifications;

use Rosetta\Models\User;

interface NotificationsServiceInterface
{
	public function markAsRead($notificationId, User $user);
	public function markAllAsRead(User $user);
	public function send(User $user, Array $params, $companyId);
}