<?php
namespace Rosetta\Services\CoDistributor;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as SMSNotification;
use Predis\Connection\ConnectionException;
use Rosetta\Events\Notifications;
use Rosetta\Exceptions\CoDistributors\AlreadyPresentException;
use Rosetta\Exceptions\RedisException;
use Rosetta\Mail\PaymentAdded;
use Rosetta\Mail\PaymentAddedCoDistributor;
use Rosetta\Mail\PurchaseLineDelete;
use Rosetta\Mail\PurchaseLineDeleteCoDistributor;
use Rosetta\Mail\PurchaseLineDeleteDud;
use Rosetta\Mail\PurchaseLineUpdate;
use Rosetta\Mail\PurchaseLineUpdateDistributor;
use Rosetta\Mail\PurchaseLineUpdateDud;
use Rosetta\Mail\PurchaseOrderDelete;
use Rosetta\Mail\PurchaseOrderDeleteCoDistributor;
use Rosetta\Mail\PurchaseOrderDeleteDistributor;
use Rosetta\Models\BlanketOrder;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\Log;
use Rosetta\Models\Notification;
use Rosetta\Models\OverdueAmount;
use Rosetta\Models\Payment;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
use Rosetta\Models\User;
use Rosetta\Notifications\SMS\DealerOrderCancelledNotification;
use Rosetta\Notifications\SMS\DistributorOrderCancelledNotification;
use Rosetta\Notifications\SMS\PurchaseOrderCancelledNotification;
use Rosetta\Notifications\SMS\PurchaseOrderUpdatedNotification;
use Rosetta\Services\CoDistributor\CoDistributorServiceInterface;
class CoDistributorService implements CoDistributorServiceInterface
{
	public function getCoDistributors(Company $company,Distributor $distributor, Array $params = [],$type = 1)
	{
		if($type == 1)
		{
			if(array_has($params,'page'))
			{
				$distributors = $distributor->coDistributors()->when($params, function ($query) use ($params){
					if(array_has($params, 'customer_name'))
					{
						$query->where('name', 'like', '%' . $params['customer_name'] . '%');
					}
					if(array_has($params,'customer_id'))
					{
						$query->where('nav_id', '=', $params['customer_id']);
					}
				})->orderBy('created_at','desc')->paginate($params['per_page']);
			}
			else
			{
				$distributors = $distributor->coDistributors;
			}
			return $distributors;
		}
		else
		{
			if(array_has($params,'page'))
			{
				$distributors = $distributor->parentCoDistributors()->when($params, function ($query) use ($params){
					if(array_has($params, 'customer_name'))
					{
						$query->where('name', 'like', '%' . $params['customer_name'] . '%');
					}
					if(array_has($params,'customer_id'))
					{
						$query->where('nav_id', '=', $params['customer_id']);
					}
				})->orderBy('created_at','desc')->paginate($params['per_page']);
			}
			else
			{
				$distributors = $distributor->parentCoDistributors;
			}
			return $distributors;
		}
	}
	public function update(Company $company,Distributor $distributor,Array $params = [])
	{
		$updates = [];
		if(array_has($params,'credit_limit'))
			$updates['credit_limit'] = $params['credit_limit'];
		if(array_has($params,'overdue_balance'))
			$updates['overdue_balance'] = $params['overdue_balance'];
		if($params['type'] == 1)
		{
			$distributor->coDistributors()->updateExistingPivot($params['distributor_id'],$updates);
		}
		else
		{
			$distributor->parentCoDistributors()->updateExistingPivot($params['distributor_id'],$updates);
		}
	}

	public function create(Company $company,Distributor $distributor,Array $params = [])
	{
		$coDistributor = $distributor->coDistributors()->where('co_distributor_id','=',$params['distributor_id'])->first();
		if($coDistributor)
		{
			throw new AlreadyPresentException;
		}
		$days = [
			'7 DAYS' => 7,
			'15 DAYS' => 15,
			'1 MONTH' => 30,
			'1.5 MONTH' => 45,
			'100% ADVC' => 0
		];
		$input = [
			"overdue_balance" => (float)$params['overdue_balance'],
			"credit_limit" => (float)$params['credit_limit'],
			'overdue_hold_days' => $days[$params['payment_terms']]
		];
		$distributor->coDistributors()->attach($params['distributor_id'],$input);

	}
	public function deleteSalesOrder($distributorId, $orderId, $reason, User $loggedInUser)//Co distributor deleting order placed by his main distributor for Dealer under him
	{
		$toEmailIds = [];
		$recipients = [];
		$toDealerEmailIds = [];
		$dealerRecipients = [];
		$sender = $loggedInUser;

		$distributor = Distributor::find($distributorId);//Co-Distributor who has logged in
		$company = $distributor->company;
		$blanketOrder = BlanketOrder::find($orderId);
		$customer = $blanketOrder->orderable;//main distributor who placed order with co-distributor ($distributorId)
		$orderAmount = $blanketOrder->Amount;
		$overdue = $blanketOrder->overdue;
		$orderShipped = $blanketOrder->shipped;
		$blanketOrderNo = $blanketOrder->order_number;
		$purchaseOrder = $blanketOrder->purchase_orders()->first();

	//	foreach ($purchaseOrders as $purchaseOrder)
	//	{
			$purchaseOrder->status = 'Cancelled';
			$purchaseOrder->cancelation_reason = $reason;
			$purchaseOrder->canceled_by = $loggedInUser->id;
			$purchaseOrder->save();
	//	}
		$blanketOrder->cancelation_reason = $reason;
		$blanketOrder->canceled_by = $loggedInUser->id;
		$blanketOrder->save();

		$distributor_lines = $blanketOrder->distributor_lines;
		if(count($distributor_lines))
		{
			foreach ($distributor_lines as $purchaseLine)
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
					$stock->quantity = $purchaseLine->quantity;
					$distributor->stocks()->save($stock);
				}
				else
				{
					$distributorStock->quantity += $purchaseLine->quantity;
					$distributorStock->save();
				}
			}
		}

		//this is for order between Main distributor and co distributor, order has been shipped by codistributor to distributor and then it is cancelled/deleted by codistributor (seems to be hypothetical since Cancel button is disabled when order status is not Open or when payment is done) 
		$coDistributorRecord = $distributor->parentCoDistributors()->where('distributor_id','=',$customer->id)->first();
		$main_distributor_overdue_balance = $coDistributorRecord->pivot->overdue_balance;
		if(!$overdue)
		{
			if($orderShipped == true)//100 %ADVC
			{
				$main_distributor_overdue_balance -= $orderAmount;
				$distributor->parentCoDistributors()->updateExistingPivot($customer->id,["overdue_balance" => $main_distributor_overdue_balance]);
			//	$customer->overdue_balance += $orderAmount;
			//	$customer->save();
			}
		}
		else
		{
			if($orderAmount >= $overdue->amount)//assuming some payment is done in between, which might not be possible as we have added a check on UI to prevent delete if any payment is done against order
			{
				$main_distributor_overdue_balance += ($orderAmount - $overdue->amount);
				$distributor->parentCoDistributors()->updateExistingPivot($customer->id,["overdue_balance" => $main_distributor_overdue_balance]);
				//$customer->overdue_balance += ($orderAmount - $overdue->amount);//chk if required
			}
			$overdue->delete();
		}

		//get Main Distributor who will receive email from Co distributor
		$query = User::where('distributor_id','=',$customer->id);
		$query->whereHas('roles',function($q) {
				$q->where('name','=','distributorAdmin');
			});
		$users = $query->get();
		$mainDistributor_user = $users->first();
		
		foreach ($users as $user) {
			if($user->communication_email)
				$toEmailIds[] = $user->communication_email;
			$recipients[] = $user;
		}
		$queuePrefix = config('brand.queue_prefix');
		$queue = $queuePrefix.'.emails';
		$Qmessage = (new PurchaseOrderDeleteCoDistributor($blanketOrder))->onQueue($queue);
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
			$notification->title = 'Purchase Order Cancelled!';
			$notification->text = 'Co-Distributor has cancelled ' .$blanketOrderNo. '. Reason: '. $reason;
			$notification->sender_id = $sender->id;
			$notification->recipient_id = $recipient->id;
			$notification->author()->associate($sender);
			$notification->sender()->associate($sender);
			$notification->recipient()->associate($recipient);
			$notification->save();
			event(new Notifications($notification));
			$notifiable = (object)[
	            "order_number" => $blanketOrderNo,
	            "number" => $mainDistributor_user->phone,
	            "customer" => $customer->name,//main distributor
	        //    "supplier" => $distributor->name//Co-Distributor who is cancelling the order
	        ];
	        SMSNotification::send($notifiable,new PurchaseOrderCancelledNotification($company));//#595 Notify Main Distributor about DUD Order (PO for Main Distributor) when Co-Distributor Cancels the Order
		}

		if($blanketOrder->related_po_id)
		{
			$q = User::where('distributor_id','=',$customer->id);
			$q->whereHas('roles',function($q1) {
					$q1->where('name','=','distributorAdmin');
				});
			$sender = $q->first();

			$mainDistributorReason =  'Please contact Distributor for cancellation reason.';
			$relatedPurchaseOrder = PurchaseOrder::find($blanketOrder->related_po_id);//DUD PO
			$relatedPurchaseOrder->status = 'Cancelled';
			$relatedPurchaseOrder->cancelation_reason = $mainDistributorReason;
			$relatedPurchaseOrder->canceled_by = $sender->id;
			$relatedPurchaseOrder->save();
			$relatedPOsOrderAmount = $relatedPurchaseOrder->Amount;

			$relatedPOsBlanketOrder = BlanketOrder::find($relatedPurchaseOrder->blanket_order_id);//DUD BO
			//$relatedPOsBlanketOrderAmount = $blanketOrder->Amount;
			$dealer = Dealer::find($relatedPOsBlanketOrder->orderable_id);
			$relatedPOsBlanketOrderOverdue = $relatedPOsBlanketOrder->overdue;
			$relatedPOsBlanketOrderShipped = $relatedPOsBlanketOrder->shipped;
			if($relatedPOsBlanketOrder->purchase_orders->count() == 1)
			{
				$relatedPOsBlanketOrder->cancelation_reason = $mainDistributorReason;
				$relatedPOsBlanketOrder->canceled_by = $sender->id;
				$relatedPOsBlanketOrder->save();
			}
			else
			{
				$all_pos_shipped = true;
				$all_pos_cancelled = true;
				foreach ($relatedPOsBlanketOrder->purchase_orders as $purchase_order) 
				{
					if($purchase_order->status != 'Shipped' && $purchase_order->status != 'Cancelled')
					{
						$all_pos_shipped = false;
					}
					if($purchase_order->status != 'Cancelled')
					{
						$all_pos_cancelled = false;
					}
				}
			/*	if($all_pos_shipped == true)
				{
					$relatedPOsBlanketOrder->shipped = true;
					$relatedPOsBlanketOrder->save();
				}	commented because Main Distributor is supposed to click on Print & Ship so that Overdue calculation will happen on the entire Dealers order from that day when button is clicked. */
				if($all_pos_cancelled == true)
				{
					$relatedPOsBlanketOrder->cancelation_reason = $mainDistributorReason;
					$relatedPOsBlanketOrder->canceled_by = $sender->id;
					$relatedPOsBlanketOrder->save();
				}
			}
			//this is for order between Dealer and Main Distributor, order is shipped by Main Distributor and then cancelled/deleted by him (this seems hypothetical since Dealers Order cannot be shipped unless all subsequent orders are shipped and overdue is added only when Order is Shipped)
			if(!$relatedPOsBlanketOrderOverdue)
			{
				if($relatedPOsBlanketOrderShipped == true)//100 %ADVC
				{
					$dealer->overdue_balance -= $relatedPOsOrderAmount;
					$dealer->save();
				}
			}
			else
			{
				if($relatedPOsOrderAmount >= $relatedPOsBlanketOrderOverdue->amount)//assuming some payment is done in between, which might not be possible as we have added a check on UI to prevent delete if any payment is done against order
				{
					$dealer->overdue_balance -= ($relatedPOsOrderAmount - $relatedPOsBlanketOrderOverdue->amount);//chk if required
				}
				$relatedPOsBlanketOrderOverdue->delete();
			}

			$query = User::where('dealer_id','=',$dealer->id);
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
			$Qmessage = (new PurchaseOrderDeleteDistributor($relatedPOsBlanketOrder))->onQueue($queue);
			try
		    {
		    	if(!empty($toDealerEmailIds))
	        		Mail::to($toDealerEmailIds)->queue($Qmessage);
	       	}
	       	catch(ConnectionException $e)
	       	{
	       		throw new RedisException;
		    }
		    foreach ($dealerRecipients as $recipient) 
			{
				$notification = new Notification;
				$notification->title = 'Purchase Order partially/completely Cancelled!';
				$notification->text = 'Distributor has partially/completely cancelled ' .$relatedPOsBlanketOrder->order_number. ' . Reason: '. $mainDistributorReason;
				$notification->sender_id = $sender->id;
				$notification->recipient_id = $recipient->id;
				$notification->author()->associate($sender);
				$notification->sender()->associate($sender);
				$notification->recipient()->associate($recipient);
				$notification->save();
				event(new Notifications($notification));
				$notifiable = (object)[
		            "order_number" => $relatedPOsBlanketOrder->order_number,
		            "number" => $dud_user->phone,
		            "customer" => $dealer->name,//dud
		          //  "supplier" => $customer->name//Main Distributor
		        ];
		        SMSNotification::send($notifiable,new PurchaseOrderCancelledNotification($company));//#595 Notify Main Distributor about DUD Order (PO for Main Distributor) when Co-Distributor Cancels the Order
			}
		}
		return $blanketOrder;
	}
	public function shipSalesOrder($companyId, $distributorId, $orderId, Array $params, User $user)
	{
		$blanketOrder = BlanketOrder::find($orderId);
		if(array_has($params, 'invoice_id'))
			$blanketOrder->invoice_id = $params['invoice_id'];
		$blanketOrder->shipped = true;
		$blanketOrder->save();
		$purchaseOrder = $blanketOrder->purchase_orders()->first();
		$purchaseOrder->status = 'Shipped';
		$purchaseOrder->save();

		$distributor_lines = $blanketOrder->distributor_lines;
		if(count($distributor_lines))
		{
			foreach ($distributor_lines as $purchaseLine)
			{
				$purchaseLine->shipped_quantity = $purchaseLine->quantity;
				$purchaseLine->save();
			}
		}

		if($blanketOrder->related_po_id)
		{
			$customer = $blanketOrder->orderable;//get (dealer's distributor) main distributor who placed order with co-distributor ($distributorId)
			$q = User::where('distributor_id','=',$customer->id);
			$q->whereHas('roles',function($q1) {
					$q1->where('name','=','distributorAdmin');
				});
			$sender = $q->first();

			$mainDistributorReason =  'Please contact Distributor for cancellation reason.';

			$relatedPurchaseOrder = PurchaseOrder::find($blanketOrder->related_po_id);
			$relatedPurchaseOrder->status = 'Shipped';
			$relatedPurchaseOrder->save();
			foreach($relatedPurchaseOrder->purchase_lines as $purchase_line)
			{
				$purchase_line->shipped_quantity = $purchase_line->quantity;
				$purchase_line->save();
			}

		/*	$relatedPOsBlanketOrder = BlanketOrder::find($relatedPurchaseOrder->blanket_order_id);
			if($relatedPOsBlanketOrder->purchase_orders->count() == 1)//if there is only one PO in the main BO placed for Dealer
			{
				$relatedPOsBlanketOrder->shipped = true;
				$relatedPOsBlanketOrder->save();

				//set overdue balance for dealer 
				$dealer = $relatedPOsBlanketOrder->orderable;//dealer who placed order with codistributor via main distributor
				$dealer_overdue_hold_days = $dealer->overdue_hold_days;
				if($dealer_overdue_hold_days == 0)
				{
					$dealer->overdue_balance += $relatedPurchaseOrder->Amount;
					$dealer->save();
				}
				elseif($dealer_overdue_hold_days > 0)
				{
					$overdueAmount = new OverdueAmount;
					$overdueAmount->amount = $relatedPOsBlanketOrder->Amount;
					$overdueAmount->distributor_id = $relatedPOsBlanketOrder->distributor_id;
					$overdueAmount->order_id = $relatedPOsBlanketOrder->id;
					$overdueAmount->overdue_date = Carbon::today()->addDays($dealer_overdue_hold_days);
					$dealer->overdue_amounts()->save($overdueAmount);
				}
			}
			else
			{
				$all_pos_shipped = true;
				foreach ($relatedPOsBlanketOrder->purchase_orders as $purchase_order) 
				{
					if($purchase_order->status != 'Shipped' && $purchase_order->status != 'Cancelled')
					{
						$all_pos_shipped = false;
					}
				}
				if($all_pos_shipped == true)
				{
					$relatedPOsBlanketOrder->shipped = true;
					$relatedPOsBlanketOrder->save();
				}
			}commented because Main Distributor is supposed to click on Print & Ship so that Overdue calculation will happen on the entire Dealers order from that day when button is clicked. 
			*/
		}

		$blanketOrder->refresh();
		//set overdue balance for main distributor

		$distributor = Distributor::find($distributorId);

		$coDistributorRecord = $distributor->parentCoDistributors()->where('distributor_id','=',$customer->id)->first();

		
		$overdue_hold_days = $coDistributorRecord->pivot->overdue_hold_days;
		$overdue_balance = $coDistributorRecord->pivot->overdue_balance;
	//	$overdue_hold_days = 7;
		if($overdue_hold_days == 0)
		{
			$overdue_balance +=  $blanketOrder->Amount;
			$distributor->parentCoDistributors()->updateExistingPivot($customer->id,["overdue_balance" => $overdue_balance]);
		}
		elseif($overdue_hold_days > 0)
		{
			$overdueAmount = new OverdueAmount;
			$overdueAmount->amount = $blanketOrder->Amount;
			$overdueAmount->distributor_id = $distributorId;
			$overdueAmount->order_id = $blanketOrder->id;
			$overdueAmount->overdue_date = Carbon::today()->addDays($overdue_hold_days);
			$customer->overdue_amounts()->save($overdueAmount);
		}
		return $blanketOrder;
	}
	public function addPayment($companyId, $distributorId, $orderId, Array $params, User $user)
	{
		$order = BlanketOrder::find($orderId);
		$customer = $order->orderable;//main distributor
		$distributor = Distributor::find($distributorId);//co distributor
		$coDistributorRecord = $distributor->parentCoDistributors()->where('distributor_id','=',$customer->id)->first();
		$main_distributor_overdue_balance = $coDistributorRecord->pivot->overdue_balance;	
		$payment = new Payment;
		$payment->amount = $params['payment_amount'];
		if(array_has($params,'payment_mode'))
			$payment->payment_mode = $params['payment_mode'];
		$payment->distributor_id = $distributorId;
		$payment->customer_type = get_class($order->orderable) == Distributor::class ? 'DISTRIBUTO' : 'DEALER';
		$payment->customer_id = $order->orderable_id;
		$order->payments()->save($payment);
		$overdue= $order->overdue;
		if(!$overdue)
		{
			$main_distributor_overdue_balance -= $params['payment_amount'];
			$distributor->parentCoDistributors()->updateExistingPivot($customer->id,["overdue_balance" => $main_distributor_overdue_balance]);
		}
		else
		{
			if($params['payment_amount'] >= $overdue->amount)
			{
				$overdue->delete();
				$main_distributor_overdue_balance += ($params['payment_amount'] - $overdue->amount);
				$distributor->parentCoDistributors()->updateExistingPivot($customer->id,["overdue_balance" => $main_distributor_overdue_balance]);
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
		$data['amount'] = $params['payment_amount'];
		$query = User::where('distributor_id','=',$customer->id);
		$query->whereHas('roles',function($q) {
				$q->where('name','=','distributorAdmin');
			});		
		$users = $query->get();
		
		foreach ($users as $user) {
			if($user->communication_email)
				$toEmailIds[] = $user->communication_email;
			$recipients[] = $user;
		}//add Customer (dealer under distributor) Admin Email Ids
		
		$queuePrefix = config('brand.queue_prefix');
		$queue = $queuePrefix.'.emails';
		$Qmessage = (new PaymentAddedCoDistributor($data))->onQueue($queue);
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
			$notification->text = 'Co-Distributor has added payment of Rs '.$data['amount'].' against Purchase Order ' .$order->order_number;
			$notification->sender_id = $sender->id;
			$notification->recipient_id = $recipient->id;
			$notification->author()->associate($sender);
			$notification->sender()->associate($sender);
			$notification->recipient()->associate($recipient);
			$notification->save();
			event(new Notifications($notification));
		}
	}
	public function deleteSalesLine($distributorId, $orderId, $lineId, User $user)
	{
		$sendEmail = false;
		$toEmailIds = [];
		$toDealerEmailIds = [];
		$recipients = [];
		$dealerRecipients = [];
		$sender = $user;//codistributor
		$distributorSender = $sender; //should be main distributor refer line 663
		$data = [];


		$blanketOrder = BlanketOrder::find($orderId);
		$purchaseOrder = $blanketOrder->purchase_orders()->first();
		$purchaseLine = $blanketOrder->purchase_lines()->find($lineId);
		$company = $purchaseOrder->company;
		$customer = $blanketOrder->relatedOrder->blanket_order->orderable;//DUD
		$coDistributor = Distributor::find($blanketOrder->distributor_id);//Co Dist who is performing the action
		$distributor = $customer->distributor;//Main Distributor
		
		$product = $purchaseLine->product->name;
		$product_id = $purchaseLine->product->id;
		$batch_no = $purchaseLine->batch_no;
		$purchaseOrderNo = $purchaseOrder->order_number;
		$log = new Log;
		$log->action = 'Deleted - Purchase Line';
		$log->author()->associate(Auth::user());
		$log->ip_address = \Request::ip();

		$product_display_name = $this->getProductDisplayName($company, $customer, $purchaseLine->product->category->code, $purchaseLine->product->collection->code, $purchaseLine->product->colour->code);

		$quantity = $purchaseLine->quantity;
		$line_amount = $purchaseLine->amount;

		$relatedPO = $blanketOrder->relatedOrder;
		$relatedPOsBO = $relatedPO->blanket_order;
		$relatedPOLine = $relatedPO->purchase_lines()->where('nav_line_no','=',$purchaseLine->nav_line_no)->first();
		$coDistributorStock = $coDistributor->stocks
			->where('product_id', '=', $product_id)
			->where('batch_no', '=', $batch_no)
			->first();

		if(!$coDistributorStock)
		{
			$stock = new Stock;
			$stock->product_id = $product_id;
			$stock->batch_no = $batch_no;
			$stock->quantity = $quantity;
			$coDistributor->stocks()->save($stock);
		}
		else
		{
			$coDistributorStock->quantity += $quantity;
			$coDistributorStock->save();
		}
		$relatedPOLine->delete();
		$relatedPO->Amount = $relatedPO->purchase_lines->sum('amount');
		$relatedPO->save();
		$purchaseLine->delete();
		$purchaseOrder->load('purchase_lines');
		$purchaseOrder->Amount = $purchaseOrder->purchase_lines->sum('amount');
		$purchaseOrder->save();

		$data['orderNumber'] = $relatedPOsBO->order_number;
		$data['orderId'] = $relatedPOsBO->id;
		$data['batch'] = $purchaseLine->batch_no;
		$data['product_display_name'] = $product_display_name;

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
	//	$purchaseOrder = PurchaseOrder::find($orderId);
	//	$purchaseOrder->Amount = $purchaseOrder->purchase_lines->sum('amount');
	//	$purchaseOrder->save();

		$log->comment = 'Administrator deleted Item : ' .$product_display_name. ' against Batch : ' .$batch_no;
		$blanketOrder->logs()->save($log);

		$query = User::where('dealer_id','=',$customer->id);
		$query->whereHas('roles',function($q) {
				$q->where('name','=','dealerAdmin');
			});
		$users = $query->get();
		$dud_user = $users->first();
		$notifiable = (object)[
            "order_number" => $relatedPOsBO->order_number,
            "number" => $dud_user->phone,
            "customer" => $customer->name
        ];
        SMSNotification::send($notifiable,new PurchaseOrderUpdatedNotification($company));//#595 Notify DUD
	
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
	    	if(!empty($toDealerEmailIds))
        		Mail::to($toDealerEmailIds)->queue($Qmessage);
       	}
       	catch(ConnectionException $e)
       	{
       		throw new RedisException;
	    }

	    $query = User::where('distributor_id','=',$distributor->id);
		$query->whereHas('roles',function($q) {
				$q->where('name','=','distributorAdmin');
			});
		$users = $query->get();
		$distributor_user = $users->first();
		$notifiable = (object)[
            "order_number" => $blanketOrder->order_number,
            "number" => $distributor_user->phone,
            "customer" => $distributor->name
        ];
        SMSNotification::send($notifiable,new PurchaseOrderUpdatedNotification($company));//#595 Notify DUD
	
		foreach ($users as $user) {
			if($user->communication_email)
				$toEmailIds[] = $user->communication_email;
			$recipients[] = $user;
			$distributorSender = $user;
		}//main distributor

		foreach ($dealerRecipients as $recipient) 
		{
			$notification = new Notification;
			$notification->title = 'Item from ' .$relatedPOsBO->order_number. ' deleted';
			$notification->text = 'Distributor deleted Item : ' .$product_display_name. ' against Batch : ' .$batch_no;
			$notification->sender_id = $distributorSender->id;
			$notification->recipient_id = $recipient->id;
			$notification->author()->associate($distributorSender);
			$notification->sender()->associate($distributorSender);
			$notification->recipient()->associate($recipient);
			$notification->save();
			event(new Notifications($notification));
		}

		$product_display_name = $this->getProductDisplayName($company, $distributor, $purchaseLine->product->category->code, $purchaseLine->product->collection->code, $purchaseLine->product->colour->code);
		$data['product_display_name'] = $product_display_name;
		
		$queuePrefix = config('brand.queue_prefix');
		$queue = $queuePrefix.'.emails';
		$Qmessage = (new PurchaseLineDeleteCoDistributor($data))->onQueue($queue);
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
			$notification->title = 'Item from ' .$relatedPOsBO->order_number. ' deleted';
			$notification->text = 'Co-Distributor deleted Item : ' .$product_display_name. ' against Batch : ' .$batch_no;
			$notification->sender_id = $sender->id;
			$notification->recipient_id = $recipient->id;
			$notification->author()->associate($sender);
			$notification->sender()->associate($sender);
			$notification->recipient()->associate($recipient);
			$notification->save();
			event(new Notifications($notification));
		}
		return $blanketOrder->fresh();
	}
	public function updateSalesLine($distributorId, $orderId, $lineId, Array $params,  User $user)
	{
		$sendUnitPriceEmail = false;
		$sendQuantityEmail = false;
		$sendUnitPriceDiscountEmail = false;
	  
	    $blanketOrder = BlanketOrder::find($orderId);
        $purchaseOrder = $blanketOrder->purchase_orders()->first();
        $purchaseLine = $purchaseOrder->purchase_lines()->find($lineId);
        
		$company = $purchaseOrder->company;
		$customer = $blanketOrder->relatedOrder->blanket_order->orderable;//DUD / End Customer / Dealer
		$distributor = $blanketOrder->orderable;//Main Distributor
		$coDistributor = Distributor::find($blanketOrder->distributor_id);
		
		$relatedPO = $blanketOrder->relatedOrder;
		$relatedBO = $relatedPO->blanket_order;
		$relatedPOLine = $relatedPO->purchase_lines()->where('nav_line_no','=',$purchaseLine->nav_line_no)->first();
		$relatedPOLineCurrentDiscountedPrice = round($relatedPOLine->unit_price - ($relatedPOLine->unit_price * $relatedPOLine->discount/100),2);
	   	
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

		if($params['discount'] != $currentDiscount && $params['quantity'] != $currentQuantity)
		{
			$newDiscountedUnitPrice = round($currentUnitPrice - ($currentUnitPrice * $newDiscount/100),2);	
		//	$newQuantity = $params['quantity'];
			$purchaseLine->discount = $newDiscount;
			$purchaseLine->amount = $newDiscountedUnitPrice * $newQuantity;
			$purchaseLine->quantity = $newQuantity;
			$purchaseLine->save();
			
			$sendUnitPriceDiscountEmail = true;
			$log->comment = 'Changed Discount from ' .$currentDiscount. ' to ' .$newDiscount. ' & Quantity from ' .$currentQuantity. ' to ' .$newQuantity;

			$relatedPOLine->quantity = $newQuantity;
			$relatedPOLine->amount = $relatedPOLineCurrentDiscountedPrice * $newQuantity;
			$relatedPOLine->save();
			$relatedPO->Amount = $relatedPO->purchase_lines->sum('amount');
			$relatedPO->save();
		
			$query = User::where('dealer_id','=',$customer->id);
			$query->whereHas('roles',function($q) {
					$q->where('name','=','dealerAdmin');
				});
			$dealerUsers = $query->get();
			$dud_user = $dealerUsers->first();
			$notifiable = (object)[
	            "order_number" => $blanketOrder->relatedOrder->blanket_order->order_number,
	            "number" => $dud_user->phone,
	            "customer" => $customer->name
	        ];
	        SMSNotification::send($notifiable,new PurchaseOrderUpdatedNotification($company));//#595 Notify DUD
		
			foreach ($dealerUsers as $dealerUser) {
				if($dealerUser->communication_email)
					$toDealerEmailIds[] = $dealerUser->communication_email;
				$dealerRecipients[] = $dealerUser;
			}//add Customer (dealer under distributor) Admin Email Ids

			$sender = $user;
			$product_display_name = $this->getProductDisplayName($company, $customer, $relatedPOLine->product->category->code, $relatedPOLine->product->collection->code, $relatedPOLine->product->colour->code);

			$data = [];
			$data['orderNumber'] = $relatedBO->order_number;
			$data['quantity'] = $currentQuantity;
			$data['current_discounted_unit_price'] = $relatedPOLineCurrentDiscountedPrice;
			$data['new_discounted_unit_price'] = $relatedPOLineCurrentDiscountedPrice;
			$data['product_display_name'] = $product_display_name;
			$queuePrefix = config('brand.queue_prefix');
			$queue = $queuePrefix.'.emails';
		    $Qmessage = (new PurchaseLineUpdateDud($relatedPOLine, $data))->onQueue($queue);
		    try
		    {
		    	if(!empty($toDealerEmailIds))
	        		Mail::to($toDealerEmailIds)->queue($Qmessage);
	        	foreach ($dealerRecipients as $recipient) 
				{
					$notification = new Notification;
					$notification->title = 'Updated Quantity for ' .$relatedBO->order_number. ' item';
					$notification->text = 'Updated Quantity from ' .$currentQuantity. ' to ' .$newQuantity. ' for Item : ' .$product_display_name. ', against Batch : ' .$relatedPOLine->batch_no;	
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
			//$sendQuantityEmail = true;
			$log->comment = 'Changed Quantity from ' .$currentQuantity. ' to ' .$newQuantity;

			if($params['quantity'] < $currentQuantity)		
			{	
				if($relatedBO)
				{
					$coDistributorStock = $coDistributor->stocks
						->where('product_id', '=', $purchaseLine->product_id)
						->where('batch_no', '=', $purchaseLine->batch_no)
						->first();

					$difference = $currentQuantity - $newQuantity;
					if(!$coDistributorStock)
					{
						$stock = new Stock;
						$stock->product_id = $purchaseLine->product_id;
						$stock->batch_no = $purchaseLine->batch_no;
						$stock->quantity = $difference;
						$coDistributor->stocks()->save($stock);
					}
					else
					{
						$coDistributorStock->quantity += $difference;
						$coDistributorStock->save();
					}
				}
				$differenceAmount = $originalLineAmount - $purchaseLine->amount;
			}
		}

		else if($params['discount'] != $currentDiscount)
		{
			$newDiscountedUnitPrice = round($currentUnitPrice - ($currentUnitPrice * $newDiscount/100),2);
			$purchaseLine->discount = $newDiscount;
			$purchaseLine->amount = $newDiscountedUnitPrice * $purchaseLine->quantity;
			$purchaseLine->save();
			$sendUnitPriceEmail = true;
			$log->comment = 'Changed Discount from ' .$currentDiscount. ' to ' .$newDiscount;
		}
		else if($params['quantity'] != $currentQuantity)
		{
			$relatedPOLine->quantity = $newQuantity;
			$relatedPOLine->amount = $relatedPOLineCurrentDiscountedPrice * $newQuantity;
			$relatedPOLine->save();
			$relatedPO->Amount = $relatedPO->purchase_lines->sum('amount');
			$relatedPO->save();
		
			$purchaseLine->quantity = $newQuantity;
			$purchaseLine->amount = $current_discounted_unit_price * $newQuantity;
			$purchaseLine->save();
			
			$query = User::where('dealer_id','=',$customer->id);
			$query->whereHas('roles',function($q) {
					$q->where('name','=','dealerAdmin');
				});
			$dealerUsers = $query->get();
			$dud_user = $dealerUsers->first();
			$notifiable = (object)[
	            "order_number" => $blanketOrder->relatedOrder->blanket_order->order_number,
	            "number" => $dud_user->phone,
	            "customer" => $customer->name
	        ];
	        SMSNotification::send($notifiable,new PurchaseOrderUpdatedNotification($company));//#595 Notify DUD
		
			foreach ($dealerUsers as $dealerUser) {
				if($dealerUser->communication_email)
					$toDealerEmailIds[] = $dealerUser->communication_email;
				$dealerRecipients[] = $dealerUser;
			}//add Customer (dealer under distributor) Admin Email Ids

/*			$query = User::where('distributor_id','=',$distributor->id);
			$query->whereHas('roles',function($q) {
					$q->where('name','=','distributorAdmin');
				});
			$sender = $query->first();//Main Distributor*/

			$sender = $user;

			$product_display_name = $this->getProductDisplayName($company, $customer, $relatedPOLine->product->category->code, $relatedPOLine->product->collection->code, $relatedPOLine->product->colour->code);

			$data = [];
			$data['orderNumber'] = $relatedBO->order_number;
			$data['quantity'] = $currentQuantity;
			$data['current_discounted_unit_price'] = $relatedPOLineCurrentDiscountedPrice;
			$data['new_discounted_unit_price'] = $relatedPOLineCurrentDiscountedPrice;
			$data['product_display_name'] = $product_display_name;
			$queuePrefix = config('brand.queue_prefix');
			$queue = $queuePrefix.'.emails';
		    $Qmessage = (new PurchaseLineUpdateDud($relatedPOLine, $data))->onQueue($queue);
		    try
		    {
		    	if(!empty($toDealerEmailIds))
	        		Mail::to($toDealerEmailIds)->queue($Qmessage);
	        	foreach ($dealerRecipients as $recipient) 
				{
					$notification = new Notification;
					$notification->title = 'Updated Quantity for ' .$relatedBO->order_number. ' item';
					$notification->text = 'Updated Quantity from ' .$currentQuantity. ' to ' .$newQuantity. ' for Item : ' .$product_display_name. ', against Batch : ' .$relatedPOLine->batch_no;	
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
			$sendQuantityEmail = true;
			$log->comment = 'Changed Quantity from ' .$currentQuantity. ' to ' .$newQuantity;

			if($params['quantity'] < $currentQuantity)		
			{	
				if($relatedBO)
				{
					$coDistributorStock = $coDistributor->stocks
						->where('product_id', '=', $purchaseLine->product_id)
						->where('batch_no', '=', $purchaseLine->batch_no)
						->first();

					$difference = $currentQuantity - $newQuantity;
					if(!$coDistributorStock)
					{
						$stock = new Stock;
						$stock->product_id = $purchaseLine->product_id;
						$stock->batch_no = $purchaseLine->batch_no;
						$stock->quantity = $difference;
						$coDistributor->stocks()->save($stock);
					}
					else
					{
						$coDistributorStock->quantity += $difference;
						$coDistributorStock->save();
					}
				}
				$differenceAmount = $originalLineAmount - $purchaseLine->amount;
			}
		}

		//$purchaseOrder = PurchaseOrder::find($orderId);
		$purchaseOrder->Amount = $purchaseOrder->purchase_lines->sum('amount');
		$purchaseOrder->save();

		$toEmailIds = [];
		$recipients = [];
		$sender = $user;
			
		if($sender->hasRole('distributorAdmin'))
		{
			if($relatedBO)
			{
				$query = User::where('distributor_id','=',$distributor->id);
				$query->whereHas('roles',function($q) {
						$q->where('name','=','distributorAdmin');
					});
				$users = $query->get();
				$distributor_user = $users->first();
				$notifiable = (object)[
		            "order_number" => $blanketOrder->order_number,
		            "number" => $distributor_user->phone,
		            "customer" => $distributor->name
		        ];
		        SMSNotification::send($notifiable,new PurchaseOrderUpdatedNotification($company));//#595 Notify Co-Distributor Admin
				foreach ($users as $user) {
					$recipients[] = $user;
					if($user->communication_email)
						$toEmailIds[] = $user->communication_email;
				}//add Company Admin Email Ids
			}
			$product_display_name = $this->getProductDisplayName($company, $coDistributor, $purchaseLine->product->category->code, $purchaseLine->product->collection->code, $purchaseLine->product->colour->code);
		}//if distributor admin is updating company line then add company/super admins email id

		
		if($sendUnitPriceDiscountEmail || $sendQuantityEmail || $sendUnitPriceEmail)
		{
			$purchaseLine->logs()->save($log);
			if($relatedBO)
			{
				$data = [];
				$data['orderNumber'] = $blanketOrder->order_number;
				$data['quantity'] = $currentQuantity;
				$data['current_discounted_unit_price'] = $current_discounted_unit_price;
				$data['new_discounted_unit_price'] = $newDiscountedUnitPrice;
				$data['product_display_name'] = $product_display_name;
				$queuePrefix = config('brand.queue_prefix');
				$queue = $queuePrefix.'.emails';
			    $Qmessage = (new PurchaseLineUpdateDistributor($purchaseLine, $data))->onQueue($queue);
			    try
			    {
					if(!empty($toEmailIds))
		        		Mail::to($toEmailIds)->queue($Qmessage);
		        	foreach ($recipients as $recipient) 
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
	    }
		return $purchaseOrder;
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
}