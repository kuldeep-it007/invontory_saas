<?php
namespace Rosetta\Services\AdvanceSalesOrders;

use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as SMSNotification;
use Predis\Connection\ConnectionException;
use Rosetta\Events\Notifications;
use Rosetta\Exceptions\Order\ViewOrderPermissionDeniedException;
use Rosetta\Exceptions\RedisException;
use Rosetta\Mail\AdvanceOrderDelete;
use Rosetta\Mail\AdvanceOrderDeleteDistributor;
use Rosetta\Models\AdvanceBlanketOrder;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\Log;
use Rosetta\Models\Notification;
use Rosetta\Models\User;
use Rosetta\Notifications\SMS\AdvanceOrderCancelledNotification;
use Rosetta\Services\AdvanceOrders\AdvanceOrdersServiceInterface;
use Rosetta\Services\AdvanceSalesOrders\AdvanceSalesOrdersServiceInterface;
use Rosetta\Services\NAV\SalesOrderServiceInterface;

class AdvanceSalesOrdersService implements AdvanceSalesOrdersServiceInterface
{
	protected $salesOrderService, $advanceOrdersService;
	public function __construct(SalesOrderServiceInterface $salesOrderService, AdvanceOrdersServiceInterface $advanceOrdersService)
	{
		$this->advanceOrdersService = $advanceOrdersService;
		$this->salesOrderService = $salesOrderService;
	}
	public function findOrder(User $loggedInUser, $orderId)
	{
		$blanketOrder = AdvanceBlanketOrder::find($orderId);
		$alert = '';
		$advanceOrders = $blanketOrder->advance_orders;
		foreach($advanceOrders as $advanceOrder)
		{
			if($advanceOrder->distributor_id == null)
			{
				list($advanceOrder,$alert) = $this->advanceOrdersService->update($advanceOrder->orderable->company, $advanceOrder->orderable, $advanceOrder->id);
			}
			else
			{
				$relatedBO = AdvanceBlanketOrder::where('related_ao_id','=',$advanceOrder->id)->first();
				if($relatedBO)
				{
					$relatedAO = $relatedBO->advance_orders()->first();
					if($relatedAO->navision_order_number)
					{
						list($relatedAO,$alert) = $this->advanceOrdersService->update($relatedAO->orderable->company, $relatedAO->orderable, $relatedAO->id);
					}
				}
				$alert = '';//how do we handle this at blanket order level check #248
			}
		}
		if($loggedInUser->hasRole('distributorAdmin'))
        {
            $distributorId = $loggedInUser->distributor_id;
            if($blanketOrder->distributor_id == $distributorId)
            	return $blanketOrder;
        }
		throw new ViewOrderPermissionDeniedException;
	}
	public function delete($distributorId, $orderId, $reason, User $loggedInUser)
	{
		$toEmailIds = [];
		$recipients = [];
		$sender = $loggedInUser;
		$distributor = Distributor::find($distributorId);
		$company = $distributor->company;
		$blanketOrder = AdvanceBlanketOrder::find($orderId);
		$orderAmount = $blanketOrder->amount;
		$dealer = Dealer::find($blanketOrder->orderable_id);
		$overdue = $blanketOrder->overdue;
		$orderShipped = $blanketOrder->shipped;
		$nav_order_no = null;
		$blanketOrderNo = $blanketOrder->order_number;
		$advanceOrders = $blanketOrder->advance_orders;

		foreach ($advanceOrders as $advanceOrder)
		{
			if($advanceOrder->stock_point_id != null)//order placed by non-Dud Dealer or Distributor with Company
			{
				$nav_order_no = $advanceOrder->navision_order_number;
				$advanceLines = $advanceOrder->advance_lines()->get();
				$advanceLineNo = $advanceLines[0]->nav_line_no;
				$status = $this->salesOrderService->delete($company, $advanceOrder->navision_order_number, $advanceOrder->status, $advanceLineNo);
				
				if($status)
				{
					$advanceOrder->status = 'Cancelled';
					$advanceOrder->cancelation_reason = $reason;
					$advanceOrder->canceled_by = $loggedInUser->id;
					$advanceOrder->save();
				}
			}
			else//Dud Order
			{
				$advanceOrder->status = 'Cancelled';
				$advanceOrder->cancelation_reason = $reason;
				$advanceOrder->canceled_by = $loggedInUser->id;
				$advanceOrder->save();

				$relatedBlanketOrder = AdvanceBlanketOrder::where('related_ao_id', '=', $advanceOrder->id)->first();
				if($relatedBlanketOrder)
				{
					$relatedBlanketOrder->cancelation_reason = $reason;
					$relatedBlanketOrder->canceled_by = $loggedInUser->id;
					$relatedBlanketOrder->save();

					$company_aos = $relatedBlanketOrder->advance_orders()->get();
					foreach($company_aos as $company_ao)
					{
						if($company_ao->status != 'Cancelled')
						{
							if($company_ao->stock_point_id != null)//company AO
							{
								$nav_order_no = $company_ao->navision_order_number;
								$advanceLines = $company_ao->advance_lines()->get();
								$advanceLineNo = $advanceLines[0]->nav_line_no;
								$status = $this->salesOrderService->delete($company, $company_ao->navision_order_number, $company_ao->status, $advanceLineNo);
								if($status)
								{
									$company_ao->status = 'Cancelled';
									$company_ao->cancelation_reason = $reason;
									$company_ao->canceled_by = $loggedInUser->id;
									$company_ao->save();
									

									$query = User::where('company_id','=',$company->id);
									$query->whereHas('roles',function($q) {
											$q->where('name','=','companyAdmin');
										});
									$users = $query->get();

									foreach ($users as $user) {
										$recipients[] = $user;
										if($user->communication_email)
											$toEmailIds[] = $user->communication_email;
									}//add Company Admin Email Ids

									$superAdminEmailQuery = User::where('distributor_id','=',null)
												->where('dealer_id','=',null)
												->where('super_stockist_id','=',null)
												->where('company_id','=',null);
									$superAdminEmailQuery->whereHas('roles', function($q){
											$q->where('name', '=', 'superadmin');
										});
									$superAdminEmailIds = $superAdminEmailQuery->get();
									foreach ($superAdminEmailIds as $user) {
										if($user->communication_email)
											$toEmailIds[] = $user->communication_email;
										$recipients[] = $user;
									}//add Super Admin Email Ids

									$q = User::where('distributor_id','=',$distributor->id);
									$q->whereHas('roles',function($q1) {
											$q1->where('name','=','distributorAdmin');
										});
									$sender = $q->first();

									foreach ($recipients as $recipient) 
									{
										$notification = new Notification;
										$notification->title = 'Advance Order Cancelled!';
										$notification->text = 'Distributor has cancelled ' .$relatedBlanketOrder->order_number. '. Reason: '. $reason;
										$notification->sender_id = $sender->id;
										$notification->recipient_id = $recipient->id;
										$notification->author()->associate($sender);
										$notification->sender()->associate($sender);
										$notification->recipient()->associate($recipient);
										$notification->save();
										event(new Notifications($notification));
									}
									$queuePrefix = config('brand.queue_prefix');
									$queue = $queuePrefix.'.emails';
									$Qmessage = (new AdvanceOrderDelete($relatedBlanketOrder))->onQueue($queue);
									try
								    {
								    	if(!empty($toEmailIds))
							        		Mail::to($toEmailIds)->queue($Qmessage);
							       	}
							       	catch(ConnectionException $e)
							       	{
							       		throw new RedisException;
								    }
								}
							}
						}//company_ao Cancelled
					}
				}
			}
		}
		$blanketOrder->cancelation_reason = $reason;
		$blanketOrder->canceled_by = $loggedInUser->id;
		$blanketOrder->save();
		
		if(!$overdue)
		{
			if($orderShipped == true)//100 %ADVC
			{
				$dealer->overdue_balance -= $orderAmount;
				$dealer->save();
			}
		}
		else
		{
			if($orderAmount >= $overdue->amount)//assuming some payment is done in between, which might not be possible as we have added a check on UI to prevent delete if any payment is done against order
			{
				$customer->overdue_balance += ($orderAmount - $overdue->amount);//chk if required
			}
			$overdue->delete();
		}


		$query = User::where('dealer_id','=',$dealer->id);
		$query->whereHas('roles',function($q) {
				$q->where('name','=','dealerAdmin');
			});
		$users = $query->get();
		$customer_user = $query->first();
		
		foreach ($users as $user) {
			if($user->communication_email)
				$toCustomerEmailIds[] = $user->communication_email;
			$customerRecipients[] = $user;
		}//add Customer Admin Email Ids

		$queuePrefix = config('brand.queue_prefix');
		$queue = $queuePrefix.'.emails';
		$Qmessage = (new AdvanceOrderDeleteDistributor($blanketOrder))->onQueue($queue);
		try
	    {
	    	if(!empty($toCustomerEmailIds))
        		Mail::to($toCustomerEmailIds)->queue($Qmessage);
       	}
       	catch(ConnectionException $e)
       	{
       		throw new RedisException;
	    }
	    foreach ($customerRecipients as $recipient) 
		{
			$notification = new Notification;
			$notification->title = 'Advance Order Cancelled!';
			$notification->text = 'Distributor has cancelled ' .$blanketOrderNo. '. Reason: '. $reason;
			$notification->sender_id = $sender->id;
			$notification->recipient_id = $recipient->id;
			$notification->author()->associate($sender);
			$notification->sender()->associate($sender);
			$notification->recipient()->associate($recipient);
			$notification->save();
			event(new Notifications($notification));
		}
		$notifiable = (object)[
            "order_number" => $blanketOrder->order_number,
            "number" => $customer_user->phone,
            "customer" => $dealer->name
        ];
        SMSNotification::send($notifiable,new AdvanceOrderCancelledNotification($company));//#595
		return $blanketOrder;
	}
}