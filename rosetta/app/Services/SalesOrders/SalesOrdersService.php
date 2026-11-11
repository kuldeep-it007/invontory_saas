<?php
namespace Rosetta\Services\SalesOrders;

use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as SMSNotification;
use Predis\Connection\ConnectionException;
use Rosetta\Events\Notifications;
use Rosetta\Exceptions\Order\DeleteLineException;
use Rosetta\Exceptions\Order\ViewOrderPermissionDeniedException;
use Rosetta\Exceptions\RedisException;
use Rosetta\Mail\PaymentAdded;
use Rosetta\Mail\PurchaseLineDelete;
use Rosetta\Mail\PurchaseLineDeleteDistributor;
use Rosetta\Mail\PurchaseLineDeleteDud;
use Rosetta\Mail\PurchaseLineUpdate;
use Rosetta\Mail\PurchaseLineUpdateCoSuperStockist;
use Rosetta\Mail\PurchaseLineUpdateDud;
use Rosetta\Mail\PurchaseOrderDelete;
use Rosetta\Mail\PurchaseOrderDeleteDistributor;
use Rosetta\Mail\ReturnApproved;
use Rosetta\Mail\ReturnRejected;
use Rosetta\Mail\ReturnRequest;
use Rosetta\Mail\SalesOrderDelete;
use Rosetta\Models\BlanketOrder;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\Log;
use Rosetta\Models\Notification;
use Rosetta\Models\OverdueAmount;
use Rosetta\Models\Payment;
use Rosetta\Models\Product\Stock;
use Rosetta\Models\PurchaseOrder\PurchaseLine;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
use Rosetta\Models\ReturnItem;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Models\User;
use Rosetta\Notifications\SMS\CoDistributorOrderCancelledNotification;
use Rosetta\Notifications\SMS\PurchaseOrderCancelledNotification;
use Rosetta\Notifications\SMS\PurchaseOrderUpdatedNotification;
use Rosetta\Services\NAV\SalesOrderServiceInterface;
use Rosetta\Services\PurchaseOrders\PurchaseOrdersServiceInterface;

class SalesOrdersService implements SalesOrdersServiceInterface
{
	protected $purchaseOrdersService, $salesOrderService;
	public function __construct(PurchaseOrdersServiceInterface $purchaseOrdersService, SalesOrderServiceInterface $salesOrderService)
	{
		$this->purchaseOrdersService = $purchaseOrdersService;
		$this->salesOrderService = $salesOrderService;
	}
	public function all($distributorId, Array $params = [])
	{
		if(array_has($params,'page'))
		{
			$purchaseOrders = PurchaseOrder::where('distributor_id', '=', $distributorId)->when($params, function ($query) use ($params){
				if(array_has($params, 'status'))
				{
					$query->where('status', '=', $params['status']);
				}
				if(array_has($params, 'start_date'))
				{
					$query->where('created_at', '>=', $params['start_date']);
				}
				if(array_has($params, 'end_date'))
				{
					$query->where('created_at', '<=', $params['end_date']);
				}
				if(array_has($params,'number'))
				{
					$query->where('order_number','like',"%{$params['number']}%");
				}
				if(array_has($params, 'customer_id'))
				{
					$query->where('orderable_type', '=', Dealer::class)->where('orderable_id', '=', $params['customer_id']);
				}
				if(array_has($params,'start_date'))
				{
			/*		$date = $params['date'];
					var_dump($date);
					exit(0);*/
					$query->where('created_at', '>=', $params['start_date']);
					$query->where('created_at', '<=', $params['end_date']);
				}
			})->orderBy('created_at','desc')->paginate($params['per_page']);
		}
		else
		{
			$purchaseOrders = PurchaseOrder::where('distributor_id', '=', $distributorId)->when($params, function ($query) use ($params){
				if(array_has($params, 'status'))
				{
					$query->where('status', '=', $params['status']);
				}
				if(array_has($params, 'start_date'))
				{
					$query->where('created_at', '>=', $params['start_date']);
				}
				if(array_has($params, 'end_date'))
				{
					$query->where('created_at', '<=', $params['end_date']);
				}
				if(array_has($params, 'customer_id'))
				{
					$query->where('orderable_type', '=', Dealer::class)->where('orderable_id', '=', $params['customer_id']);
				}
				if(array_has($params,'start_date'))
				{
					$query->where('created_at', '>=', $params['start_date']);
					$query->where('created_at', '<=', $params['end_date']);
				}
			})->orderBy('created_at','desc')->get();
		}
		return $purchaseOrders;
	}
	public function findOrder(User $loggedInUser, $orderId)
	{		
		$blanketOrder = BlanketOrder::find($orderId);
		$alert = '';
		$purchaseOrders = $blanketOrder->purchase_orders;
		foreach($purchaseOrders as $purchaseOrder)
		{
			if($purchaseOrder->distributor_id == null && $purchaseOrder->super_stockist_id == null && $purchaseOrder->orderable_type != 'Rosetta\Models\SuperStockist\SuperStockist')
			{
				list($purchaseOrder,$alert) = $this->purchaseOrdersService->update($purchaseOrder->orderable->company, $purchaseOrder->orderable, $purchaseOrder->id);
			}
			else
			{
				$relatedBO = BlanketOrder::where('related_po_id','=',$purchaseOrder->id)->first();
				if($relatedBO)
				{
					$relatedPO = $relatedBO->purchase_orders()->first();
					if(!empty($relatedPO->navision_order_number))
					{
						list($relatedPO,$alert) = $this->purchaseOrdersService->update($relatedPO->orderable->company, $relatedPO->orderable, $relatedPO->id);
					}
				}
				$alert = '';//how do we handle this at blanket order level check #248
			}
		}
		if($loggedInUser->hasRole('distributorAdmin'))
        {
			$distributorId = $loggedInUser->distributor_id;
			$purchaseOrder = $blanketOrder->whereHas('purchase_orders', function ($q) use ($distributorId, $blanketOrder) { $q->where('distributor_id', '=', $distributorId)->where('blanket_order_id', '=', $blanketOrder->id); })->get();
            
            if($blanketOrder->distributor_id == $distributorId)
            	return $blanketOrder;
			if(!empty($purchaseOrder))
            	return $blanketOrder;
        }
		if($loggedInUser->hasRole('superStockistAdmin'))
        {
            $superStockistId = $loggedInUser->super_stockist_id;
			$purchaseOrder = $blanketOrder->whereHas('purchase_orders', function ($q) use ($superStockistId, $blanketOrder) { $q->where('super_stockist_id', '=', $superStockistId)->where('blanket_order_id', '=', $blanketOrder->id); })->get();
            if($blanketOrder->super_stockist_id == $superStockistId){
				return $blanketOrder;
			}

			if(!empty($purchaseOrder)){
            	return $blanketOrder;
			}
            	return $blanketOrder;
        }
		throw new ViewOrderPermissionDeniedException;
	}
	public function delete($distributorId, $orderId, $reason, User $loggedInUser)
	{
		$toEmailIds = [];
		$recipients = [];
		$sender = $loggedInUser;


		$blanketOrder = BlanketOrder::find($orderId);
		if ($blanketOrder->orderable_type == 'Rosetta\Models\Distributor' && ($distributorId == "null")) {
			$distributorId = $blanketOrder->orderable_id;
		}
		$distributor = Distributor::find($distributorId);
		//$company = $distributor->company;
		$company = Company::find($blanketOrder->company_id);
		//$purchaseOrder = PurchaseOrder::find($orderId);

		$orderAmount = $blanketOrder->amount;
		$dealer = Dealer::find($blanketOrder->orderable_id);
		$overdue = $blanketOrder->overdue;
		$orderShipped = $blanketOrder->shipped;
		$blanketOrder->order_status = 'Cancelled';
		$nav_order_no = null;
		$blanketOrderNo = $blanketOrder->order_number;
		$purchaseOrders = $blanketOrder->purchase_orders;
		
		//dd($blanketOrder->id);
		foreach ($purchaseOrders as $purchaseOrder) {
			$superStockistPurchaseOrdersId = (!empty($purchaseOrder->ss_stock_point_id))?$purchaseOrder->ss_stock_point_id:$blanketOrder->super_stockist_id;
			$distributorPurchaseOrdersId = (!empty($purchaseOrder->dist_stock_point_id))?$purchaseOrder->dist_stock_point_id:$blanketOrder->distributor_id;
			if ($purchaseOrder->stock_point_id != null) //order placed by non-Dud Dealer or Distributor with Company
			{
				$nav_order_no = $purchaseOrder->navision_order_number;
				$purchaseLines = $purchaseOrder->purchase_lines()->get();
				$purchaseLineNo = $purchaseLines[0]->nav_line_no;
				$status = $this->salesOrderService->delete($company, $purchaseOrder->navision_order_number, $purchaseOrder->status, $purchaseLineNo);

				if ($status) {
					$purchaseOrder->status = 'Cancelled';
					$purchaseOrder->cancelation_reason = $reason;
					$purchaseOrder->canceled_by = $loggedInUser->id;
					$purchaseOrder->save();
				}
			} else //Dud Order to Distributor
			{
				$purchaseOrder->status = 'Cancelled';
				$purchaseOrder->cancelation_reason = $reason;
				$purchaseOrder->canceled_by = $loggedInUser->id;
				$purchaseOrder->save();

				$relatedBO = BlanketOrder::where('related_po_id', '=', $purchaseOrder->id)->first(); //Dist to Comp or Dist to SS Order
				if ($relatedBO) {
					$relatedBO->cancelation_reason = $reason;
					$relatedBO->canceled_by = $loggedInUser->id;
					$relatedBO->save();

					$relatedPO = $relatedBO->purchase_orders()->first();
					if ($relatedPO->stock_point_id != null && $relatedPO->navision_order_number != null) //Dist to Comp Order
					{
						$nav_order_no = $relatedPO->navision_order_number;
						$purchaseLines = $relatedPO->purchase_lines()->get();
						$purchaseLineNo = $purchaseLines[0]->nav_line_no;
						$status = $this->salesOrderService->delete($company, $relatedPO->navision_order_number, $relatedPO->status, $purchaseLineNo);

						if ($status) {
							$relatedPO->status = 'Cancelled';
							$relatedPO->cancelation_reason = $reason;
							$relatedPO->canceled_by = $loggedInUser->id;
							$relatedPO->save();

							$query = User::where('company_id', '=', $company->id);
							$query->whereHas('roles', function ($q) {
								$q->where('name', '=', 'companyAdmin');
							});
							$users = $query->get();

							foreach ($users as $user) {
								$recipients[] = $user;
								if ($user->communication_email)
									$toEmailIds[] = $user->communication_email;
							} //add Company Admin Email Ids

							$superAdminEmailQuery = User::where('distributor_id', '=', null)
								->where('dealer_id', '=', null)
								->where('super_stockist_id', '=', null)
								->where('company_id', '=', null);
							$superAdminEmailQuery->whereHas('roles', function ($q) {
								$q->where('name', '=', 'superadmin');
							});
							$superAdminEmailIds = $superAdminEmailQuery->get();
							foreach ($superAdminEmailIds as $user) {
								if ($user->communication_email)
									$toEmailIds[] = $user->communication_email;
								$recipients[] = $user;
							} //add Super Admin Email Ids

							$q = User::where('distributor_id', '=', $distributor->id);
							$q->whereHas('roles', function ($q1) {
								$q1->where('name', '=', 'distributorAdmin');
							});
							$sender = $q->first();

							foreach ($recipients as $recipient) {
								$notification = new Notification;
								$notification->title = 'Purchase Order Cancelled!';
								$notification->text = 'Distributor has cancelled ' . $relatedBO->order_number . '. Reason: ' . $reason;
								$notification->sender_id = $sender->id;
								$notification->recipient_id = $recipient->id;
								$notification->author()->associate($sender);
								$notification->sender()->associate($sender);
								$notification->recipient()->associate($recipient);
								$notification->save();
								event(new Notifications($notification));
							}
							$queuePrefix = config('brand.queue_prefix');
							$queue = $queuePrefix . '.emails';
							$Qmessage = (new PurchaseOrderDelete($relatedBO))->onQueue($queue);
							try {
								// if(!empty($toEmailIds))
								// 	Mail::to($toEmailIds)->queue($Qmessage);
							} catch (ConnectionException $e) {
								throw new RedisException;
							}
						}
					} else //distributor to SS Order
					{
						$relatedPO->status = 'Cancelled';
						$relatedPO->cancelation_reason = $reason;
						$relatedPO->canceled_by = $loggedInUser->id;
						$relatedPO->save();

						$superRelatedBO = BlanketOrder::where('related_po_id', '=', $relatedPO->id)->first();
						if ($superRelatedBO) //SS to Co-SS (Co-SS Stock) or SS-Comp (Comp Stock) Order
						{
							$superRelatedPO = $superRelatedBO->purchase_orders()->first();
							if ($superRelatedPO->stock_point_id != null && $superRelatedPO->navision_order_number != null) //SS to Comp
							{
								$nav_order_no = $superRelatedPO->navision_order_number;
								$purchaseLines = $superRelatedPO->purchase_lines()->get();
								$purchaseLineNo = $purchaseLines[0]->nav_line_no;
								$status = $this->salesOrderService->delete($company, $superRelatedPO->navision_order_number, $superRelatedPO->status, $purchaseLineNo);

								if ($status) {
									$superRelatedPO->status = 'Cancelled';
									$superRelatedPO->cancelation_reason = $reason;
									$superRelatedPO->canceled_by = $loggedInUser->id;
									$superRelatedPO->save();
								}
							} else //SS to Co-SS
							{
								$superRelatedPO->status = 'Cancelled';
								$superRelatedPO->cancelation_reason = $reason;
								$superRelatedPO->canceled_by = $loggedInUser->id;
								$superRelatedPO->save();

								$superRelatedPO_lines = $superRelatedPO->purchase_lines;
								$coSuperStockistId = $superRelatedPO->super_stockist_id;
								$coSuperStockist = SuperStockist::find($coSuperStockistId);

								foreach ($superRelatedPO_lines as $purchaseLine) {
									$coSuperStockistStock = $coSuperStockist->stocks
										->where('product_id', '=', $purchaseLine->product_id)
										->where('batch_no', '=', $purchaseLine->batch_no)
										->first();

									if (!$coSuperStockistStock) {
										$stock = new Stock;
										$stock->product_id = $purchaseLine->product_id;
										$stock->batch_no = $purchaseLine->batch_no;
										$stock->quantity = $purchaseLine->quantity;
										$coSuperStockist->stocks()->save($stock);
									} else {
										$coSuperStockistStock->quantity += $purchaseLine->quantity;
										$coSuperStockistStock->save();
									}
								}
							}
							$superRelatedBO->cancelation_reason = $reason;
							$superRelatedBO->canceled_by = $loggedInUser->id;
							$superRelatedBO->save();
						} else // SS Stock
						{
							$relatedPO_lines = $relatedPO->purchase_lines;
							$superStockistId = $relatedPO->super_stockist_id;
							$superStockist = SuperStockist::find($superStockistId);

							foreach ($relatedPO_lines as $purchaseLine) {
								$superStockistStock = $superStockist->stocks
									->where('product_id', '=', $purchaseLine->product_id)
									->where('batch_no', '=', $purchaseLine->batch_no)
									->first();

								if (!$superStockistStock) {
									$stock = new Stock;
									$stock->product_id = $purchaseLine->product_id;
									$stock->batch_no = $purchaseLine->batch_no;
									$stock->quantity = $purchaseLine->quantity;
									$superStockist->stocks()->save($stock);
								} else {
									$superStockistStock->quantity += $purchaseLine->quantity;
									$superStockistStock->save();
								}
							}
							$query = User::where('super_stockist_id', '=', $superStockistId);
							$query->whereHas('roles', function ($q) {
								$q->where('name', '=', 'superStockistAdmin');
							});
							$users = $query->get();
							$superStockist_user = $users->first();
							foreach ($users as $user) {
								$recipients[] = $user;
								if ($user->communication_email)
									$toSuperStockistEmailIds[] = $user->communication_email;
								$superStockistRecipients[] = $user;
							} //add Super Stockist Admin Email Ids

							$q = User::where('distributor_id', '=', $distributor->id);
							$q->whereHas('roles', function ($q1) {
								$q1->where('name', '=', 'distributorAdmin');
							});
							$sender = $q->first();
							foreach ($superStockistRecipients as $recipient) {
								$notification = new Notification;
								$notification->title = 'Sales Order Cancelled!';
								$notification->text = 'Distributor has cancelled ' . $relatedBO->order_number . '. Reason: ' . $reason;
								$notification->sender_id = $sender->id;
								$notification->recipient_id = $recipient->id;
								$notification->author()->associate($sender);
								$notification->sender()->associate($sender);
								$notification->recipient()->associate($recipient);
								$notification->save();
								event(new Notifications($notification));
							}
							$queuePrefix = config('brand.queue_prefix');
							$queue = $queuePrefix . '.emails';
							$Qmessage = (new SalesOrderDelete($relatedBO))->onQueue($queue);
							try {
								// if(!empty($toSuperStockistEmailIds))
								// 	Mail::to($toSuperStockistEmailIds)->queue($Qmessage);
							} catch (ConnectionException $e) {
								throw new RedisException;
							}
						}
					}
				} elseif ($distributorPurchaseOrdersId > 0 && ($purchaseOrder->navision_order_number == null)) {
					$relatedPO_lines = $purchaseOrder->purchase_lines;
					$stockistDistributorId = ($purchaseOrder->dist_stock_point_id > 0) ? $purchaseOrder->dist_stock_point_id : $purchaseOrder->distributor_id;
					$stockistDistributor = Distributor::find($stockistDistributorId);

					foreach ($relatedPO_lines as $purchaseLine) {
						$stockistDistributorStock = $stockistDistributor->stocks
							->where('product_id', '=', $purchaseLine->product_id)
							->where('batch_no', '=', $purchaseLine->batch_no)
							->first();

						if (!$stockistDistributorStock) {
							$stock = new Stock;
							$stock->product_id = $purchaseLine->product_id;
							$stock->batch_no = $purchaseLine->batch_no;
							$stock->quantity = $purchaseLine->quantity;
							$stockistDistributor->stocks()->save($stock);
						} else {
							$stockistDistributorStock->quantity += $purchaseLine->quantity;
							$stockistDistributorStock->save();
						}
					}
					/*
					$q = User::where('distributor_id','=',$distributor->id);
					$q->whereHas('roles',function($q1) {
							$q1->where('name','=','distributorAdmin');
						});
					$sender = $q->first();
					foreach ($superStockistRecipients as $recipient) 
					{
						$notification = new Notification;
						$notification->title = 'Sales Order Cancelled!';
						$notification->text = 'Distributor has cancelled ' .$blanketOrder->order_number. '. Reason: '. $reason;
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
					$Qmessage = (new SalesOrderDelete($blanketOrder))->onQueue($queue);
					try
					{
						if(!empty($toSuperStockistEmailIds))
							Mail::to($toSuperStockistEmailIds)->queue($Qmessage);
					   }
					   catch(ConnectionException $e)
					   {
						   throw new RedisException;
					}*/
				} elseif ($superStockistPurchaseOrdersId > 0 && ($purchaseOrder->navision_order_number == null)) {
					$relatedPO_lines = $purchaseOrder->purchase_lines;
					$superStockistId = $purchaseOrder->super_stockist_id;
					$superStockist = SuperStockist::find($superStockistId);

					foreach ($relatedPO_lines as $purchaseLine) {
						$superStockistStock = $superStockist->stocks
							->where('product_id', '=', $purchaseLine->product_id)
							->where('batch_no', '=', $purchaseLine->batch_no)
							->first();

						if (!$superStockistStock) {
							$stock = new Stock;
							$stock->product_id = $purchaseLine->product_id;
							$stock->batch_no = $purchaseLine->batch_no;
							$stock->quantity = $purchaseLine->quantity;
							$superStockist->stocks()->save($stock);
						} else {
							$superStockistStock->quantity += $purchaseLine->quantity;
							$superStockistStock->save();
						}
					}
					$query = User::where('super_stockist_id', '=', $superStockistId);
					$query->whereHas('roles', function ($q) {
						$q->where('name', '=', 'superStockistAdmin');
					});
					$users = $query->get();
					$superStockist_user = $users->first();
					foreach ($users as $user) {
						$recipients[] = $user;
						if ($user->communication_email)
							$toSuperStockistEmailIds[] = $user->communication_email;
						$superStockistRecipients[] = $user;
					} //add Super Stockist Admin Email Ids

					if(!empty($distributor->id)){
						$q = User::where('distributor_id', '=', $distributor->id);
						$q->whereHas('roles', function ($q1) {
							$q1->where('name', '=', 'distributorAdmin');
						});
						$sender = $q->first();
						foreach ($superStockistRecipients as $recipient) {
							$notification = new Notification;
							$notification->title = 'Sales Order Cancelled!';
							$notification->text = 'Distributor has cancelled ' . $blanketOrder->order_number . '. Reason: ' . $reason;
							$notification->sender_id = $sender->id;
							$notification->recipient_id = $recipient->id;
							$notification->author()->associate($sender);
							$notification->sender()->associate($sender);
							$notification->recipient()->associate($recipient);
							$notification->save();
							event(new Notifications($notification));
						}
					}					
					$queuePrefix = config('brand.queue_prefix');
					$queue = $queuePrefix . '.emails';
					$Qmessage = (new SalesOrderDelete($blanketOrder))->onQueue($queue);
					try {
						// if(!empty($toSuperStockistEmailIds))
						// 	Mail::to($toSuperStockistEmailIds)->queue($Qmessage);
					} catch (ConnectionException $e) {
						throw new RedisException;
					}
				}
			}
		}
		$blanketOrder->cancelation_reason = $reason;
		$blanketOrder->canceled_by = $loggedInUser->id;
		$blanketOrder->save();

		if (!$overdue) {
			if ($orderShipped == true) //100 %ADVC
			{
				$dealer->overdue_balance -= $orderAmount;
				$dealer->save();
			}
		} else {
			if ($orderAmount >= $overdue->amount) //assuming some payment is done in between, which might not be possible as we have added a check on UI to prevent delete if any payment is done against order
			{
				$customer->overdue_balance += ($orderAmount - $overdue->amount); //chk if required
			}
			$overdue->delete();
		}


		$query = User::where('dealer_id', '=', $dealer->id);
		$query->whereHas('roles', function ($q) {
			$q->where('name', '=', 'dealerAdmin');
		});
		$users = $query->get();
		$customer_user = $query->first();

		foreach ($users as $user) {
			if ($user->communication_email)
				$toCustomerEmailIds[] = $user->communication_email;
			$customerRecipients[] = $user;
		} //add Customer Admin Email Ids

		$queuePrefix = config('brand.queue_prefix');
		$queue = $queuePrefix . '.emails';
		$Qmessage = (new PurchaseOrderDeleteDistributor($blanketOrder))->onQueue($queue);
		try {
			// if(!empty($toCustomerEmailIds))
			// 	Mail::to($toCustomerEmailIds)->queue($Qmessage);
		} catch (ConnectionException $e) {
			throw new RedisException;
		}
		if (!empty($customerRecipients)) {
			foreach ($customerRecipients as $recipient) {
				$notification = new Notification;
				$notification->title = 'Purchase Order Cancelled!';
				$notification->text = 'Distributor has cancelled ' . $blanketOrderNo . '. Reason: ' . $reason;
				$notification->sender_id = $sender->id;
				$notification->recipient_id = $recipient->id;
				$notification->author()->associate($sender);
				$notification->sender()->associate($sender);
				$notification->recipient()->associate($recipient);
				$notification->save();
				event(new Notifications($notification));
			}
		}
		return $blanketOrder;
	}
	public function deleteLine($distributorId, $orderId, $lineId, User $user)
	{
		$sendEmail = false;
		$toEmailIds = [];
		$toDealerEmailIds = [];
		$recipients = [];
		$dealerRecipients = [];
		$sender = $user;
		$distributorSender = $sender; 
		$data = [];
		$relatedPO_nav_order_no = null;
		$purchaseOrder = PurchaseOrder::find($orderId);//DUD Order
		$blanketOrder =  $purchaseOrder->blanket_order;//
		$purchaseLine = $blanketOrder->purchase_lines()->find($lineId);//DUD Line
		$company = $purchaseOrder->company;
		if($blanketOrder->related_po_id)//when company admin is deleting line
			$customer = $blanketOrder->relatedOrder->blanket_order->orderable;//DUD or DUS
		else//when distributor admin is deleting line
			$customer = $blanketOrder->orderable;//DUC OR DIST Under Comp or SS under Comp

		$distributor = $customer->distributor;
	
		$data['batch'] = $purchaseLine->batch_no;
		$product = $purchaseLine->product->name;
		$product_id = $purchaseLine->product->id;
		$batch_no = $purchaseLine->batch_no;
		$nav_order_no = $purchaseOrder->navision_order_number;
		$purchaseOrderNo = $purchaseOrder->order_number;
		$log = new Log;
		$log->action = 'Deleted - Purchase Line';
		$log->author()->associate(Auth::user());
		$log->ip_address = \Request::ip();

		$product_display_name = $this->getProductDisplayName($company, $customer, $purchaseLine->product->category->code, $purchaseLine->product->collection->code, $purchaseLine->product->colour->code);

		$data['product_display_name'] = $product_display_name;
		$data['orderNumber'] = $blanketOrder->order_number;
		$data['orderId'] = $blanketOrder->id;
		$quantity = $purchaseLine->quantity;
		$line_amount = $purchaseLine->amount;

		if($nav_order_no != null)//company admin deleting line
		{
			if(count($purchaseOrder->purchase_lines) == 1)//should not happen after R1.0.9 as we are not showing Dud Orders to Company....Only 1 line present in the order
			{
			//	dd("22");
				$status = $this->salesOrderService->delete($company, $nav_order_no, $purchaseOrder->status, $purchaseLine->nav_line_no);
				if($status)
				{
					if($blanketOrder->related_po_id)
					{
						$relatedPO = $blanketOrder->relatedOrder;
						$relatedPOLine = $relatedPO->purchase_lines()->where('nav_line_no','=',$purchaseLine->nav_line_no)->first();
						$relatedPOLine->delete();
						$relatedPO->delete();
						$relatedPOsBO = $relatedPO->blanket_order;
						$relatedPOsBO->load('purchase_lines');
						if(!count($relatedPOsBO->purchase_lines))
						{
							$relatedPOsBO->delete();
						}
					}
					$purchaseLine->delete();
					$purchaseOrder->delete();
					$blanketOrder->load('purchase_lines');
					if(!count($blanketOrder->purchase_lines))
					{
						$blanketOrder->delete();
					}
				}
				else
					throw new DeleteLineException;
			}
			else//Super Admin deleting Company line 
			{				
				$status = $this->salesOrderService->deleteSalesLine($company, $nav_order_no, $purchaseLine->nav_line_no, $batch_no);
				if($status)
				{
					$notification = new Notification;
					if($blanketOrder->related_po_id)
					{
						$relatedPO = $blanketOrder->relatedOrder;
						$relatedPOsBO = $relatedPO->blanket_order;//Dist to SS or DUD to Dist Order
						$relatedPOLine = $relatedPO->purchase_lines()->where('nav_line_no','=',$purchaseLine->nav_line_no)->first();
						$data['orderNumber'] = $relatedPOsBO->order_number;
						$data['orderId'] = $relatedPOsBO->id;
						$notification->title = 'Item from ' .$relatedPO->order_number. ' deleted';
						$superRelatedPO = null;
						if($relatedPOsBO->related_po_id)
						{
							$superRelatedPO = $relatedPOsBO->relatedOrder;
							$superRelatedPOsBO = $superRelatedPO->blanket_order;//DUD to Dist Order
							$superRelatedPOLine = $superRelatedPO->purchase_lines()->where('nav_line_no','=',$purchaseLine->nav_line_no)->first();

							$customer = $superRelatedPOsBO->orderable;//DUD
							$distributor = $customer->distributor;
							$data['orderNumber'] = $superRelatedPOsBO->order_number;
							$data['orderId'] = $superRelatedPOsBO->id;
							$notification->title = 'Item from ' .$superRelatedPOsBO->order_number. ' deleted';
							if($superRelatedPO)
							{
								if(count($superRelatedPO->purchase_lines) == 1)
								{
									$superRelatedPOLine->delete();
									$superRelatedPO->delete();
									$superRelatedPOsBO->delete();

									$relatedPOLine->delete();
									$relatedPO->delete();
									$relatedBO->delete();
								}
								else
								{
									$superRelatedPOLine->delete();
									$relatedPOLine->delete();
									$relatedPO->load('purchase_lines');
									if(!count($relatedPO->purchase_lines))
									{
										$superRelatedPO->delete();
										$superRelatedPOsBO->delete();
										$relatedPO->delete();
										$relatedBO->delete();
									}
									else
									{
										$superRelatedPO->Amount = $superRelatedPO->purchase_lines->sum('net_amount');//#741
										$superRelatedPO->save();
										$relatedPO->Amount = $relatedPO->purchase_lines->sum('net_amount');//#741
										$relatedPO->save();
									}

								}
							}
						}//super related order
						else
						{
							if(count($relatedPO->purchase_lines) == 1)
							{
								$relatedPOLine->delete();
								$relatedPO->delete();
								$relatedPOsBO->delete();
							}
							else
							{
								$relatedPOLine->delete();
								$relatedPO->load('purchase_lines');
								if(!count($relatedPO->purchase_lines))
								{
									$relatedPO->delete();
									$relatedPOsBO->delete();
								}
								else
								{
									$relatedPO->Amount = $relatedPO->purchase_lines->sum('net_amount');//#741
									$relatedPO->save();
								}
							}
						}
					}//related order
				}//status - nav line deleted
				
				$product_display_name = $this->getProductDisplayName($company, $customer, $purchaseLine->product->category->code, $purchaseLine->product->collection->code, $purchaseLine->product->colour->code);

				$data['product_display_name'] = $product_display_name;

				$query = User::where('distributor_id','=',$distributor->id);
				$query->whereHas('roles',function($q) {
						$q->where('name','=','distributorAdmin');
					});
				$users = $query->get();
				$distributor_user = $users->first();

				$sender = $distributor_user;
				$query = User::where('dealer_id','=',$customer->id);
				$query->whereHas('roles',function($q) {
						$q->where('name','=','dealerAdmin');
					});
				$users = $query->get();
				$dud_user = $users->first();
			
				foreach ($users as $user) {
					if($user->communication_email)
						$toDealerEmailIds[] = $user->communication_email;
					$dealerRecipients[] = $user;
				}//add Customer Admin Email Ids
	
				$queuePrefix = config('brand.queue_prefix');
				$queue = $queuePrefix.'.emails';
				$Qmessage = (new PurchaseLineDeleteDud($data))->onQueue($queue);
				try
			    {
			    	// if(!empty($toDealerEmailIds))
		        	// 	Mail::to($toDealerEmailIds)->queue($Qmessage);
		       	}
		       	catch(ConnectionException $e)
		       	{
		       		throw new RedisException;
			    }
			    foreach ($dealerRecipients as $recipient) 
				{
					$notification->text = 'Distributor has deleted Item : ' .$product_display_name. ' against Batch : ' .$batch_no;
					$notification->sender_id = $sender->id;
					$notification->recipient_id = $recipient->id;
					$notification->author()->associate($sender);
					$notification->sender()->associate($sender);
					$notification->recipient()->associate($recipient);
					$notification->save();
					event(new Notifications($notification));
				}
			}//SA deleting cmp line
			$purchaseLine->delete();
			$purchaseOrder->load('purchase_lines');
			if(!count($purchaseOrder->purchase_lines))
			{
				$purchaseOrder->delete();
				$blanketOrder->load('purchase_lines');
				if(!count($blanketOrder->purchase_lines))
				{
					$blanketOrder->delete();
				}
			}
			else
			{
				$purchaseOrder->Amount = $purchaseOrder->purchase_lines->sum('net_amount');//#741
				$purchaseOrder->save();
			}	
		}
		else//distributor admin deleting line - this will happen - My Dealer Orders - Order
		{
			$relatedBO = BlanketOrder::where('related_po_id','=',$purchaseOrder->id)->first();
			if($relatedBO)//dist to ss or dist cmp
			{
				$relatedPO = $relatedBO->purchase_orders()->first();
				$relatedPOLine = $relatedPO->purchase_lines()->where('nav_line_no','=',$purchaseLine->nav_line_no)->first();
				if($relatedPO->navision_order_number != null)//dist to comp
				{
					if(count($relatedPO->purchase_lines) == 1)
					{
						$status = $this->salesOrderService->delete($company, $relatedPO->navision_order_number, $relatedPO->status, $relatedPOLine->nav_line_no);
						if($status)
						{
							$relatedPOLine->delete();
							$relatedPO->delete();
							$relatedBO->delete();
						}
						else
							throw new DeleteLineException;
					}
					else
					{
						$relatedNavPO = $this->salesOrderService->deleteSalesLine($company, $relatedPO->navision_order_number, $relatedPOLine->nav_line_no, $batch_no);
						if($relatedNavPO)
						{
							$relatedPOLine->delete();
							$relatedPO->load('purchase_lines');
							if(!count($relatedPO->purchase_lines))
							{
								$relatedPO->delete();
								$relatedBO->delete();
							}
							else
							{
								$relatedPO->Amount = $relatedPO->purchase_lines->sum('net_amount');//#741
								$relatedPO->save();
							}
						}
						else
							throw new DeleteLineException;
					}
					$data['orderNumber'] = $relatedBO->order_number;
					$data['orderId'] = $relatedBO->id;
					$query = User::where('company_id','=',$company->id);
					$query->whereHas('roles',function($q) {
							$q->where('name','=','companyAdmin');
						});
					$users = $query->get();
			
					foreach ($users as $user) {
						$companyRecipients[] = $user;
						if($user->communication_email)
							$toCompanyEmailIds[] = $user->communication_email;
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
						$companyRecipients[] = $user;
						if($user->communication_email)
							$toCompanyEmailIds[] = $user->communication_email;
					}//add Super Admin Email Ids
					$queuePrefix = config('brand.queue_prefix');
					$queue = $queuePrefix.'.emails';
					$Qmessage = (new PurchaseLineDelete($data))->onQueue($queue);
					try
				    {
				    	// if(!empty($toCompanyEmailIds))
			        	// 	Mail::to($toCompanyEmailIds)->queue($Qmessage);
			       	}
			       	catch(ConnectionException $e)
			       	{
			       		throw new RedisException;
				    }
				    foreach ($companyRecipients as $recipient) 
					{
						$notification = new Notification;
						$notification->title = 'Item from ' .$relatedBO->order_number. ' deleted';
						$notification->text = 'Distributor has deleted Item : ' .$product_display_name. ' against Batch : ' .$batch_no;
						$notification->sender_id = $sender->id;
						$notification->recipient_id = $recipient->id;
						$notification->author()->associate($sender);
						$notification->sender()->associate($sender);
						$notification->recipient()->associate($recipient);
						$notification->save();
						event(new Notifications($notification));
					}
				}
				else//dist to SS or co ss
				{
					$superRelatedBO = BlanketOrder::where('related_po_id','=',$relatedPO->id)->first();//SS BO
					if($superRelatedBO)
					{
						$superRelatedPO = $superRelatedBO->purchase_orders()->first();
						$superRelatedPOLine = $superRelatedPO->purchase_lines()->where('nav_line_no','=',$purchaseLine->nav_line_no)->first();
						if($superRelatedPO->navision_order_number != null)//SS to Comp
						{
							if(count($superRelatedPO->purchase_lines) == 1)
							{
								$status = $this->salesOrderService->delete($company, $superRelatedPO->navision_order_number, $superRelatedPO->status, $superRelatedPOLine->nav_line_no);
								if($status)
								{
									$superRelatedPOLine->delete();
									$superRelatedPO->delete();
									$superRelatedBO->delete();

									$relatedPOLine->delete();
									$relatedPO->delete();
									$relatedBO->delete();
								}
								else
									throw new DeleteLineException;
							}
							else
							{
								$relatedNavPO = $this->salesOrderService->deleteSalesLine($company, $superRelatedPO->navision_order_number, $superRelatedPOLine->nav_line_no, $batch_no);
								if($relatedNavPO)
								{
									$superRelatedPOLine->delete();
									$relatedPOLine->delete();
									$superRelatedPO->load('purchase_lines');
									if(!count($superRelatedPO->purchase_lines))
									{
										$superRelatedPO->delete();
										$superRelatedBO->delete();
										$relatedPO->delete();
										$relatedBO->delete();
									}
									else
									{
										$superRelatedPO->Amount = $superRelatedPO->purchase_lines->sum('net_amount');//#741
										$superRelatedPO->save();
										$relatedPO->Amount = $relatedPO->purchase_lines->sum('net_amount');//#741
										$relatedPO->save();
									}
								}
								else
									throw new DeleteLineException;
							}
						}
						elseif($superRelatedPO->super_stockist_id != null)//SS to Co SS
						{
							$coSuperStockist = SuperStockist::find($superRelatedPO->super_stockist_id);
							$coSuperStockistStock = $coSuperStockist->stocks
													->where('product_id', '=', $product_id)
													->where('batch_no', '=', $batch_no)
													->first();
							if(count($superRelatedPO->purchase_lines) == 1)
							{
								$superRelatedPOLine->delete();
								$superRelatedPO->delete();
								$superRelatedBO->delete();

								$relatedPOLine->delete();
								$relatedPO->delete();
								$relatedBO->delete();
							}
							else
							{
								$superRelatedPOLine->delete();
								$relatedPOLine->delete();
								$superRelatedPO->load('purchase_lines');
								if(!count($superRelatedPO->purchase_lines))
								{
									$superRelatedPO->delete();
									$superRelatedBO->delete();

									$relatedPO->delete();
									$relatedBO->delete();
								}
								else
								{
									$superRelatedPO->Amount = $superRelatedPO->purchase_lines->sum('net_amount');//#741
									$superRelatedPO->save();
									$relatedPO->Amount = $relatedPO->purchase_lines->sum('net_amount');//#741
									$relatedPO->save();
								}

								if(!$coSuperStockistStock)
								{
									$stock = new Stock;
									$stock->product_id = $product_id;
									$stock->batch_no = $batch_no;
									$stock->quantity = $quantity;
									$coSuperStockist->stocks()->save($stock);
								}
								else
								{
									$coSuperStockistStock->quantity += $quantity;
									$coSuperStockistStock->save();
								}
							}
						}
					}
					else//SS Stock
					{
						if(count($relatedPO->purchase_lines) == 1)
						{

							$relatedPOLine->delete();
							$relatedPO->delete();
							$relatedBO->delete();
						}
						else
						{
							$relatedPOLine->delete();
							$relatedPO->load('purchase_lines');
							if(!count($relatedPO->purchase_lines))
							{
								$relatedPO->delete();
								$relatedBO->delete();
							}
							else
							{
								$relatedPO->Amount = $relatedPO->purchase_lines->sum('net_amount');//#741
								$relatedPO->save();
							}
							$superStockist = SuperStockist::find($relatedPO->super_stockist_id);
							$superStockistStock = $superStockist->stocks
							->where('product_id', '=', $product_id)
							->where('batch_no', '=', $batch_no)
							->first();

							if(!$superStockistStock)
							{
								$stock = new Stock;
								$stock->product_id = $product_id;
								$stock->batch_no = $batch_no;
								$stock->quantity = $quantity;
								$superStockist->stocks()->save($stock);
							}
							else
							{
								$superStockistStock->quantity += $quantity;
								$superStockistStock->save();
							}
						}
					}
				}
			}
			$purchaseLine->delete();
			$purchaseOrder->load('purchase_lines');
			if(!count($purchaseOrder->purchase_lines))
			{
				$purchaseOrder->delete();
			}
			else
			{
				$purchaseOrder->Amount = $purchaseOrder->purchase_lines->sum('net_amount');//#741
				$purchaseOrder->save();
			}

			$data['orderNumber'] = $blanketOrder->order_number;
			$data['orderId'] = $blanketOrder->id;
			$product_display_name = $this->getProductDisplayName($company, $customer, $purchaseLine->product->category->code, $purchaseLine->product->collection->code, $purchaseLine->product->colour->code);

			$data['product_display_name'] = $product_display_name;

			$query = User::where('dealer_id','=',$customer->id);
			$query->whereHas('roles',function($q) {
					$q->where('name','=','dealerAdmin');
				});
			$users = $query->get();
			$dud_user = $users->first();

			foreach ($users as $user) {
				if($user->communication_email)
					$toDealerEmailIds[] = $user->communication_email;
				$dealerRecipients[] = $user;
			}//add Customer Admin Email Ids

			$queuePrefix = config('brand.queue_prefix');
			$queue = $queuePrefix.'.emails';
			$Qmessage = (new PurchaseLineDeleteDud($data))->onQueue($queue);
			try
		    {
		    	// if(!empty($toDealerEmailIds))
	        	// 	Mail::to($toDealerEmailIds)->queue($Qmessage);
	       	}
	       	catch(ConnectionException $e)
	       	{
	       		throw new RedisException;
		    }
		    foreach ($dealerRecipients as $recipient) 
			{
				$notification = new Notification;
				$notification->title = 'Item from ' .$blanketOrder->order_number. ' deleted';
				$notification->text = 'Distributor has deleted Item : ' .$product_display_name. ' against Batch : ' .$batch_no;
				$notification->sender_id = $sender->id;
				$notification->recipient_id = $recipient->id;
				$notification->author()->associate($sender);
				$notification->sender()->associate($sender);
				$notification->recipient()->associate($recipient);
				$notification->save();
				event(new Notifications($notification));
			}
		}
		$sendEmail = true;
		$log->comment = 'Deleted Purchase Order Line for Product: ' .$product_display_name. ' & Batch: ' .$batch_no;
		//assumption made is Line Delete is possible only before Shipment. Note: this does not take care of advance payments
		$overdue = $blanketOrder->overdue;
				
		if(!$overdue)
		{
			if($blanketOrder->shipped == true)//this scenario is not possible as we are disbaling delete Line if lines are shipped
			{
				$customer->overdue_balance -= $line_amount;
				$customer->save();
			}
		}
		else//this will happen either if payment term is ADVC or full payment is already done by user for that order
		{
			if($line_amount >= $overdue->amount)//assuming some payment is done in between, which might not be possible as we have added a check on UI to prevent delete if any payment is done against order
			{
				$customer->overdue_balance += ($line_amount - $overdue->amount);//chk if required
				$overdue->delete();
			}
			else
			{
				$overdue->amount -= $line_amount;
				$overdue->save();
			}
		}
		$log->comment = 'Administrator deleted Item : ' .$product_display_name. ' against Batch : ' .$batch_no;
		$blanketOrder->logs()->save($log);
		return $blanketOrder->fresh();
	}
	public function updateLine($distributorId, $orderId, $lineId, Array $params,  User $user)
	{
		//Distributor updates DUD Order
		$sendUnitPriceEmail = false;
		$sendQuantityEmail = false;
		$sendUnitPriceDiscountEmail = false;
		$coSuperStockist = null;
		$dealerRecipients = [];
        $purchaseOrder = PurchaseOrder::find($orderId);//DUD PO
        $purchaseLine = $purchaseOrder->purchase_lines()->find($lineId);
        $company = $purchaseOrder->company;
		$blanketOrder = $purchaseOrder->blanket_order;//DUD BO
		$customer = $blanketOrder->orderable;//Dud Customer / Dealer
		$distributor = $customer->distributor;//main Distributor
		if(!empty($customer->distributor->super_stockist_id) && $customer->distributor->super_stockist_id>0)
			$superStockist = $customer->distributor->superStockist;//Distributor's SS
		$relatedBO = BlanketOrder::where('related_po_id','=',$purchaseOrder->id)->first();//Main Dist BO
		if($relatedBO)
		{
			$relatedPO = $relatedBO->purchase_orders()->first();
			$relatedPOLine = $relatedPO->purchase_lines()->where('nav_line_no','=',$purchaseLine->nav_line_no)->first();
			$relatedPOLineCurrentDiscountedPrice = round($relatedPOLine->unit_price - ($relatedPOLine->unit_price * $relatedPOLine->discount/100),2);
			
			$superRelatedBO = BlanketOrder::where('related_po_id','=',$relatedPO->id)->first();//SS BO
			if($superRelatedBO)
			{
				$superRelatedPO = $superRelatedBO->purchase_orders()->first();
				$coSuperStockist = SuperStockist::find($superRelatedPO->super_stockist_id);
				$superRelatedPOLine = $superRelatedPO->purchase_lines()->where('nav_line_no','=',$purchaseLine->nav_line_no)->first();
				$superRelatedPOLineCurrentDiscountedPrice = round($superRelatedPOLine->unit_price - ($superRelatedPOLine->unit_price * $superRelatedPOLine->discount/100),2);
			}
		}
		$currentDiscount = $purchaseLine->discount;
		$currentUnitPrice = $purchaseLine->unit_price;
		$current_discounted_unit_price = round($currentUnitPrice - ($currentUnitPrice * $currentDiscount/100),2);
		$currentQuantity = $purchaseLine->quantity;
		$newDiscountedUnitPrice = $current_discounted_unit_price;
		
		$log = new Log;
		$log->action = 'Updated - Purchase Line';
		$log->author()->associate($user);
		$log->ip_address = \Request::ip();
		$newDiscount = $params['discount'];
		$newQuantity = $params['quantity'];
		$originalLineAmount = $purchaseLine->amount;
		
		if(array_has($params,'nav_order_no'))//Ordered from Comp Stock
			$nav_order_no = $params['nav_order_no'];
		else
			$nav_order_no = null;

		$query = User::where('dealer_id','=',$customer->id);
		$query->whereHas('roles',function($q) {
				$q->where('name','=','dealerAdmin');
			});		
		$dealerUsers = $query->get();
		$customer_user = $dealerUsers->first();
		foreach ($dealerUsers as $dealerUser) {
			if($dealerUser->communication_email)
				$toDealerEmailIds[] = $dealerUser->communication_email;
			$dealerRecipients[] = $dealerUser;
		}//add Customer (dealer under distributor) Admin Email Ids

		$sender = $user;//Main Distributor

		if($params['discount'] != $currentDiscount && $params['quantity'] != $currentQuantity)
		{
			$newDiscountedUnitPrice = round($currentUnitPrice - ($currentUnitPrice * $newDiscount/100),2);
			$newQuantity = $params['quantity'];	
		/*	if($nav_order_no != null)
			{
				$navPurchaseOrder = $this->salesOrderService->updateSalesLine($company, $purchaseOrder->nav_key, $purchaseOrder->navision_order_number, $purchaseLine->nav_line_no, $newQuantity, $newDiscountedUnitPrice, $purchaseLine->batch_no);
			}*/
			if($nav_order_no != null)//Company Stock
			{
				if($superRelatedBO != null)//SS to Comp in ccase of SS feature
				{
					$navPurchaseOrder = $this->salesOrderService->updateSalesLine($company, $superRelatedPO->nav_key, $superRelatedPO->navision_order_number, $superRelatedPOLine->nav_line_no, $newQuantity, $superRelatedPOLineCurrentDiscountedPrice, $relatedPOLine->batch_no);
				}
				else if($relatedPO != null)//Dist to Comp or SS to Comp(replenish) or DUC to Comp in case of ongoing feature
				{
					$navPurchaseOrder = $this->salesOrderService->updateSalesLine($company, $relatedPO->nav_key, $relatedPO->navision_order_number, $relatedPOLine->nav_line_no, $newQuantity, $relatedPOLineCurrentDiscountedPrice, $relatedPOLine->batch_no);
				}
			}
			if($superRelatedBO)//SS to Comp or SS to Co-SS
			{
				$superRelatedPOLine->quantity = $newQuantity;
				$superRelatedPOLine->amount = $superRelatedPOLineCurrentDiscountedPrice * $newQuantity;
				$superRelatedPOLine->tax_amount = $superRelatedPOLine->amount * $superRelatedPOLine->tax_percent/100;//#741
				$superRelatedPOLine->net_amount = $superRelatedPOLine->amount + $superRelatedPOLine->tax_amount;//#741
				$superRelatedPOLine->save();
				$relatedPO->Amount = $relatedPO->purchase_lines->sum('net_amount');//#741
				$relatedPO->save();
			}
			if($relatedBO)//can be company or Co Distributor PO
			{
				$relatedPOLine->quantity = $newQuantity;
				$relatedPOLine->amount = $relatedPOLineCurrentDiscountedPrice * $newQuantity;
				$relatedPOLine->tax_amount = $relatedPOLine->amount * $relatedPOLine->tax_percent/100;//#741
				$relatedPOLine->net_amount = $relatedPOLine->amount + $relatedPOLine->tax_amount;//#741
				$relatedPOLine->save();
				$relatedPO->Amount = $relatedPO->purchase_lines->sum('net_amount');//#741
				$relatedPO->save();
			}
			$purchaseLine->discount = $newDiscount;
			$purchaseLine->amount = $newDiscountedUnitPrice * $newQuantity;
			$purchaseLine->tax_amount = $purchaseLine->amount * $purchaseLine->tax_percent/100;//#741
			$purchaseLine->net_amount = $purchaseLine->amount + $purchaseLine->tax_amount;//#741
			$purchaseLine->quantity = $newQuantity;
			$purchaseLine->save();
			$sendUnitPriceDiscountEmail = true;
			$log->comment = 'Changed Discount from ' .$currentDiscount. ' to ' .$newDiscount. ' & Quantity from ' .$currentQuantity. ' to ' .$newQuantity;

			if($params['quantity'] < $currentQuantity)		
			{	
				if($nav_order_no == null || $nav_order_no == "")
				{
					if($relatedBO)
					{
						if($coSuperStockist)
						{
							$coSuperStockistStock = $coSuperStockist->stocks
							->where('product_id', '=', $purchaseLine->product_id)
							->where('batch_no', '=', $purchaseLine->batch_no)
							->first();

							$difference = $currentQuantity - $params['quantity'];
							if(!$coSuperStockistStock)
							{
								$stock = new Stock;
								$stock->product_id = $purchaseLine->product_id;
								$stock->batch_no = $purchaseLine->batch_no;
								$stock->quantity = $difference;
								$coSuperStockist->stocks()->save($stock);
							}
							else
							{
								$coSuperStockistStock->quantity += $difference;
								$coSuperStockistStock->save();
							}
						}
					}
					else
					{
						$superStockistStock = $superStockist->stocks
											->where('product_id', '=', $purchaseLine->product_id)
											->where('batch_no', '=', $purchaseLine->batch_no)
											->first();
						$difference = $currentQuantity - $params['quantity'];
						if(!$superStockistStock)
						{
							$stock = new Stock;
							$stock->product_id = $purchaseLine->product_id;
							$stock->batch_no = $purchaseLine->batch_no;
							$stock->quantity = $difference;
							$superStockist->stocks()->save($stock);
						}
						else
						{
							$superStockistStock->quantity += $difference;
							$superStockistStock->save();
						}
					}
					
				}
			}

		}
		else if($params['discount'] != $currentDiscount)
		{
			$newDiscountedUnitPrice = round($currentUnitPrice - ($currentUnitPrice * $newDiscount/100),2);
			$purchaseLine->discount = $newDiscount;
			$purchaseLine->amount = $newDiscountedUnitPrice * $params['quantity'];
			$purchaseLine->tax_amount = $purchaseLine->amount * $purchaseLine->tax_percent/100;//#741
			$purchaseLine->net_amount = $purchaseLine->amount + $purchaseLine->tax_amount;//#741
			$purchaseLine->save();
			$sendUnitPriceEmail = true;
			$log->comment = 'Changed Discount from ' .$currentDiscount. ' to ' .$newDiscount;
		}
		else if($params['quantity'] != $currentQuantity)
		{
			if($nav_order_no != null)//Company Stock
			{
				if($superRelatedBO != null)//SS to Comp in ccase of SS feature
				{
					$navPurchaseOrder = $this->salesOrderService->updateSalesLine($company, $superRelatedPO->nav_key, $superRelatedPO->navision_order_number, $superRelatedPOLine->nav_line_no, $newQuantity, $relatedPOLineCurrentDiscountedPrice, $relatedPOLine->batch_no);
				}
				else if($relatedPO != null)//Dist to Comp or SS to Comp(replenish) or DUC to Comp in case of ongoing feature
				{
					$navPurchaseOrder = $this->salesOrderService->updateSalesLine($company, $relatedPO->nav_key, $relatedPO->navision_order_number, $relatedPOLine->nav_line_no, $newQuantity, $relatedPOLineCurrentDiscountedPrice, $relatedPOLine->batch_no);
				}
			}
			if(!empty($superRelatedBO))//SS to Comp or SS to Co-SS
			{
				$superRelatedPOLine->quantity = $newQuantity;
				$superRelatedPOLine->amount = $superRelatedPOLineCurrentDiscountedPrice * $newQuantity;
				$superRelatedPOLine->tax_amount = $superRelatedPOLine->amount * $superRelatedPOLine->tax_percent/100;//#741
				$superRelatedPOLine->net_amount = $superRelatedPOLine->amount + $superRelatedPOLine->tax_amount;//#741
				$superRelatedPOLine->save();
				$relatedPO->Amount = $relatedPO->purchase_lines->sum('net_amount');//#741
				$relatedPO->save();
			}
			if($relatedBO)//Dist to Comp or SS to Comp or DUC to Comp
			{
				$relatedPOLine->quantity = $newQuantity;
				$relatedPOLine->amount = $relatedPOLineCurrentDiscountedPrice * $newQuantity;
				$relatedPOLine->tax_amount = $relatedPOLine->amount * $relatedPOLine->tax_percent/100;//#741
				$relatedPOLine->net_amount = $relatedPOLine->amount + $relatedPOLine->tax_amount;//#741
				$relatedPOLine->save();
				$relatedPO->Amount = $relatedPO->purchase_lines->sum('net_amount');//#741
				$relatedPO->save();
			}
			$purchaseLine->quantity = $newQuantity;
			$purchaseLine->amount = $current_discounted_unit_price * $newQuantity;
			$purchaseLine->tax_amount = $purchaseLine->amount * $purchaseLine->tax_percent/100;//#741
			$purchaseLine->net_amount = $purchaseLine->amount + $purchaseLine->tax_amount;//#741
			$purchaseLine->save();
			
			
			$sendQuantityEmail = true;
			$log->comment = 'Changed Quantity from ' .$currentQuantity. ' to ' .$newQuantity;

			if($params['quantity'] < $currentQuantity)		
			{	
				if($nav_order_no == null || $nav_order_no == "")//Not Comp Stock
				{
					//Stock for Comp Stock Points get adjusted in Navision directly oonncce order line qty is updated in Navision. However, SS and Co-SS Stock is maintained in ROS and henve below code
					if($superRelatedBO)//Co SS Stock
					{
						if($coSuperStockist)
						{
							$coSuperStockistStock = $coSuperStockist->stocks
													->where('product_id', '=', $purchaseLine->product_id)
													->where('batch_no', '=', $purchaseLine->batch_no)
													->first();

							$difference = $currentQuantity - $params['quantity'];
							if(!$coSuperStockistStock)
							{
								$stock = new Stock;
								$stock->product_id = $purchaseLine->product_id;
								$stock->batch_no = $purchaseLine->batch_no;
								$stock->quantity = $difference;
								$coSuperStockist->stocks()->save($stock);
							}
							else
							{
								$coSuperStockistStock->quantity += $difference;
								$coSuperStockistStock->save();
							}
						}
					}//SS Stock
					else
					{
						$superStockistStock = $superStockist->stocks
											->where('product_id', '=', $purchaseLine->product_id)
											->where('batch_no', '=', $purchaseLine->batch_no)
											->first();
						$difference = $currentQuantity - $params['quantity'];
						if(!$superStockistStock)
						{
							$stock = new Stock;
							$stock->product_id = $purchaseLine->product_id;
							$stock->batch_no = $purchaseLine->batch_no;
							$stock->quantity = $difference;
							$superStockist->stocks()->save($stock);
						}
						else
						{
							$superStockistStock->quantity += $difference;
							$superStockistStock->save();
						}
					}
				}
			}
		}

		$purchaseOrder = PurchaseOrder::find($orderId);
		$purchaseOrder->Amount = $purchaseOrder->purchase_lines->sum('net_amount');//#741
		$purchaseOrder->save();

		if($nav_order_no != null)
		{
			//refresh order 
			if($relatedBO)
	        {
	            if($superRelatedBO)
	            {
	                list($superRelatedPO,$alert) = $this->purchaseOrdersService->update($company, $superRelatedPO->orderable, $superRelatedPO->id);
	            }
	            else
	            {
	                list($relatedPO,$alert) = $this->purchaseOrdersService->update($company, $relatedPO->orderable, $relatedPO->id);
	            }
	        }
		}
		else
			$alert = null;

		$toEmailIds = [];
		$recipients = [];
			
		if($sendUnitPriceDiscountEmail || $sendQuantityEmail)
		{
			if($sender->hasRole('distributorAdmin'))
			{
				if($relatedBO)
				{
					$notification = new Notification;
					$data = [];
					$data['orderNumber'] = $relatedBO->order_number;
					$data['quantity'] = $currentQuantity;
					$data['current_discounted_unit_price'] = $relatedPOLineCurrentDiscountedPrice;
					$data['new_discounted_unit_price'] = $relatedPOLineCurrentDiscountedPrice;
					if($nav_order_no != null)//Comp Stock
					{
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
						$superadmin_user = $superAdminEmailQuery->first();
						foreach ($superAdminEmailIds as $user) {
							$recipients[] = $user;
							if($user->communication_email)
								$toEmailIds[] = $user->communication_email;
						}//add Super Admin Email Ids
						$product_display_name = $this->getProductDisplayName($company, $distributor, $relatedPOLine->product->category->code, $relatedPOLine->product->collection->code, $relatedPOLine->product->colour->code);
						$data['product_display_name'] = $product_display_name;
						$queuePrefix = config('brand.queue_prefix');
						$queue = $queuePrefix.'.emails';
					    $Qmessage = (new PurchaseLineUpdate($relatedPOLine, $data))->onQueue($queue);

					    $notification->title = 'Updated Quantity for ' .$relatedBO->order_number. ' item';
						$notification->text = 'Updated Quantity from ' .$currentQuantity. ' to ' .$newQuantity. ' for Item : ' .$product_display_name. ', against Batch : ' .$relatedPOLine->batch_no;	
					/*    $notifiable = (object)[
				            "order_number" => $relatedBO->order_number,
				            "number" => $superadmin_user->phone,
				            "customer" => $relatedBO->orderable->name
				        ];
				        SMSNotification::send($notifiable,new PurchaseOrderUpdatedNotification($company));//Not used as the vendor was discontinued #595 Notify Company/Super Admin*/

					}
					else//SS or Co-SS Stock
					{
						if($superRelatedBO)//Co-SS Stock
						{
							$query = User::where('super_stockist_id','=',$coSuperStockist->id);
							$query->whereHas('roles',function($q) {
									$q->where('name','=','superStockistAdmin');
								});
							$users = $query->get();
							$co_ss_user = $users->first();
							foreach ($users as $user) {
								$recipients[] = $user;
								if($user->communication_email)
									$toEmailIds[] = $user->communication_email;
							}//add Co SS Admin Email Ids
							$product_display_name = $this->getProductDisplayName($company, $coSuperStockist, $superRelatedPOLine->product->category->code, $superRelatedPOLine->product->collection->code, $superRelatedPOLine->product->colour->code);
							$data['product_display_name'] = $product_display_name;
							$queuePrefix = config('brand.queue_prefix');
							$queue = $queuePrefix.'.emails';
						    $Qmessage = (new PurchaseLineUpdateCoSuperStockist($superRelatedPOLine, $data))->onQueue($queue);

						    $notification->title = 'Updated Quantity for ' .$superRelatedBO->order_number. ' item';
							$notification->text = 'Updated Quantity from ' .$currentQuantity. ' to ' .$newQuantity. ' for Item : ' .$product_display_name. ', against Batch : ' .$superRelatedPOLine->batch_no;	
					/*	    $notifiable = (object)[
					            "order_number" => $relatedBO->order_number,
					            "number" => $co_ss_user->phone,
					            "customer" => $coSuperStockist->name
					        ];
					        SMSNotification::send($notifiable,new PurchaseOrderUpdatedNotification($company));//#595 Notify Co-Distributor Admin*/
					    }
					    else//SS Stock
						{
							$query = User::where('super_stockist_id','=',$superStockist->id);
							$query->whereHas('roles',function($q) {
									$q->where('name','=','superStockistAdmin');
								});
							$users = $query->get();
							$ss_user = $users->first();
							foreach ($users as $user) {
								$recipients[] = $user;
								if($user->communication_email)
									$toEmailIds[] = $user->communication_email;
							}//add SS Admin Email Ids
							$product_display_name = $this->getProductDisplayName($company, $superStockist, $relatedPOLine->product->category->code, $relatedPOLine->product->collection->code, $relatedPOLine->product->colour->code);
							$data['product_display_name'] = $product_display_name;
							$queuePrefix = config('brand.queue_prefix');
							$queue = $queuePrefix.'.emails';
						    $Qmessage = (new PurchaseLineUpdateCoSuperStockist($relatedPOLine, $data))->onQueue($queue);

						    $notification->title = 'Updated Quantity for ' .$relatedBO->order_number. ' item';
							$notification->text = 'Updated Quantity from ' .$currentQuantity. ' to ' .$newQuantity. ' for Item : ' .$product_display_name. ', against Batch : ' .$relatedPOLine->batch_no;	
						   /* $notifiable = (object)[
					            "order_number" => $relatedBO->order_number,
					            "number" => $ss_user->phone,
					            "customer" => $coSuperStockist->name
					        ];
					        SMSNotification::send($notifiable,new PurchaseOrderUpdatedNotification($company));//#595 Notify Co-Distributor Admin*/
					    }
					}
				    try
				    {
						// if(!empty($toEmailIds))
				    	// 	Mail::to($toEmailIds)->queue($Qmessage);
				    	foreach ($recipients as $recipient) 
						{
						//	$notification = new Notification;
						//	$notification->title = 'Updated Quantity for ' .$relatedBO->order_number. ' item';
						//	$notification->text = 'Updated Quantity from ' .$currentQuantity. ' to ' .$newQuantity. ' for Item : ' .$product_display_name. ', against Batch : ' .$relatedPOLine->batch_no;	
							$notification->sender_id = $sender->id;
							$notification->recipient_id = $recipient->id;
							$notification->author()->associate($sender);
							$notification->sender()->associate($sender);
							$notification->recipient()->associate($recipient);
							$notification->save();
							event(new Notifications($notification));
						}
				   	}
				   	catch(ConnectionException $e)
				   	{
				   		throw new RedisException;
				    }
				}//if distributor admin is updating company/co distributor line then add company/super co-distributor admins email id
			}//if($sender->hasRole('distributorAdmin'))
		}//if($sendUnitPriceDiscountEmail || $sendQuantityEmail)
		if($sendUnitPriceDiscountEmail || $sendQuantityEmail || $sendUnitPriceEmail)
		{
			$purchaseLine->logs()->save($log);
			$product_display_name = $this->getProductDisplayName($company, $customer, $purchaseLine->product->category->code, $purchaseLine->product->collection->code, $purchaseLine->product->colour->code);

			$data = [];
			$data['orderNumber'] = $blanketOrder->order_number;
			$data['quantity'] = $currentQuantity;
			$data['current_discounted_unit_price'] = $current_discounted_unit_price;
			$data['new_discounted_unit_price'] = $newDiscountedUnitPrice;
			$data['product_display_name'] = $product_display_name;
			$queuePrefix = config('brand.queue_prefix');
			$queue = $queuePrefix.'.emails';
		    $Qmessage = (new PurchaseLineUpdateDud($purchaseLine, $data))->onQueue($queue);
		    try
		    {
		    	if(!empty($toDealerEmailIds))
	        		Mail::to($toDealerEmailIds)->queue($Qmessage);
	        	foreach ($dealerRecipients as $recipient) 
				{
					$notification = new Notification;
					if($sendUnitPriceDiscountEmail)
					{
						$notification->title = 'Updated Discount and Quantity for ' .$blanketOrder->order_number. ' item';
						$notification->text = 'Updated Discount from ' .$currentDiscount. '% to ' .$newDiscount. '% and Quantity from ' .$currentQuantity. ' to ' .$newQuantity. ' for Item : ' .$product_display_name. ', against Batch : ' .$purchaseLine->batch_no;	
					}
					if($sendQuantityEmail)
					{
						$notification->title = 'Updated Quantity for ' .$blanketOrder->order_number. ' item';
						$notification->text = 'Updated Quantity from ' .$currentQuantity. ' to ' .$newQuantity. ' for Item : ' .$product_display_name. ', against Batch : ' .$purchaseLine->batch_no;	
					}
					if($sendUnitPriceEmail)
					{
						$notification->title = 'Updated Discount for ' .$blanketOrder->order_number. ' item';
						$notification->text = 'Updated Discount from ' .$currentDiscount. '% to ' .$newDiscount. '% for Item : ' .$product_display_name. ', against Batch : ' .$purchaseLine->batch_no;
					}	
					$notification->sender_id = $sender->id;
					$notification->recipient_id = $recipient->id;
					$notification->author()->associate($sender);
					$notification->sender()->associate($sender);
					$notification->recipient()->associate($recipient);
					$notification->save();
					event(new Notifications($notification));
				}
	       	}
	       	catch(ConnectionException $e)
	       	{
	       		throw new RedisException;
		    }
	    }
	 /*   $notifiable = (object)[
            "order_number" => $blanketOrder->order_number,
            "number" => $customer_user->phone,
            "customer" => $customer->name
        ];
        SMSNotification::send($notifiable,new PurchaseOrderUpdatedNotification($company));//#595 Notify Customer (DUD)*/
		
		return [$purchaseOrder,$alert];
	}
	public function addPayment($companyId, $distributorId, $orderId, Array $params, User $user)
	{
		//$purchaseorder = PurchaseOrder::find($orderId);
		$order = BlanketOrder::find($orderId);
		$dealer = $order->orderable;
		$payment = new Payment;
		$payment->amount = $params['payment_amount'];
		if(array_has($params,'payment_mode'))
			$payment->payment_mode = $params['payment_mode'];
	//	$payment->company_id = $companyId;
		$payment->distributor_id = $distributorId;
		$payment->customer_type = get_class($order->orderable) == Distributor::class ? 'DISTRIBUTO' : 'DEALER';
		$payment->customer_id = $order->orderable_id;
		$order->payments()->save($payment);
		$overdue= $order->overdue;
		if(!$overdue)
		{
			$dealer->overdue_balance -= $params['payment_amount'];
			$dealer->save();
		}
		else
		{
			if($params['payment_amount'] >= $overdue->amount)
			{
				$overdue->delete();
				$dealer->overdue_balance += ($params['payment_amount'] - $overdue->amount);
			}
			else
			{
				$overdue->amount -= $params['payment_amount'];
				$overdue->save();
			}
		}

		$toEmailIds = [];
		$recipients = [];
		$data = [];
		$sender = $user;
			
		$data['orderId'] = $order->id;
		$data['orderNumber'] = $order->order_number;
		//setlocale(LC_MONETARY, 'en_IN.UTF-8');
		//$money_format = money_format('&#x20b9;%!n', $params['payment_amount']);
		$data['amount'] = $params['payment_amount'];
		$query = User::where('dealer_id','=',$dealer->id);
		$query->whereHas('roles',function($q) {
				$q->where('name','=','dealerAdmin');
			});		
		$users = $query->get();
		
		foreach ($users as $user) {
			if($user->communication_email)
				$toEmailIds[] = $user->communication_email;
			$recipients[] = $user;
		}//add Customer (dealer under distributor) Admin Email Ids
		
		$queuePrefix = config('brand.queue_prefix');
		$queue = $queuePrefix.'.emails';
		$Qmessage = (new PaymentAdded($data))->onQueue($queue);
		try
	    {
	    	if(!empty($toEmailIds))
        		Mail::to($toEmailIds)->queue($Qmessage);
       	}
       	catch(ConnectionException $e)
       	{
       		throw new RedisException;
	    }
	    foreach ($recipients as $recipient) 
		{
			$notification = new Notification;
			$notification->title = 'Payment Added';
			$notification->text = 'Distributor added payment of Rs '.$data['amount'].' against Purchase Order ' .$order->order_number;
			$notification->sender_id = $sender->id;
			$notification->recipient_id = $recipient->id;
			$notification->author()->associate($sender);
			$notification->sender()->associate($sender);
			$notification->recipient()->associate($recipient);
			$notification->save();
			event(new Notifications($notification));
		}
	}
	public function ship($companyId, $distributorId, $orderId, Array $params, User $user)
	{
		//$order = PurchaseOrder::find($orderId);
		$order = BlanketOrder::find($orderId);
		if(array_has($params, 'invoice_id'))
			$order->invoice_id = $params['invoice_id'];
		$order->shipped = true;
		$order->save();
		$main_distributor_order = $order->main_distributor_order()->first();
		if($main_distributor_order)
		{
			$main_distributor_order->status = 'Shipped';
			$main_distributor_order->save();
			foreach($main_distributor_order->purchase_lines as $purchaseLine)
			{
				$purchaseLine->shipped_quantity = $purchaseLine->quantity;
				$purchaseLine->save();
			}
		}
		$order->refresh();
		//set overdue balance
		$dealer = $order->orderable;
		$overdue_hold_days = $dealer->overdue_hold_days;
		if($overdue_hold_days == 0)
		{
			$dealer->overdue_balance += $order->Amount;
			$dealer->save();
		}
		elseif($overdue_hold_days > 0)
		{
			$overdueAmount = new OverdueAmount;
			$overdueAmount->amount = $order->Amount;
			$overdueAmount->distributor_id = $distributorId;
			$overdueAmount->order_id = $order->id;
			$overdueAmount->overdue_date = Carbon::today()->addDays($overdue_hold_days);
			$dealer->overdue_amounts()->save($overdueAmount);
		}
		return $order;
	}
	public function addInvoice($companyId, $distributorId, $orderId, Array $params, User $user)
	{
		$order = BlanketOrder::find($orderId);
		if(array_has($params, 'invoice_id'))
		{
			$order->invoice_id = $params['invoice_id'];
			$order->save();
		}
		$order->refresh();
		return $order;
	}
	public function addReturns($distributorId, $dealerId, $lineId, $quantity, User $user)
	{
		$returnItem = new ReturnItem;
		$line = PurchaseLine::where('id', '=', $lineId)->first();
		$returnItem->product_id = $line->product_id;
		$returnItem->batch_no = $line->batch_no;
		$returnItem->quantity = $quantity;
		$returnItem->unit_price = $line->unit_price;
		$returnItem->discount = $line->discount;
		$returnItem->rate_type = $line->rate_type;

		$unitPrice = $line->unit_price;
		$discountedUnitPrice = round($line->unit_price - ($line->unit_price * $line->discount/100),2);
		$returnItem->amount = $discountedUnitPrice * $quantity;
		$returnItem->status = 'Pending';
		$returnItem->customer_type = 'DEALER';
		$returnItem->customer_id = $dealerId;
		$returnItem->distributor_id = $distributorId;
		$line->returnItems()->save($returnItem);

		$sender = $user;
		$toEmailIds = [];
		$recipients = [];
		$data = [];

		$line = $returnItem->returnable;
		$purchaseOrder = $line->purchase_order;
		$blanketOrder = $purchaseOrder->blanket_order;
		$company = $blanketOrder->company;
		$customer = $blanketOrder->orderable;
		$distributor = Distributor::find($blanketOrder->distributor_id);
		$product_display_name = $this->getProductDisplayName($company, $customer, $line->product->category->code, $line->product->collection->code, $line->product->colour->code);
		$data['orderNumber'] = $blanketOrder->order_number;
		$data['orderId'] = $blanketOrder->id;
		$data['batch_no'] = $line->batch_no;
		$data['product_display_name'] = $product_display_name;
		$data['quantity'] = $returnItem->quantity;
		$data['customerName'] = $customer->name;

		$query = User::where('distributor_id','=',$customer->distributor_id);
		$query->whereHas('roles',function($q) {
				$q->where('name','=','distributorAdmin');
			});
		$users = $query->get();
		
		foreach ($users as $user) {
			if($user->communication_email)
				$toEmailIds[] = $user->communication_email;
			$recipients[] = $user;
		}

		$queuePrefix = config('brand.queue_prefix');
		$queue = $queuePrefix.'.emails';
		$Qmessage = (new ReturnRequest($data))->onQueue($queue);
	    try
	    {
	    	if(!empty($toEmailIds))
        		Mail::to($toEmailIds)->queue($Qmessage);
        	foreach ($recipients as $recipient) 
			{
				$notification = new Notification;
				$notification->title = 'Return request for Purchase Order : ' .$blanketOrder->order_number;
				$notification->text = $customer->name. ' has placed a return request for Item : ' .$product_display_name. ', against Batch : ' .$returnItem->batch_no;		
				$notification->sender_id = $sender->id;
				$notification->recipient_id = $recipient->id;
				$notification->author()->associate($sender);
				$notification->sender()->associate($sender);
				$notification->recipient()->associate($recipient);
				$notification->save();
				event(new Notifications($notification));
			}
        }
        catch(ConnectionException $e)
       	{
       		throw new RedisException;
	    }
		return $returnItem;
	}
	public function getReturns($distributorId, Array $params)
	{
		if(array_has($params,'page'))
		{
			$returnItems = ReturnItem::where('distributor_id', '=', $distributorId)->when($params, function ($query) use ($params){
				if(array_has($params, 'status'))
				{
					$query->where('status', '=', $params['status']);
				}
				else
				{
					$query->where('status', '=', 'Pending');
				}
			/*	if(array_has($params,'number'))
				{
					$query->where('order_number','like',"%{$params['number']}%");
				}*/
				if(array_has($params, 'customer_id'))
				{
					$query->where('customer_type', '=', 'DEALER')->where('customer_id', '=', $params['customer_id']);
				}
			})->orderBy('created_at','desc')->paginate($params['per_page']);
		}
		else
		{
			$returnItems = ReturnItem::where('distributor_id', '=', $distributorId)->when($params, function ($query) use ($params){
				if(array_has($params, 'status'))
				{
					$query->where('status', '=', $params['status']);
				}
				else
				{
					$query->where('status', '=', 'Pending');
				}
				if(array_has($params, 'customer_id'))
				{
					$query->where('customer_type', '=', 'DEALER')->where('customer_id', '=', $params['customer_id']);
				}
			})->orderBy('created_at','desc')->get();
		}
		return $returnItems;
	}
	public function manageReturns($distributorId, $returnItemId, Array $params, User $user)
	{
		$sender = $user;
		$toEmailIds = [];
		$recipients = [];
		$data = [];
	
		$returnItem = ReturnItem::where('id', '=', $returnItemId)->first();

		$purchaseLine = $returnItem->returnable;
		$purchaseOrder = $purchaseLine->purchase_order;
		$blanketOrder = $purchaseOrder->blanket_order;
		$company = $blanketOrder->company;
		$customer = $blanketOrder->orderable;
		$distributor = Distributor::find($blanketOrder->distributor_id);

		$product_display_name = $this->getProductDisplayName($company, $customer, $purchaseLine->product->category->code, $purchaseLine->product->collection->code, $purchaseLine->product->colour->code);
		$data['orderNumber'] = $blanketOrder->order_number;
		$data['orderId'] = $blanketOrder->id;
		$data['batch_no'] = $purchaseLine->batch_no;
		$data['product_display_name'] = $product_display_name;
		$data['quantity'] = $returnItem->quantity;

		if($params['status'] == 'Approve')
		{
			$returnItem->status = 'Approved';
			$title = 'Return Approved for Purchase Order : ' .$blanketOrder->order_number;
			$text = 'Return Item : ' .$product_display_name. ', against Batch : ' .$returnItem->batch_no;	
		}
		else if($params['status'] == 'Reject')
		{
			$returnItem->status = 'Rejected';
			$returnItem->reject_reason = $params['reject_reason'];
			$title = 'Return Rejected for Purchase Order : ' .$blanketOrder->order_number;
			$text = 'Return Item : ' .$product_display_name. ', against Batch : ' .$returnItem->batch_no. ', Rejection reason : ' .$params['reject_reason'];	
			$data['reject_reason'] = $returnItem->reject_reason;
		}
		$returnItem->authorisation_date = Carbon::today();
		$returnItem->authorised_by = $user->id;
		$returnItem->save();

		$query = User::where('dealer_id','=',$returnItem->customer_id);
		$query->whereHas('roles',function($q) {
				$q->where('name','=','dealerAdmin');
			});
		$users = $query->get();
		
		foreach ($users as $user) {
			if($user->communication_email)
				$toEmailIds[] = $user->communication_email;
			$recipients[] = $user;
		}//add Customer Admin Email Ids
		
		$queuePrefix = config('brand.queue_prefix');
		$queue = $queuePrefix.'.emails';
		if($params['status'] == 'Approve')
		{
			$distributorStock = $distributor->stocks
								->where('product_id', '=', $purchaseLine->product_id)
								->where('batch_no', '=', $purchaseLine->batch_no)
								->first();
			if(!$distributorStock)
			{
				$stock = new Stock;
				$stock->product_id = $purchaseLine->product_id;
				$stock->batch_no = $purchaseLine->batch_no;
				$stock->quantity = $returnItem->quantity;
				$distributor->stocks()->save($stock);
			}
			else
			{
				$distributorStock->quantity += $returnItem->quantity;
				$distributorStock->save();
			}

			$newLineQty = $purchaseLine->quantity - $returnItem->quantity;
			$purchaseLine->quantity = $newLineQty;
			$purchaseLine->shipped_quantity = $newLineQty;
			$discounted_unit_price = round($purchaseLine->unit_price - ($purchaseLine->unit_price * $purchaseLine->discount/100),2);
			$newLineAmount = $discounted_unit_price * $newLineQty;
			$differenceAmount = $purchaseLine->amount - $newLineAmount;
			$purchaseLine->amount = $newLineAmount;
			$purchaseLine->save();

			$purchaseOrder->Amount -= $differenceAmount;
			$purchaseOrder->save();

			$overdue = $blanketOrder->overdue;
			if(!$overdue)
			{
				if($blanketOrder->shipped == true)//100 %ADVC
				{
					$customer->overdue_balance -= $differenceAmount;
					$customer->save();
				}
			}
			else
			{
				if($differenceAmount >= $overdue->amount)//assuming some payment is done in between and line quantity can only be reduced
				{
					$customer->overdue_balance += ($differenceAmount - $overdue->amount);
					$overdue->delete();
				}
				else
				{
					$overdue->amount -= $differenceAmount;
					$overdue->save();
				}
			}
	    	$Qmessage = (new ReturnApproved($data))->onQueue($queue);
		}
	    else if($params['status'] == 'Reject')
	    	$Qmessage = (new ReturnRejected($data))->onQueue($queue);
	    try
	    {
	    	if(!empty($toEmailIds))
        		Mail::to($toEmailIds)->queue($Qmessage);
        	foreach ($recipients as $recipient) 
			{
				$notification = new Notification;
				$notification->title = $title;
				$notification->text = $text;	
				$notification->sender_id = $sender->id;
				$notification->recipient_id = $recipient->id;
				$notification->author()->associate($sender);
				$notification->sender()->associate($sender);
				$notification->recipient()->associate($recipient);
				$notification->save();
				event(new Notifications($notification));
			}
        }
        catch(ConnectionException $e)
       	{
       		throw new RedisException;
	    }
		return $returnItem;
	}
	public function getProductDisplayName($company, $customer, $category_code, $collection_code=null, $colour_code=null)
	{
		$activeCategory = $customer->activeProductCategories()->where('category_code', '=', $category_code)->first();
		if($collection_code != null)
    	{	$activeCollection = $customer->activeProductCollections()->where('collection_code', '=', $collection_code)->first();
    	}
    	if($colour_code != null)
    	{	$activeColour = $customer->activeProductColours()->where('colour_code', '=', $colour_code)->first();
    		if(!$activeColour)
    			$activeColour = $company->activeProductColours()->where('colour_code', '=', $colour_code)->first();
    	}
    	$product_display_name = $activeCategory->category_display. ' >> '. $activeCollection->collection_display. ' >> '. $activeColour->colour_display;
    	return $product_display_name;
	}
	public function updateLineForSuperStockist($superStockistId, $orderId, $lineId, Array $params,  User $user, String $ip = null)
	{
		$purchaseOrder = PurchaseOrder::find($orderId);
		$purchaseLine = $purchaseOrder->purchase_lines()->find($lineId);
		$company = $purchaseOrder->company;
		$currentDiscount = $purchaseLine->discount;
		$currentUnitPrice = $purchaseLine->unit_price;
		$currentQuantity = $purchaseLine->quantity;
		$changedPurchaseLines = [];
		
		if($currentDiscount != $params['discount'])
		{
			$newDiscountedUnitPrice   = round($currentUnitPrice - ($currentUnitPrice * $params['discount']/100),2);
			$purchaseLine->discount   = $params['discount'];
			$purchaseLine->amount     = $newDiscountedUnitPrice * $purchaseLine->quantity;
			$purchaseLine->tax_amount = $purchaseLine->amount * $purchaseLine->tax_percent/100;
			$purchaseLine->net_amount = $purchaseLine->amount + $purchaseLine->tax_amount;
			$purchaseLine->save();
			$purchaseOrder->fresh();
			$purchaseOrder->Amount = $purchaseOrder->purchase_lines->sum('net_amount');
			$purchaseOrder->save();
		}
		
		$allAffectedOrders = $this->getRelatedOrders($purchaseOrder);

		$allAffectedPurchaseLines = array_map(function($o)use($purchaseLine){
			return $o->purchase_lines()->where('nav_line_no',$purchaseLine->nav_line_no)->where('batch_no',$purchaseLine->batch_no)->first();
		},$allAffectedOrders);
		$companyLines = [];
		if($currentQuantity != $params['quantity'])
		{
			foreach($allAffectedPurchaseLines as $line)
			{
				if($line->isCompanyLine)
				{
					$companyLines[] = $line;
				}
				$this->updateLineQuantity($user, $company,$line, $params['quantity']);
			}
		}
		$alert = null;
		foreach($companyLines as $line)
		{
			list($superRelatedPO,$alert) = $this->purchaseOrdersService->update($company, $line->purchase_order->orderable, $line->purchase_order->id);
		}
		//
		$purchaseOrder->fresh();
		return [$purchaseOrder,$alert];
	}
	protected function updateLineQuantity(User $user, Company $company,PurchaseLine $line,$quantity)
	{
		$newDiscountedUnitPrice = round($line->unit_price - ($line->unit_price  * $line->discount /100),2);
		$currentQuantity = $line->quantity;
			$line->quantity   = $quantity;
			$line->amount     = $newDiscountedUnitPrice * $line->quantity;
			$line->tax_amount = $line->amount * $line->tax_percent/100;
			$line->net_amount = $line->amount + $line->tax_amount;
			$line->save();
		if($line->isCompanyLine)
		{
			$this->salesOrderService->updateSalesLine($company, $line->purchase_order->nav_key, $line->purchase_order->navision_order_number, $line->nav_line_no, $quantity, $newDiscountedUnitPrice, $line->batch_no);
		}
		else
		{	
			if($line->isSuperStockistLine)
			{
				$superStockist = $line->purchase_order->superStockist;
				$superStockistStock = $superStockist->stocks
									->where('product_id', '=', $line->product_id)
									->where('batch_no', '=', $line->batch_no)
									->first();
				$difference = $currentQuantity - $quantity;
				if(!$superStockistStock)
				{
					$stock = new Stock;
					$stock->product_id = $line->product_id;
					$stock->batch_no = $line->batch_no;
					$stock->quantity = $difference;
					$superStockist->stocks()->save($stock);
				}
				else
				{
					$superStockistStock->quantity += $difference;
					$superStockistStock->save();
				}
			}
		}

	}
	protected function getDownStreamOrders(PurchaseOrder $order = null)
	{
		$relatedOrders = [];
		if($order)
		{
			$relatedOrders[] = $order;
			$relatedOrders = array_merge($relatedOrders, $this->getDownStreamOrders($order->blanket_order->relatedOrder));
		}
		return $relatedOrders;
	}
	protected function getUpstreamOrders(PurchaseOrder $order  = null)
	{
		$blanketOrder = BlanketOrder::where('related_po_id',$order->id)->first();
		if($blanketOrder)
			return [$blanketOrder];
		return [];
	}
	protected function getRelatedOrders(PurchaseOrder $order = null)
	{
		return array_merge($this->getDownStreamOrders($order),$this->getUpstreamOrders($order));
	}
}