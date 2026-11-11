<?php
namespace Rosetta\Services\Notifications;

use Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Rosetta\Events\Notifications;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\Notification;
use Rosetta\Models\User;
use Rosetta\Services\Notifications\NotificationsServiceInterface;
use Rosetta\Services\Users\UserServiceInterface;

class NotificationsService implements NotificationsServiceInterface
{
	protected $userService;
	public function __construct(UserServiceInterface $userService)
	{
	    $this->userService = $userService;
	}
	public function markAsRead($notificationId, User $user)
	{
		$notification = Notification::where('id', '=', $notificationId)->get()->first();
		$notification->read = 1;
		$notification->save();
	}
	public function markAllAsRead(User $user)
	{
		$notifications = $user->notifications()->where('read', '=', 0)->get();
		foreach ($notifications as $notification)
		{
			$notification->read = 1;
			$notification->save();
		}
	}
	public function send(User $loggedInUser, Array $params, $companyId)
	{
		$recipients = [];
		$company = Company::find($companyId);
		/*if($loggedInUser->hasRole('superadmin'))
        {
            $users = $this->userService->all($request->all());
        }
        else if($loggedInUser->hasRole('companyAdmin') || $loggedInUser->hasRole('companySalesRep'))
        {
            $companyId = $loggedInUser->company_id;
            $users = $this->userService->all([], $companyId, "company");
        }
        dd($users);*/
		if($params['sendTo'] == "allCustomers")
		{
			$distributors = $company->distributors()->get();
			$dealers = $company->dealers()->get();
			foreach ($distributors as $distributor) {
				$users = User::where('distributor_id', '=', $distributor->id)->get();
				foreach ($users as $user)
				{
					$recipients[] = $user;
				}
			}
			foreach ($dealers as $dealer) {
				$users = User::where('dealer_id', '=', $dealer->id)->get();
				foreach ($users as $user)
				{
					$recipients[] = $user;
				}
			}			
		}
		if($params['sendTo'] == "allDistributors")
		{
			$distributors = $company->distributors()->get();
			foreach ($distributors as $distributor) {
				$users = User::where('distributor_id', '=', $distributor->id)->get();
				foreach ($users as $user)
				{
					$recipients[] = $user;
				}
			}
		}
		if($params['sendTo'] == "allDealers")
		{
			$dealers = $company->dealers()->get();
			foreach ($dealers as $dealer) {
				$users = User::where('dealer_id', '=', $dealer->id)->get();
				foreach ($users as $user)
				{
					$recipients[] = $user;
				}
			}
		}
		if($params['sendTo'] == "selectedCustomers")
		{
			$distributors = [];
			$dealers = [];
			$customers = $params['selectedCustomers'];
			foreach ($customers as $customer) {
				if($customer['type'] == 'Distributor')
					$distributors[] = $customer;
				if($customer['type'] == 'Dealer')
					$dealers[] = $customer;
			}
			foreach ($distributors as $distributor) {
				$users = User::where('distributor_id', '=', $distributor['id'])->get();
				foreach ($users as $user)
				{
					$recipients[] = $user;
				}
			}
			foreach ($dealers as $dealer) {
				$users = User::where('dealer_id', '=', $dealer['id'])->get();
				foreach ($users as $user)
				{
					$recipients[] = $user;
				}
			}		
		}
		foreach ($recipients as $recipient) 
		{
			$notification = new Notification;
			$notification->title = $params['notification_title'];
			$notification->text = $params['notification_text'];
			$notification->sender_id = $loggedInUser->id;
			$notification->recipient_id = $recipient->id;
			$notification->author()->associate($loggedInUser);
			$notification->sender()->associate($loggedInUser);
			$notification->recipient()->associate($recipient);
			$notification->save();
			event(new Notifications($notification));
		}
	}
}