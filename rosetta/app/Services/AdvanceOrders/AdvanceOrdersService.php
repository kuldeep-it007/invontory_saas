<?php
namespace Rosetta\Services\AdvanceOrders;

use Auth;
use Carbon\Carbon;
use Illuminate\Broadcasting\event;
use Illuminate\Database\Query\whereRaw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as SMSNotification;
use Predis\Connection\ConnectionException;
use Ramsey\Uuid\Uuid;
use Rosetta\Events\Notifications;
use Rosetta\Exceptions\Order\BlockItemDeniedException;
use Rosetta\Exceptions\Order\DeleteLineException;
use Rosetta\Exceptions\Order\InsufficientQuantityToBlockException;
use Rosetta\Exceptions\Order\ViewOrderPermissionDeniedException;
use Rosetta\Exceptions\RedisException;
use Rosetta\Mail\AdvanceCartItemDelete;
use Rosetta\Mail\AdvanceCartItemDiscount;
use Rosetta\Mail\AdvanceCartItemETA;
use Rosetta\Mail\AdvanceCartItemMessage;
use Rosetta\Mail\AdvanceCartItemQuantityDiscount;
use Rosetta\Mail\AdvanceCartItemh;
use Rosetta\Mail\AdvanceLineDelete;
use Rosetta\Mail\AdvanceLineUpdate;
use Rosetta\Mail\AdvanceLineUpdateDud;
use Rosetta\Mail\AdvanceOrderDelete;
use Rosetta\Mail\AdvanceOrderDeleteDistributor;
use Rosetta\Mail\AdvanceOrderLineETA;
use Rosetta\Mail\AdvanceOrderLineMessage;
use Rosetta\Mail\AdvanceOrderNew;
use Rosetta\Models\Address;
use Rosetta\Models\AdvanceBlanketOrder;
use Rosetta\Models\AdvanceOrder\AdvanceItem;
use Rosetta\Models\AdvanceOrder\AdvanceLine;
use Rosetta\Models\AdvanceOrder\AdvanceOrder;
use Rosetta\Models\AdvanceOrder\BlockedAdvanceItem;
use Rosetta\Models\AdvanceOrder\addresses;
use Rosetta\Models\AdvanceOrder\advance_lines;
use Rosetta\Models\Company;
use Rosetta\Models\Company\StockPoint;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\Log;
use Rosetta\Models\Message;
use Rosetta\Models\Notification;
use Rosetta\Models\Product\Product;
use Rosetta\Models\Product\Stock;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Models\User;
use Rosetta\Notifications\SMS\AdvanceOrderCancelledNotification;
use Rosetta\Notifications\SMS\AdvanceOrderUpdatedNotification;
use Rosetta\Services\CLRate\CLRateServiceInterface;
use Rosetta\Services\Depot\DepotItemsServiceInterface;
use Rosetta\Services\Discount\DiscountsServiceInterface;
use Rosetta\Services\NAV\AvailableCreditServiceInterface;
use Rosetta\Services\NAV\PSIServiceInterface;
use Rosetta\Services\NAV\PSSLServiceInterface;
use Rosetta\Services\NAV\PendingPurchaseOrdersServiceInterface;
use Rosetta\Services\NAV\SalesOrderServiceInterface;

class AdvanceOrdersService implements AdvanceOrdersServiceInterface
{
	protected $salesOrderService, $psslService, $psiService, $availableCreditService, $discountService, $clRateService, $depotService, $pendingPurchaseOrdersService;
	public function __construct(SalesOrderServiceInterface $salesOrderService, PSSLServiceInterface $psslService, PSIServiceInterface $psiService, AvailableCreditServiceInterface $availableCreditService, DiscountsServiceInterface $discountService, CLRateServiceInterface $clRateService, DepotItemsServiceInterface $depotService, PendingPurchaseOrdersServiceInterface $pendingPurchaseOrdersService)
	{
		$this->salesOrderService = $salesOrderService;
		$this->psslService = $psslService;
		$this->psiService = $psiService;
		$this->availableCreditService = $availableCreditService;
		$this->discountService = $discountService;
		$this->clRateService = $clRateService;
		$this->depotService = $depotService;
		$this->pendingPurchaseOrdersService = $pendingPurchaseOrdersService;
	}
	public function create(Company $company, $customer, Array $params, User $user)
	{
		$recipients = [];
		$sender = $user;
		$advanceOrder = new AdvanceOrder;
		$advanceOrder->shipping_method = $params['shipping_method'];
		$advanceOrder->special_request = $params['special_request'];
		$UUId = Uuid::uuid4();	
		$advanceOrder->UUID = $UUId;
		$serverUrl = config('app.url');
	//	$callBackUrl = $serverUrl."/api/webhook/advance_orders/".$UUId;
		$callBackUrl = '';
		$lines = $params['advance_lines'];
		$lineNo = 10000;
 		$newLineNo = 10000;
 		$cnt = 1;
 		
		foreach ($lines as $line)
		{
			$validLine = $line;
			$validLine['line_no'] = $newLineNo;
			$cnt++;
			$newLineNo = $lineNo * $cnt;
			$validLines[] = $validLine;
		}

		$paramsUpdates = [
			"shipping_method" => $params['shipping_method'],
			"special_request" => $params['special_request'],
			"shipping_address"=> $params['shipping_address'],
			"advance_lines" => $validLines,
		];
		$validLinesCollection = collect($validLines);
		$navAdvanceOrder = $this->salesOrderService->createAO($company, $customer, $paramsUpdates, $callBackUrl);
		$advanceOrder->navision_order_number = $navAdvanceOrder->getNo();
		if($navAdvanceOrder->getStatus() == 'Released')
		{
			$advanceOrder->status = 'Approved';	
		}
		else
		{
			$advanceOrder->status = $navAdvanceOrder->getStatus();
		}
		$advanceOrder->amount = $navAdvanceOrder->getAmount();
		$advanceOrder->nav_key = $navAdvanceOrder->getKey();
		$advanceOrder->company_id = $company->id;
		$advanceOrder->order_number = str_replace('S', 'A',$navAdvanceOrder->getNo());
		$customer->advanceOrders()->save($advanceOrder);
		$navAdvanceLines = $navAdvanceOrder->getSalesLines()->getSales_Order_Line();
		if(!is_array($navAdvanceLines))
		{
			$navAdvanceLines = [$navAdvanceLines];
		}
		foreach($navAdvanceLines as $advanceLine)//Lines from NAV
		{
			$advanceOrderLine = new AdvanceLine;
			$product = Product::where('nav_id', '=', $advanceLine->getNo())->get()->first();
			$advanceOrderLine->product_id = $product->id;
			$advanceOrderLine->nav_line_no = $advanceLine->getLine_No();
			$line = $validLinesCollection->where('line_no','=',$advanceLine->getLine_No())->first();
			$advanceOrderLine->discount = $line['discount'];
			$advanceOrderLine->unit_price = $line['unit_price'];
			$advanceOrderLine->eta = $line['eta'];
			$advanceOrderLine->advance_item_id = $line['item_id'];
			$advanceOrderLine->quantity = $advanceLine->getQuantity();
			$advanceOrderLine->rate_type = $advanceLine->getRate_Type();
			$advanceOrderLine->amount = $advanceLine->getLine_Amount();
			$advanceOrderLine->net_amount = $line['net_amount'];
			$advanceOrderLine->tax_percent = $line['tax_percent'];
			$advanceOrderLine->tax_type_code = $line['tax_type_code'];
			$advanceOrderLine->tax_amount = $line['tax_amount'];
			$line_id = $advanceLine->getLine_No();
			$advanceOrder->advance_lines()->save($advanceOrderLine);

			$item = AdvanceItem::find($line['item_id']);
			$itemMessages = $item->messages;
			foreach ($itemMessages as $itemMessage)
			{
				$message = new Message;
				$message->text = $itemMessage->text;
				$message->author()->associate($itemMessage->created_by);
				$advanceOrderLine->messages()->save($message);
			}
		}
		$address = new Address;
 		$address->nav_address_code = $params['shipping_address']['code'];
 		$address->address = $params['shipping_address']['address'];
 		$address->address_2 = $params['shipping_address']['address_2'];
 		$address->post_code = $params['shipping_address']['post_code'];
 		$address->city = $params['shipping_address']['city'];
 		$address->state = $params['shipping_address']['state'];
 		$address->country_region_code = $params['shipping_address']['country_region_code'];
		$address->contact = $params['shipping_address']['contact'];
		$address->name = $params['shipping_address']['name'];
 		$address->phone_no = $params['shipping_address']['phone_no'];
 		$advanceOrder->addresses()->save($address);

 		foreach ($lines as $line)
		{
			$item = AdvanceItem::find($line['item_id']);
			$item->delete();
		}
		if($user->distributor_id != null || $user->dealer_id != null)
		{
			$query = User::where('company_id','=',$company->id);
			$query->whereHas('roles',function($q) {
					$q->where('name','=','companyAdmin');
				});
			$users = $query->get();
	
			foreach ($users as $user) {
				$recipients[] = $user;
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
				$recipients[] = $user;
			}//add Super Admin Email Ids
		}
		foreach ($recipients as $recipient) 
		{
			$notification = new Notification;
			$notification->title = 'Created new Advance Order';
			$notification->text = 'New Advance Order ' .$advanceOrder->order_number. ' has been created';
			$notification->sender_id = $sender->id;
			$notification->recipient_id = $recipient->id;
			$notification->author()->associate($sender);
			$notification->sender()->associate($sender);
			$notification->recipient()->associate($recipient);
			$notification->save();
		//	dd($notification->sender->id);
			event(new Notifications($notification));
		}
		return $advanceOrder;
	}
	public function createBackOrder($company, $customer, $customerType, $params, $user, $company_lines, $effectiveBlanketOrder)
	{
		$message = null;
		$recipients = [];
		$sender = $user;
		$advanceOrder = new AdvanceOrder;
		$advanceOrder->shipping_method = $params['shipping_method'];
		$advanceOrder->special_request = $params['special_request'];
		$UUId = Uuid::uuid4();	
		$advanceOrder->UUID = $UUId;
		$serverUrl = config('app.url');
	//	$callBackUrl = $serverUrl."/api/webhook/advance_orders/".$UUId;
		$callBackUrl = '';
		$lines = $company_lines;
		$lineNo = 10000;
 		$newLineNo = 10000;
 		$cnt = 1;
 		
		foreach ($lines as $line)
		{
			$validLine = $line;
			$validLine['line_no'] = $newLineNo;
			$cnt++;
			$newLineNo = $lineNo * $cnt;
			$validLines[] = $validLine;
		}
		$paramsUpdates = [
			"shipping_method" => $params['shipping_method'],
			"special_request" => $params['special_request'],
			"shipping_address"=> $params['shipping_address'],
			"advance_lines" => $validLines,
		];
		$validLinesCollection = collect($validLines);
		$navAdvanceOrder = $this->salesOrderService->createAO($company, $customer, $paramsUpdates, $callBackUrl);
		$advanceOrder->navision_order_number = $navAdvanceOrder->getNo();
		if($navAdvanceOrder->getStatus() == 'Released')
		{
			$advanceOrder->status = 'Approved';	
		}
		else
		{
			$advanceOrder->status = $navAdvanceOrder->getStatus();
		}
		$advanceOrder->amount = $navAdvanceOrder->getAmount();
		$advanceOrder->nav_key = $navAdvanceOrder->getKey();
		$advanceOrder->company_id = $company->id;
		$advanceOrder->blanket_order_id = $effectiveBlanketOrder->id;
		$advanceOrder->order_number = str_replace('S', 'A',$navAdvanceOrder->getNo());
		$customer->advanceOrders()->save($advanceOrder);
		$navAdvanceLines = $navAdvanceOrder->getSalesLines()->getSales_Order_Line();
		if(!is_array($navAdvanceLines))
		{
			$navAdvanceLines = [$navAdvanceLines];
		}
		foreach($navAdvanceLines as $advanceLine)//Lines from NAV
		{
			$advanceOrderLine = new AdvanceLine;
			$product = Product::where('nav_id', '=', $advanceLine->getNo())->get()->first();
			$advanceOrderLine->product_id = $product->id;
			$advanceOrderLine->nav_line_no = $advanceLine->getLine_No();
			$line = $validLinesCollection->where('line_no','=',$advanceLine->getLine_No())->first();
			$advanceOrderLine->discount = $line['discount'];
			$advanceOrderLine->unit_price = $line['unit_price'];
			$advanceOrderLine->eta = $line['eta'];
			$advanceOrderLine->advance_item_id = $line['item_id'];
			$advanceOrderLine->quantity = $advanceLine->getQuantity();
			$advanceOrderLine->rate_type = $advanceLine->getRate_Type();
			$advanceOrderLine->amount = $advanceLine->getLine_Amount();
			$advanceOrderLine->net_amount = $line['net_amount'];
			$advanceOrderLine->tax_percent = $line['tax_percent'];
			$advanceOrderLine->tax_type_code = $line['tax_type_code'];
			$advanceOrderLine->tax_amount = $line['tax_amount'];
			$line_id = $advanceLine->getLine_No();
			$advanceOrder->advance_lines()->save($advanceOrderLine);

			$item = AdvanceItem::find($line['item_id']);
			$itemMessages = $item->messages;
			foreach ($itemMessages as $itemMessage)
			{
				$message = new Message;
				$message->text = $itemMessage->text;
				$message->author()->associate($itemMessage->created_by);
				$advanceOrderLine->messages()->save($message);
			}
		}
		$address = new Address;
 		$address->nav_address_code = $params['shipping_address']['code'];
 		$address->address = $params['shipping_address']['address'];
 		$address->address_2 = $params['shipping_address']['address_2'];
 		$address->post_code = $params['shipping_address']['post_code'];
 		$address->city = $params['shipping_address']['city'];
 		$address->state = $params['shipping_address']['state'];
 		$address->country_region_code = $params['shipping_address']['country_region_code'];
		$address->contact = $params['shipping_address']['contact'];
		$address->name = $params['shipping_address']['name'];
 		$address->phone_no = $params['shipping_address']['phone_no'];
 		$advanceOrder->addresses()->save($address);

 		foreach ($lines as $line)
		{
			$item = AdvanceItem::find($line['item_id']);
			$item->delete();
		}
		if($user->distributor_id != null || $user->dealer_id != null)
		{
			$query = User::where('company_id','=',$company->id);
			$query->whereHas('roles',function($q) {
					$q->where('name','=','companyAdmin');
				});
			$users = $query->get();
	
			foreach ($users as $user) {
				$recipients[] = $user;
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
				$recipients[] = $user;
			}//add Super Admin Email Ids
		}
		foreach ($recipients as $recipient) 
		{
			$notification = new Notification;
			$notification->title = 'Created new Advance Order';
			$notification->text = 'New Advance Order ' .$advanceOrder->order_number. ' has been created';
			$notification->sender_id = $sender->id;
			$notification->recipient_id = $recipient->id;
			$notification->author()->associate($sender);
			$notification->sender()->associate($sender);
			$notification->recipient()->associate($recipient);
			$notification->save();
		//	dd($notification->sender->id);
			event(new Notifications($notification));
		}
		return [$advanceOrder,null, $message];
	}
	public function createAdvanceBlanketOrder(Company $company, $customer, Array $params, User $user, $customerType,bool $online=false,Transaction $transaction = null)
	{
		$recipients = [];
		$toEmailIds = [];
		$toDealerEmailIds = [];
		$sender = $user;
		$blanketOrder = new AdvanceBlanketOrder;
		if(array_has($params, 'source'))//#767
			$blanketOrder->source = $params['source'];
		if(array_has($params, 'OnBehalf'))//#767
			$blanketOrder->on_behalf = $params['OnBehalf'];
		$blanketOrder->company_id = $company->id;
		if($customer->distributor_id)
			$blanketOrder->distributor_id = $customer->distributor_id;
		if(array_has($params, 'end_customer_id'))
			$blanketOrder->end_customer_id = $params['end_customer_id'];
		if(array_has($params, 'installer_id'))
			$blanketOrder->engineer_id = $params['installer_id'];
		$blanketOrder->save();
		
		$collection = collect($params['advance_lines']);
		$orderAmount = $collection->sum('amount');
		if(!$online)
		{
			if($customer->distributor_id == null)
			{
				$availableCredit = $this->availableCreditService->chechAvailableCredit($company, $customer->nav_id);
				$balanceDue = $availableCredit->getBalance_Due_LCY();
				$creditLimit = $availableCredit->getCalcAvailableCreditUI();
			}
			else
			{
				$creditLimit = $customer->credit_limit;
				$balanceDue = $customer->overdue_balance;
			}
			if($orderAmount > $creditLimit)
			{
				$message = "Your order cannot be processed at this time due to negative credit limit. Please contact your Relationship Manager for further actions.";
				return [null, null, $message];
			}
			if($orderAmount <= $creditLimit && $balanceDue > 0)
			{
				$message = "Your order cannot be processed at this time due to overdue payments. Please contact your Relationship Manager for further actions.";
				return [null, null, $message];
			}
		}

		$groupedByStockPoint = $collection->groupBy('stock_point_id');
		$allInvalidLines = collect([]);
		$message = null;
		$orderNotPlacedCnt = 0;
		$stockPointCnt = 0;
		$companyStockPointCnt = 0;
		$distributorBlanketOrder = null;
		$superStokistBlanketOrder = null;
		foreach($groupedByStockPoint as $stock_point_id => $advance_lines)
		{			
			$stockPointCnt++;
			if($stock_point_id === "" || $stock_point_id === null)
			{
				$effectiveBlanketOrder = $blanketOrder;
				$company_lines = $advance_lines;
				list($advanceOrder,$invalidLines, $message) = $this->createBackOrder($company, $customer, $customerType, $params, $user, $company_lines, $effectiveBlanketOrder);
			}
			else
			{				
				if(!empty($customer->distributor->super_stockist_id) && $customer->distributor->super_stockist_id != null)
				{
					$distributorBlanketOrder = new AdvanceBlanketOrder;
					if(array_has($params, 'source'))//#767
						$distributorBlanketOrder->source = $params['source'];
					if(array_has($params, 'OnBehalf'))//#767
						$distributorBlanketOrder->on_behalf = $params['OnBehalf'];
					$distributorBlanketOrder->company_id = $company->id;
					$distributorBlanketOrder->super_stockist_id = $customer->distributor->super_stockist_id;
					$distributorBlanketOrder->save();
					//$effectiveBlanketOrder = $distributorBlanketOrder;

					#################### super stockist blanket order ####################
					$superStokistBlanketOrder = new AdvanceBlanketOrder;
					if(array_has($params, 'source'))//#767
						$superStokistBlanketOrder->source = $params['source'];
					if(array_has($params, 'OnBehalf'))//#767
						$superStokistBlanketOrder->on_behalf = $params['OnBehalf'];
					$superStokistBlanketOrder->company_id = $company->id;
					//$superStokistBlanketOrder->distributor_id = $customer->distributor_id;
					
					$superStokistBlanketOrder->save();
					$effectiveBlanketOrder = $superStokistBlanketOrder;					
					//dd('lkjhg',$effectiveBlanketOrder);
				}elseif($customer->distributor_id != null)
				{
					$distributorBlanketOrder = new AdvanceBlanketOrder;
					if(array_has($params, 'source'))//#767
						$distributorBlanketOrder->source = $params['source'];
					if(array_has($params, 'OnBehalf'))//#767
						$distributorBlanketOrder->on_behalf = $params['OnBehalf'];
					$distributorBlanketOrder->company_id = $company->id;
					$distributorBlanketOrder->super_stockist_id = $customer->distributor->super_stockist_id;
					$distributorBlanketOrder->save();
					$effectiveBlanketOrder = $distributorBlanketOrder;
					//dd('lkjhg',$effectiveBlanketOrder);
				}else
				{
					$effectiveBlanketOrder = $blanketOrder;
				}

				$stock_point = StockPoint::where('id', '=', $stock_point_id)->first();
				$company_lines = $advance_lines;
				list($advanceOrder,$invalidLines, $message) = $this->createIntransitOrder($company, $customer, $customerType, $params, $user, $company_lines, $effectiveBlanketOrder, $stock_point_id);

				/* create new advance order for dealer with distributor*/
				if($advanceOrder && $customer->distributor)
				{							
					$customer->distributor->advanceBlanketOrders()->save($distributorBlanketOrder);
					/* this is order placed for dealer create new OA based on original AO*/
					$newAdvanceOrder = new AdvanceOrder;
					$newAdvanceOrder->shipping_method = $params['shipping_method'];
					$newAdvanceOrder->special_request = $params['special_request'];
					$newAdvanceOrder->UUID = Uuid::uuid4();
					$newAdvanceOrder->navision_order_number = '';
					$newAdvanceOrder->distributor_id = $customer->distributor_id;
					$newAdvanceOrder->blanket_order_id = $blanketOrder->id;
					$newAdvanceOrder->stock_point_id = $stock_point_id;
					$newAdvanceOrder->nav_key = '';
					$newAdvanceOrder->company_id = $company->id;
					$newAdvanceOrder->status = $advanceOrder->status;
					$newAdvanceOrder->shipping_method = $params['shipping_method'];
					$newAdvanceOrder->special_request = $params['special_request'];
					$newAdvanceOrder->save();

					$address = new Address;
			 		$address->nav_address_code = $params['shipping_address']['code'];
			 		$address->address = $params['shipping_address']['address'];
			 		$address->address_2 = $params['shipping_address']['address_2'];
			 		$address->post_code = $params['shipping_address']['post_code'];
			 		$address->city = $params['shipping_address']['city'];
			 		$address->state = $params['shipping_address']['state'];
			 		$address->country_region_code = $params['shipping_address']['country_region_code'];
					$address->contact = $params['shipping_address']['contact'];
					$address->name = $params['shipping_address']['name'];
			 		$address->phone_no = $params['shipping_address']['phone_no'];
			 		$newAdvanceOrder->addresses()->save($address);
					
					$originalParamLines = collect($params['advance_lines']);
					$totalAmount = 0;
					foreach($advanceOrder->advance_lines as $temp_advance_line)
					{
						$tempLine = new AdvanceLine;
						$tempLine->nav_line_no = $temp_advance_line->nav_line_no;
						$tempLine->product_id = $temp_advance_line->product_id;
						$tempLine->advance_order_id = $newAdvanceOrder->id;
						$tempLine->quantity = $temp_advance_line->quantity;
						$tempLine->batch_no = $temp_advance_line->batch_no;
						$selectedLine = $originalParamLines->where('product_id','=',$temp_advance_line->product_id)
							->where('batch_no','=',$temp_advance_line->batch_no)
							->where('quantity','=',round($temp_advance_line->quantity,2))//#843
							->first();
						$tempLine->unit_price = $selectedLine['unit_price'];
						$tempLine->discount = $selectedLine['discount'];
						$tempLine->amount = $selectedLine['amount'];
						$tempLine->rate_type = $temp_advance_line->rate_type;
						$tempLine->tax_type_code = $temp_advance_line->tax_type_code;
						$tempLine->tax_percent = $temp_advance_line->tax_percent;
						$tempLine->tax_amount = ((float) $tempLine->amount) * $tempLine->tax_percent /100;
						$tempLine->net_amount = $tempLine->tax_amount + $tempLine->amount;
						$tempLine->eta = $temp_advance_line->eta;
						$tempLine->nav_intransit_po = $temp_advance_line->batch_no;
						$tempLine->advance_item_id = $selectedLine['item_id'];
						$newAdvanceOrder->advance_lines()->save($tempLine);
						$totalAmount+=(float)$tempLine->amount;
					}
					$newAdvanceOrder->Amount = $totalAmount;
					$newAdvanceOrder->save();

					$customer->advanceOrders()->save($newAdvanceOrder);
					$blanketOrder->related_ao_id = $newAdvanceOrder->id;
					$blanketOrder->save();					
				}

				/** Create new advance order for distributor with super stokist Start*/
				if($advanceOrder && !empty($customer->distributor->superStockist))
				{
					
					$customer->distributor->superStockist->advanceBlanketOrders()->save($superStokistBlanketOrder);

					/* this is order placed for SS create new AO based on original AO*/
					$newSSAdvanceOrder = new AdvanceOrder;
					$newSSAdvanceOrder->shipping_method = $params['shipping_method'];
					$newSSAdvanceOrder->special_request = $params['special_request'];
					$newSSAdvanceOrder->UUID = Uuid::uuid4();
					$newSSAdvanceOrder->navision_order_number = '';
					$newSSAdvanceOrder->super_stockist_id = $customer->distributor->super_stockist_id;
					$newSSAdvanceOrder->blanket_order_id = $distributorBlanketOrder->id;
					$newSSAdvanceOrder->nav_key = '';
					$newSSAdvanceOrder->company_id = $company->id;
					$newSSAdvanceOrder->stock_point_id = $stock_point_id;
					$newSSAdvanceOrder->status = $advanceOrder->status;
					$newSSAdvanceOrder->shipping_method = $params['shipping_method'];
					$newSSAdvanceOrder->special_request = $params['special_request'];
					$newSSAdvanceOrder->save();

					$address = new Address;
					$address->nav_address_code = $params['shipping_address']['code'];
					$address->address = $params['shipping_address']['address'];
					$address->address_2 = $params['shipping_address']['address_2'];
					$address->post_code = $params['shipping_address']['post_code'];
					$address->city = $params['shipping_address']['city'];
					$address->state = $params['shipping_address']['state'];
					$address->country_region_code = $params['shipping_address']['country_region_code'];
					$address->contact = $params['shipping_address']['contact'];
					$address->name = $params['shipping_address']['name'];
					$address->phone_no = $params['shipping_address']['phone_no'];
					$newSSAdvanceOrder->addresses()->save($address);
					
					$originalParamLines = collect($params['advance_lines']);
					$totalAmount = 0;
					foreach($advanceOrder->advance_lines as $temp_advance_line)
					{
						$product = Product::find($temp_advance_line->product_id);
						$discount = $this->discountService->getDiscount('SUPER_STOCKIST_DISTRIBUTOR',$customer->distributor,$product);
						$priceObj = $product->prices()->where('company_id','=',$company->id)->first();
						$price = 0;
						
						$tempLine = new AdvanceLine;
						$tempLine->nav_line_no = $temp_advance_line->nav_line_no;
						$tempLine->product_id = $temp_advance_line->product_id;
						$tempLine->advance_order_id = $newSSAdvanceOrder->id;
						$tempLine->quantity = $temp_advance_line->quantity;
						$tempLine->batch_no = $temp_advance_line->batch_no;

						if($priceObj)
						{
							$price = (float)$priceObj->price;
						}

						if($product->category->allow_cl && ($temp_advance_line->rate_type == 'CL'))
						{
							$clPercent = $this->clRateService->getRate($company,$product,'DISTRIBUTO',$customer->distributor_id);
							//$price = $price + ($price * $clPercent / 100);
							//dd($clPercent, $price, $priceObj);
							$tempLine->unit_price = $price;
						}
						else
						{
							$tempLine->unit_price = $price;
						}

						$tempLine->discount = (float)$discount;
						$finalAmount = ((float)$tempLine->unit_price * (float)$tempLine->quantity);
						$amount = $finalAmount-(((float)$finalAmount*(float)$discount)/100);

						//$tempLine->unit_price = $selectedLine['unit_price'];
						//$tempLine->discount = $selectedLine['discount'];
						$tempLine->amount = $amount;
						$tempLine->rate_type = $temp_advance_line->rate_type;
						$tempLine->tax_type_code = $temp_advance_line->tax_type_code;
						$tempLine->tax_percent = $temp_advance_line->tax_percent;
						$tempLine->tax_amount = ((float) $tempLine->amount) * $tempLine->tax_percent /100;
						$tempLine->net_amount = $tempLine->tax_amount + $tempLine->amount;
						$tempLine->eta = $temp_advance_line->eta;
						$tempLine->nav_intransit_po = $temp_advance_line->batch_no;
						$tempLine->advance_item_id = $selectedLine['item_id'];

						$newSSAdvanceOrder->advance_lines()->save($tempLine);
						$totalAmount+=(float)$tempLine->amount;
					}
					$newSSAdvanceOrder->Amount = $totalAmount;
					$newSSAdvanceOrder->save();

					$customer->distributor->advanceOrders()->save($newSSAdvanceOrder);
					$distributorBlanketOrder->related_ao_id = $newSSAdvanceOrder->id;
					$distributorBlanketOrder->save();					
				}
				/** Create new advance order for distributor with super stokist END*/

				if(count($invalidLines))
				{
					$allInvalidLines = $allInvalidLines->concat($invalidLines);
				}
				if($advanceOrder === null)
				{
					$orderNotPlacedCnt++;
				}
				$companyStockPointCnt++;
			}
		}

		if($orderNotPlacedCnt == $stockPointCnt)
		{
			$blanketOrder->forceDelete();
			if($distributorBlanketOrder)
				$distributorBlanketOrder->forceDelete();
			return [null, $allInvalidLines, $message];
		}
		if($allInvalidLines->count() == count($params['advance_lines']))
		{
			$blanketOrder->forceDelete();
			if($distributorBlanketOrder)
				$distributorBlanketOrder->forceDelete();
			return [null, $allInvalidLines, $message];
		}
		$customer->advanceBlanketOrders()->save($blanketOrder);
		if($transaction)
		{
			$transaction->advanceBlanketOrder()->associate($blanketOrder);
            $transaction->save();
		}
		return [$blanketOrder,$allInvalidLines, $message];
	}
	public function createIntransitOrder(Company $company, $customer, $customerType, Array $params, User $user, $company_lines, $effectiveBlanketOrder, $stock_point_id)
	{
		$message = null;
		$soAmount = 0;
		$recipients = [];
		$sender = $user;
		$advanceOrder = new AdvanceOrder;
		$advanceOrder->shipping_method = $params['shipping_method'];
		$advanceOrder->special_request = $params['special_request'];
		$UUId = Uuid::uuid4();	
		$advanceOrder->UUID = $UUId;
		$serverUrl = config('app.url');
		$callBackUrl = '';
		$stock_point = StockPoint::where('id', '=', $stock_point_id)->first();
		$validated = $this->salesOrderService->validateInTransitOrder($company,$company_lines,$customer, $params['order_from'], $stock_point->code);

		if(!count($validated['valid']))
		{
			return [null, $validated['invalid'], $message];
		}
		$lines = $validated['valid'];
		$lineNo = 10000;
 		$newLineNo = 10000;
 		$cnt = 1;
 		
		foreach ($lines as $line)
		{
			$validLine = $line;
			$validLine['line_no'] = $newLineNo;
			$batch_nos[$newLineNo] = $line['batch_no'];
			$cnt++;
			$newLineNo = $lineNo * $cnt;
			$validLines[] = $validLine;
			$unitPrice = (float)$line['unit_price'];
			$unitPrice = round($unitPrice - ($unitPrice *$line['discount']/100),2);
			$lineAmount = $unitPrice * $line['quantity'];
			$soAmount = $soAmount + $lineAmount;
		}

		$paramsUpdates = [
			"shipping_method" => $params['shipping_method'],
			"special_request" => $params['special_request'],
			"shipping_address"=> $params['shipping_address'],
			"order_from" => $params['order_from'],
			"advance_lines" => $validLines,
		];
		$validLinesCollection = collect($validLines);

		if(!empty($customer->distributor->super_stockist_id) && $customer->distributor->super_stockist_id != null)
		{
			/* Update advance order for distributor with super stokist *
			if($customer->distributor_id != null)
		{
			/* update pricing for each line, take pricing for distributor*
			foreach($paramsUpdates['advance_lines'] as &$aLine)
			{
				$product = Product::find($aLine['product_id']);
				$discount = $this->discountService->getDiscount('DISTRIBUTO',$customer->distributor,$product);
				$priceObj = $product->prices()->where('company_id','=',$company->id)->first();
				$price = 0;
				if($priceObj)
				{
					$price = (float)$priceObj->price;
				}

				if($product->category->allow_cl && ($aLine['rate_type'] == 'CL'))
				{
					$clPercent = $this->clRateService->getRate($company,$product,'DISTRIBUTO',$customer->distributor_id);
					$price = $price + ($price * $clPercent / 100);

					$aLine['unit_price'] = $price;
				}
				else
				{
					$aLine['unit_price'] = $price;
				}

				$aLine['discount'] = (float)$discount;
			}
			//$navAdvanceOrder = $this->salesOrderService->createIntransitOrder($company, $customer->distributor, $paramsUpdates, $callBackUrl, $batch_nos, $stock_point->code);
		}*/

			/* update pricing for each line, take pricing for superstokist*/
			foreach($paramsUpdates['advance_lines'] as &$aLine)
			{
				$product = Product::find($aLine['product_id']);
				$discount = $this->discountService->getDiscount('SUPER_STOCKIST',$customer->distributor->superStockist,$product);
				$priceObj = $product->prices()->where('company_id','=',$company->id)->first();
				$price = 0;
				if($priceObj)
				{
					$price = (float)$priceObj->price;
				}

				if($product->category->allow_cl && ($aLine['rate_type'] == 'CL'))
				{
					$clPercent = $this->clRateService->getRate($company,$product,'SUPER_STOCKIST',$customer->distributor->super_stockist_id);
					$price = $price + ($price * $clPercent / 100);
					$aLine['unit_price'] = $price;
				}
				else
				{
					$aLine['unit_price'] = $price;
				}

				$aLine['discount'] = (float)$discount;
			}

			/** Comment out for distributor */
			//$navAdvanceOrder = $this->salesOrderService->createIntransitOrder($company, $customer->distributor, $paramsUpdates, $callBackUrl, $batch_nos, $stock_point->code);
			$navAdvanceOrder = $this->salesOrderService->createIntransitOrder($company, $customer->distributor->superStockist, $paramsUpdates, $callBackUrl, $batch_nos, $stock_point->code);
		}else if($customer->distributor_id != null)
		{
			/* update pricing for each line, take pricing for distributor*/
			foreach($paramsUpdates['advance_lines'] as &$aLine)
			{
				$product = Product::find($aLine['product_id']);
				$discount = $this->discountService->getDiscount('DISTRIBUTO',$customer->distributor,$product);
				$priceObj = $product->prices()->where('company_id','=',$company->id)->first();
				$price = 0;
				if($priceObj)
				{
					$price = (float)$priceObj->price;
				}

				if($product->category->allow_cl && ($aLine['rate_type'] == 'CL'))
				{
					$clPercent = $this->clRateService->getRate($company,$product,'DISTRIBUTO',$customer->distributor_id);
					$price = $price + ($price * $clPercent / 100);
					$aLine['unit_price'] = $price;
				}
				else
				{
					$aLine['unit_price'] = $price;
				}

				$aLine['discount'] = (float)$discount;
			}
			$navAdvanceOrder = $this->salesOrderService->createIntransitOrder($company, $customer->distributor, $paramsUpdates, $callBackUrl, $batch_nos, $stock_point->code);
		}
		else
		{
			$navAdvanceOrder = $this->salesOrderService->createIntransitOrder($company, $customer, $paramsUpdates, $callBackUrl, $batch_nos, $stock_point->code);
		}

		$advanceOrder->navision_order_number = $navAdvanceOrder->getNo();
		if($navAdvanceOrder->getStatus() == 'Released')
		{
			$advanceOrder->status = 'Approved';	
		}
		else
		{
			$advanceOrder->status = $navAdvanceOrder->getStatus();
		}
		$advanceOrder->amount = $navAdvanceOrder->getAmount();
		$advanceOrder->nav_key = $navAdvanceOrder->getKey();
		$advanceOrder->company_id = $company->id;
		$advanceOrder->blanket_order_id = $effectiveBlanketOrder->id;
		$advanceOrder->stock_point_id = $stock_point_id;
		$advanceOrder->order_number = str_replace('S', 'A',$navAdvanceOrder->getNo());
	
		if(!empty($customer->distributor->super_stockist_id) && $customer->distributor->super_stockist_id != null){	
			$ss_related_ao = $customer->distributor->superStockist->advanceOrders()->save($advanceOrder);
			$effectiveBlanketOrder->related_ao_id = $ss_related_ao->id;
			$effectiveBlanketOrder->save();
		}else if($customer->distributor_id != null){
			$ss_related_ao = $customer->distributor->advanceOrders()->save($advanceOrder);
			$effectiveBlanketOrder->related_ao_id = $ss_related_ao->id;
			$effectiveBlanketOrder->save();
		}else{
			$ss_related_ao = $customer->advanceOrders()->save($advanceOrder);
			$effectiveBlanketOrder->related_ao_id = $ss_related_ao->id;
			$effectiveBlanketOrder->save();
		}
		$navAdvanceLines = $navAdvanceOrder->getSalesLines()->getSales_Order_Line();
		if(!is_array($navAdvanceLines))
		{
			$navAdvanceLines = [$navAdvanceLines];
		}
		$paramsUpdatesLineCollection = collect($paramsUpdates['advance_lines']);
		foreach($navAdvanceLines as $advanceLine)//Lines from NAV
		{
			$line_id = $advanceLine->getLine_No();
			$advanceOrderLine = new AdvanceLine;
			$product = Product::where('nav_id', '=', $advanceLine->getNo())->get()->first();
			$tax = $company->region->taxes()->where('category_code','=',$product->category->code)->where('collection_code','=',$product->collection->code)->first();
			if(!$tax)
			{
				$tax = $company->region->taxes()->where('category_code','=',$product->category->code)->first();
			}
			if($tax)
			{
				$advanceOrderLine->tax_type_code = $tax->tax_type_code;
				$advanceOrderLine->tax_percent = $tax->tax_percent;
				$advanceOrderLine->tax_amount = ((float) $advanceLine->getLine_Amount()) * $advanceOrderLine->tax_percent /100;
			}
			else
			{
				$advanceOrderLine->tax_percent = 0;
				$advanceOrderLine->tax_amount = 0;

			}
			$advanceOrderLine->product_id = $product->id;
			$advanceOrderLine->nav_line_no = $advanceLine->getLine_No();
			$line = $validLinesCollection->where('line_no','=',$advanceLine->getLine_No())->first();
			$line1 = $paramsUpdatesLineCollection->where('product_id','=',$product->id)
 					->where('batch_no','=',$batch_nos[$line_id])
 					->first();
			$advanceOrderLine->discount = $line1['discount'];
			$advanceOrderLine->unit_price = $line1['unit_price'];
			$advanceOrderLine->eta = $line['eta'];
			$advanceOrderLine->advance_item_id = $line['item_id'];
			$advanceOrderLine->quantity = $advanceLine->getQuantity();
			$advanceOrderLine->rate_type = $advanceLine->getRate_Type();
			$advanceOrderLine->amount = $advanceLine->getLine_Amount();
			$advanceOrderLine->net_amount = $advanceOrderLine->tax_amount + $advanceOrderLine->amount;
			$advanceOrderLine->batch_no = $batch_nos[$line_id];
			$advanceOrderLine->nav_intransit_po = $batch_nos[$line_id];
			//	$advanceOrderLine->net_amount = $line['net_amount'];
		//	$advanceOrderLine->tax_percent = $line['tax_percent'];
		//	$advanceOrderLine->tax_type_code = $line['tax_type_code'];
		//	$advanceOrderLine->tax_amount = $line['tax_amount'];
		//	$advanceOrderLine->batch_no = $line['batch_no'];
		//	$advanceOrderLine->nav_intransit_po = $line['batch_no'];
			$line_id = $advanceLine->getLine_No();
			$advanceOrder->advance_lines()->save($advanceOrderLine);

			if($params['order_from'] == 'cart')
				$item = AdvanceItem::find($line['item_id']);
			else if($params['order_from'] == 'blocked')
				$item = BlockedAdvanceItem::find($line['item_id']);
			$itemMessages = $item->messages;
			if($itemMessages)
			{
				foreach ($itemMessages as $itemMessage)
				{
					$message = new Message;
					$message->text = $itemMessage->text;
					$message->author()->associate($itemMessage->created_by);
					$advanceOrderLine->messages()->save($message);
				}
			}
		}
		$address = new Address;
 		$address->nav_address_code = $params['shipping_address']['code'];
 		$address->address = $params['shipping_address']['address'];
 		$address->address_2 = $params['shipping_address']['address_2'];
 		$address->post_code = $params['shipping_address']['post_code'];
 		$address->city = $params['shipping_address']['city'];
 		$address->state = $params['shipping_address']['state'];
 		$address->country_region_code = $params['shipping_address']['country_region_code'];
		$address->contact = $params['shipping_address']['contact'];
		$address->name = $params['shipping_address']['name'];
 		$address->phone_no = $params['shipping_address']['phone_no'];
 		$advanceOrder->addresses()->save($address);

 		$advanceOrder->load('advance_lines');
 		$advanceOrder->load('addresses');
 		$advance_lines = $validated['valid'];

 		foreach ($advance_lines as $advance_line)
		{
			if($params['order_from'] == 'cart')
			{
				$item = AdvanceItem::find($advance_line['item_id']);
				$item->delete();
			}
			else if($params['order_from'] == 'blocked')
			{
				$item = BlockedAdvanceItem::find($advance_line['item_id']);
				$UUId = $item->UUID;
				$this->depotService->unBlockAdvanceItem($UUId, $customer->company);//#672
				$item->delete();
			}
		}
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
		if($customerType == 'dealers' && $customer->distributor_id != null)//email to Dud
		{
			$q = User::where('distributor_id','=',$customer->distributor_id);
			$q->whereHas('roles',function($q1) {
					$q1->where('name','=','distributorAdmin');
				});
			$sender = $q->first();
			$mainDistributorUsers = $q->get();
			foreach ($mainDistributorUsers as $user) {
				if($user->communication_email)
					$toMainDistributorEmailIds[] = $user->communication_email;
				$mainDistributorRecipients[] = $user;
			}

			foreach ($mainDistributorRecipients as $recipient) 
			{
				$notification = new Notification;
				$notification->title = 'Created new Advance Order';
				$notification->text = 'New Advance Order ' .$advanceOrder->order_number. ' has been created';
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
			$Qmessage = (new AdvanceOrderNew($effectiveBlanketOrder))->onQueue($queue);
			try
		    {
		    	if(!empty($toMainDistributorEmailIds))
	        		Mail::to($toMainDistributorEmailIds)->queue($Qmessage);
	       	}
	       	catch(ConnectionException $e)
	       	{
	       		throw new RedisException;
		    }

		}
		foreach ($recipients as $recipient) 
		{
			$notification = new Notification;
			$notification->title = 'Created new Advance Order';
			$notification->text = 'New Advance Order ' .$advanceOrder->order_number. ' has been created';
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
		$Qmessage = (new AdvanceOrderNew($effectiveBlanketOrder))->onQueue($queue);
		
		try
	    {
	    	if(!empty($toEmailIds))
        		Mail::to($toEmailIds)->queue($Qmessage);
       	}
       	catch(ConnectionException $e)
       	{
       		throw new RedisException;
	    }
		return [$advanceOrder,$validated['invalid'], $message];
	}
	public function update(Company $company, $customer, $orderId)
	{
		$advanceOrder = AdvanceOrder::find($orderId);
		$advanceLines = $advanceOrder->advance_lines()->get();
		$status = $advanceOrder->status;
		$navAdvanceOrder = $this->salesOrderService->refresh($company, $advanceOrder->navision_order_number);

		$blanketOrder = $advanceOrder->advance_blanket_order;
		$relatedAO = $blanketOrder->relatedOrder;
		if($relatedAO)
			$relatedAOLines = $relatedAO->advance_lines()->get();

		$alert = false;
		if(!$navAdvanceOrder)//complete shipment scenario
		{
			$psiPresent = $this->psiService->getSO($company, $advanceOrder->navision_order_number);
			if($psiPresent)
			{
				foreach ($advanceLines as $advanceLine)
				{
					$productId = $advanceLine->product_id;
					$quantity = $advanceLine->quantity;
					$shippedQuantity = $advanceLine->shipped_quantity;
					if($shippedQuantity <> $quantity)
					{
						$advanceLine->shipped_quantity = $quantity;
						if($relatedAO)
							$advanceLine->received_quantity = $quantity;//#510
						$advanceLine->save();
					}
				}
				$advanceOrder->status = 'Complete';
				$psi = $this->psiService->getSI($company, $advanceOrder->navision_order_number);//#635
				$advanceOrder->nav_invoice_number = $psi->getNo();//#635
				$advanceOrder->nav_invoice_date = strtotime($psi->getPosting_Date());//#667
				$advanceOrder->save();

				if($relatedAO)
				{
					$relatedAO->status = 'Complete';
					$relatedAO->nav_invoice_number = $advanceOrder->nav_invoice_number;//#635
					$relatedAO->nav_invoice_date = $advanceOrder->nav_invoice_date;//#862 
					$relatedAO->save();
					foreach ($relatedAOLines as $relatedAdvanceLine)
					{
						$relatedAdvanceLine->shipped_quantity = $relatedAdvanceLine->quantity;
						$relatedAdvanceLine->save();
					}
					$blanketOrder->shipped = true;
					$blanketOrder->order_status = 'Complete';//#635
					$blanketOrder->save();
				}
			}
			else
			{
				if($advanceOrder->status <> 'Cancelled')
				{
					$advanceOrder->status = 'Cancelled';
					$advanceOrder->cancelation_reason = 'Cancelled by Company Admin';
					$advanceOrder->save();
					if($relatedAO)
					{
						$relatedAO->status = 'Cancelled';
						$relatedAO->cancelation_reason = 'Cancelled by Company Admin';
						$relatedAO->save();
						$blanketOrder->cancelation_reason = 'Cancelled by Company Admin';
						$blanketOrder->save();
					}
				}
			//	$advanceOrder->status = 'Cancelled';
			//	$advanceOrder->save();
			}
		}		
		else if($navAdvanceOrder->getStatus() == 'Released')//partial shipment scenario
		{
			$navAdvanceLines = $navAdvanceOrder->getSalesLines()->getSales_Order_Line();
			if($navAdvanceLines)
			{
				if(!is_array($navAdvanceLines))
				{
					$navAdvanceLines = [$navAdvanceLines];
				}
				//$shipped = false;
				foreach($navAdvanceLines as $navAdvanceLine)
				{
					//$line = $advanceOrder->advance_lines()->whereNull('batch_no')->where('nav_line_no','=',$navAdvanceLine->getLine_No())->first();
					$line = $advanceOrder->advance_lines()->where('nav_line_no','=',$navAdvanceLine->getLine_No())->first();
					if($relatedAO)
						$relatedAOLine = $relatedAO->advance_lines()->where('nav_line_no','=',$navAdvanceLine->getLine_No())->first();
					if($line)
					{
						if($navAdvanceLine->getQuantity_Shipped())
						{
							//$shipped = true;
							//$alreadyShippedLines = $advanceOrder->advance_lines()->whereNotNull('batch_no')->where('nav_line_no','=',$navAdvanceLine->getLine_No())->first();//check the result
							$alreadyShippedLines = $advanceOrder->advance_lines()->where('nav_line_no','=',$navAdvanceLine->getLine_No())->first();//check the result

							if($alreadyShippedLines && !is_array($alreadyShippedLines))
							{
								$alreadyShippedLines = [$alreadyShippedLines];
							}
							$shipmentLines = $this->psslService->getShipment($company, $advanceOrder->navision_order_number, $line->nav_line_no);
							if(is_array($shipmentLines))//certain times we are getting 2 instances, this is bug with NAV
							{
								$noOfShipmentLines = count($shipmentLines);
							}
							else
							{
								$noOfShipmentLines = 1;
							}

							//***************Start Of Multiple PSS Line Instances ********//

							if($noOfShipmentLines > 1)
							{
								if($shipmentLines && !is_array($shipmentLines))
								{
									$shipmentLines = [$shipmentLines];
								}
								$uniqueShipments=[];
								foreach ($shipmentLines as $shipmentLine)
								{
									if($alreadyShippedLines)
									{
										foreach ($alreadyShippedLines as $alreadyShippedLine)
										{
											if($alreadyShippedLine->batch_no != $shipmentLine->getLot_No())
											{
												$uniqueShipments[$shipmentLine->getLot_No()] = $shipmentLine->getQuantity();
											}
										}
									}
									else
									{
										$uniqueShipments[$shipmentLine->getLot_No()] = $shipmentLine->getQuantity();
									}
								}
							}//***************End Of Multiple PSS Line Instances ********//
							else if($noOfShipmentLines == 1)//********Start Of Single PSS Line Instance. Certain times NAV sends multiple shipments in single instance with comma separated values in Lot_Numbers and Multiple_Quantities tags********//
							{
								$BatchNos = $shipmentLines->getLot_Numbers();
								$BatchQuantities = $shipmentLines->getMultiple_Quantities();
								$batchNoArray = explode("," ,$BatchNos);
								$QuantitiesArray = explode(";" ,$BatchQuantities);
								$uniqueShipments=[];
								$cnt = 0;

								foreach ($batchNoArray as $batchNo)
								{
									if($alreadyShippedLines)
									{
										foreach ($alreadyShippedLines as $alreadyShippedLine)
										{
											if($alreadyShippedLine->batch_no != $batchNo)
											{
												$uniqueShipments[$batchNo] = $QuantitiesArray[$cnt];	
												$cnt++;
											}
										}
									}
									else
									{
										$uniqueShipments[$batchNo] = $QuantitiesArray[$cnt];	
										$cnt++;
									}
								}
							}//***************End Of Single PSS Line Instance ********//

							foreach ($uniqueShipments as $key => $value)
							{
								$value = preg_replace('/,/','',$value);
								if(round($value, 2) >= 1)
								{
									if(ceil($value) == ceil($line->quantity) || floor($value) == floor($line->quantity))
									{
										$line->batch_no = $key;
										$line->shipped_quantity = round($value, 2);

										if($relatedAO)
										{
											$relatedAOLine->shipped_quantity = round($value, 2);
											$line->received_quantity = round($value, 2);//#510
										}

										$line->quantity = round($value, 2);
										$line->nav_po_no = $navAdvanceLine->getPurchase_Order_No();
										$line->save();
										if($relatedAO)
											$relatedAOLine->save();
									}
									else if(ceil($value) < ceil($line->quantity))
									{
										$pendingQty = $line->quantity - round($value, 2);
										$line->quantity = round($value, 2);
										$line->shipped_quantity = round($value, 2);

										if($relatedAO)
										{
											$relatedAOLine->shipped_quantity = round($value, 2);
											$line->received_quantity = round($value, 2);//#510
										}

										$line->amount = ($line->unit_price - ($line->unit_price * ($line->discount / 100))) * round($value, 2);
										$line->batch_no = $key;
										$line->nav_po_no = $navAdvanceLine->getPurchase_Order_No();
										$line->save();

										if($relatedAO)
											$relatedAOLine->save();

										$advanceOrderLine = new AdvanceLine;
										$advanceOrderLine->product_id = $line->product_id;
										$advanceOrderLine->nav_line_no = $line->nav_line_no;
										$advanceOrderLine->discount = $line->discount;
										$advanceOrderLine->unit_price = $line->unit_price;
										$advanceOrderLine->rate_type = $line->rate_type;
										$advanceOrderLine->amount = ($line->unit_price - ($line->unit_price * ($line->discount / 100))) * $pendingQty;
										$advanceOrderLine->quantity = $pendingQty;
										$advanceOrderLine->eta = $line->eta;
										$advanceOrderLine->advance_item_id = $line->advance_item_id;
										$advanceOrderLine->nav_po_no = $navAdvanceLine->getPurchase_Order_No();
										$advanceOrder->advance_lines()->save($advanceOrderLine);
									}	
								}
								$line = $advanceOrder->advance_lines()->whereNull('batch_no')->where('nav_line_no','=',$navAdvanceLine->getLine_No())->first();
							}
						}//end of $navAdvanceLine->getQuantity_Shipped()
						//elseif($navAdvanceLine->getPurchase_Order_No() != null && $line->nav_po_no == null)
						else//sometimes PO against BackOrder SO can be deleted in NAV after creation before fulfillment, so nav_po_no once present may get removed later, hence don't chk condition while updating nav_po_no
						{
							$line->nav_po_no = $navAdvanceLine->getPurchase_Order_No();
							$line->save();
						}
					}//end if $line
				}
				$totalLinesCnt = $advanceOrder->advance_lines()->count();
				$completeShipmentCnt = DB::table('advance_lines')->where('advance_order_id', '=', $advanceOrder->id)->whereColumn('shipped_quantity', '=', 'quantity')->count();
				$partialShipmentCnt = DB::table('advance_lines')->where('advance_order_id', '=', $advanceOrder->id)->where('shipped_quantity', '>', '0')->whereColumn('shipped_quantity', '<', 'quantity')->count();
				$advanceLines = $advanceOrder->advance_lines()->get();
				$noShipmentCnt = $advanceLines->where('shipped_quantity', '=', '0')->count();

				
				if($noShipmentCnt == $totalLinesCnt)
					$status = 'Approved';
				elseif($completeShipmentCnt == $totalLinesCnt)
					$status = 'Complete';
				elseif($partialShipmentCnt > 0 || $completeShipmentCnt != 0)	
					$status = 'Partially Shipped';

				$advanceOrder->status = $status;
				$advanceOrder->amount = $navAdvanceOrder->getAmount();
				$advanceOrder->save();
			}
		}
		else//status update in Nav 
		{
			$advanceOrder->status = $navAdvanceOrder->getStatus();	
			$advanceOrder->amount = $navAdvanceOrder->getAmount();
			$advanceOrder->save();

			$navAdvanceLines = $navAdvanceOrder->getSalesLines()->getSales_Order_Line();
			if($navAdvanceLines)
			{
				if(!is_array($navAdvanceLines))
				{
					$navAdvanceLines = [$navAdvanceLines];
				}
				foreach($navAdvanceLines as $navAdvanceLine)
				{
					$line = $advanceOrder->advance_lines()->where('nav_line_no','=',$navAdvanceLine->getLine_No())->first();
					$line->nav_po_no = $navAdvanceLine->getPurchase_Order_No();
							$line->save();
				}
			}
		}

		if($relatedAO)//#635
		{
			if($relatedAO->status == 'Complete')
			{
				$distributor = $relatedAO->advance_blanket_order->distributor;
				$distributor_invoice_series = $distributor->invoice_series + 1;
				$distributor_code = substr($distributor->nav_id,5);
				$temp = str_pad($distributor_invoice_series, 5, '0', STR_PAD_LEFT);

				if (date('m') <= 3) 
				    $year = date('Y');
				else
				    $year = date('Y') + 1;
				$year1 = $year-1;
				$fiscalyear = $year1.'-'.$year;
				$ros_inv_num = 'PI'.'/'.$fiscalyear.'/'.$distributor_code.'/'.$temp;
				if($relatedAO->advance_blanket_order->ros_invoice_number == null)
				{
					$relatedAO->advance_blanket_order->ros_invoice_number = $ros_inv_num;
					$relatedAO->advance_blanket_order->save();
					$distributor->invoice_series = $distributor_invoice_series;
					$distributor->save();
				}
			}	
		}

		return [$advanceOrder,$alert];
	}
	public function refresh($UUId)
	{
		$advanceOrder = AdvanceOrder::where('UUID', '=', $UUId)->get()->first();
		$customer = $advanceOrder->orderable()->get()->first();
		$company = Company::find($customer->company_id);
		$advanceOrder = $this->update($company, $customer, $advanceOrder->id);
		/*$navAdvanceOrder = $this->salesOrderService->refresh($company, $advanceOrder->navision_order_number);
		if(!$navAdvanceOrder)//order is completely shipped
		{
			$psiPresent = $this->psiService->getSO($company, $advanceOrder->navision_order_number);
			if($psiPresent)
			{
				$advanceOrder->status = 'Shipped';	
			}
			else
			{
				$advanceOrder->status = 'Cancelled';
			}
			$advanceOrder->save();
		}
	/*	if($navAdvanceOrder->getStatus() == 'Released')
		{
			$this->update($company, $customer, $advanceOrder->id);
		}*/
		/*else
		{
			$advanceOrder->status = $navAdvanceOrder->getStatus();
			$advanceOrder->save();
		}*/
	}
	public function listCompanyAOs($companyId, Array $params = [])
	{
		$company = Company::find($companyId);
		$customer = null;
		if(array_has($params, 'selectedCustomerType') && array_has($params, 'selectedCustomerId'))
		{
			if($params['selectedCustomerType'] == "Distributor")
	            $customer = Distributor::find($params['selectedCustomerId']);
	        elseif($params['selectedCustomerType'] == "Dealer")
	            $customer = Dealer::find($params['selectedCustomerId']);
		}
		if($customer)
			$company = $customer;
		if(array_has($params,'page'))
		{
			$advanceOrders = $company->advanceOrders()->when($params, function ($query) use ($params){
				if(array_has($params, 'status'))
				{
					$query->where('status', '=', $params['status']);
				}
				if(array_has($params,'so'))
				{
					$query->where('navision_order_number', '=', $params['so']);
				}
			})->orderBy('created_at','desc')->paginate($params['per_page']);
		}
		else
		{
			$advanceOrders = $company->advanceOrders()->when($params, function ($query) use ($params){
				if(array_has($params, 'status'))
				{
					$query->where('status', '=', $params['status']);
				}
				if(array_has($params,'so'))
				{
					$query->where('navision_order_number', '=', $params['so']);
				}
			})->orderBy('created_at','desc')->get();
		}
		return $advanceOrders;
	}
	public function all($company, $customer, Array $params = [])
	{
		if(array_has($params,'page'))
		{
			$advanceOrders = $customer->advanceOrders()->when($params, function ($query) use ($params){
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
				if(array_has($params,'so'))
				{
					$query->where('navision_order_number','like',"%{$params['so']}%");
				}
			})->orderBy('created_at','desc')->paginate($params['per_page']);
		}
		else
		{
			$advanceOrders = $customer->advanceOrders()->when($params, function ($query) use ($params){
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
			})->orderBy('created_at','desc')->get();
		}
		return $advanceOrders;
	}
	public function find($company, $customer, $advanceOrderId)
	{
		return $customer->advanceOrders()->find($advanceOrderId);
	}
	public function findInTransitOrder(User $loggedInUser, $orderId)
	{
		$alert = '';
		$blanketOrder = AdvanceBlanketOrder::find($orderId);
		$orderCompanyId = $blanketOrder->orderable->company->id;
		$orderCustomerId = $blanketOrder->orderable->id;
		$advanceOrders = $blanketOrder->advance_orders;
		$orderDistributorId = $blanketOrder->distributor_id;
		foreach($advanceOrders as $advanceOrder)
		{
			if($advanceOrder->distributor_id == null && $advanceOrder->super_stockist_id == null)
			{
				list($advanceOrder,$alert) = $this->update($advanceOrder->orderable->company, $advanceOrder->orderable, $advanceOrder->id);
			}
			else
			{
				$relatedBO = AdvanceBlanketOrder::where('related_ao_id','=',$advanceOrder->id)->first();
				if($relatedBO)
				{
					$relatedAO = $relatedBO->advance_orders()->first();
					if($relatedAO->navision_order_number)
					{
						list($relatedAO,$alert) = $this->update($relatedAO->orderable->company, $relatedAO->orderable, $relatedAO->id);
					}
				}
				$alert = '';//how do we handle this at blanket order level check #248
			}
		}
		$blanketOrder = AdvanceBlanketOrder::find($orderId);
		if($loggedInUser->hasRole('superadmin'))
		{
			return [$blanketOrder,$alert];
        }
        else if($loggedInUser->hasRole('depotManager'))
		{
			return [$blanketOrder,$alert];
        }
        else if($loggedInUser->hasRole('apiUser'))
		{
			return [$blanketOrder,$alert];
        }
    	else if($loggedInUser->hasRole('companyAdmin'))
        {
            $companyId = $loggedInUser->company_id;
            if($orderCompanyId == $companyId)
            {	
            	return [$blanketOrder,$alert];
            }
        }
        else if($loggedInUser->hasRole('distributorAdmin'))
        {
            $distributorId = $loggedInUser->distributor_id;
            if($orderCustomerId == $distributorId)
            	return [$blanketOrder,$alert];
            if($orderDistributorId == $distributorId)
            	return [$blanketOrder,$alert];
        }
        else if($loggedInUser->hasRole('superStockistAdmin'))
        {
            $superStockistId = $loggedInUser->super_stockist_id;
            if($orderCustomerId == $superStockistId)
            	return [$blanketOrder,$alert];
         //   if($orderDistributorId == $distributorId)
          //  	return [$blanketOrder,$alert];
        }
        else if($loggedInUser->hasRole('dealerAdmin'))
        {
            $dealerId = $loggedInUser->dealer_id;
        	if($orderCustomerId == $dealerId)
        		return [$blanketOrder,$alert];
        }
		throw new ViewOrderPermissionDeniedException;	
	}
	public function findOrder(User $loggedInUser, $advanceOrderId)
	{
		$advanceOrder = AdvanceOrder::find($advanceOrderId);
		$advanceOrderCompanyId = $advanceOrder->orderable->company->id;
		$advanceOrderCustomerId = $advanceOrder->orderable->id;
		list($advanceOrder,$alert) = $this->update($advanceOrder->orderable->company, $advanceOrder->orderable, $advanceOrderId);
		if($loggedInUser->hasRole('superadmin'))
		{
        	return [$advanceOrder,$alert];
        }
        $companyId = $loggedInUser->company_id;
        $distributorId = $loggedInUser->distributor_id;
        $dealerId = $loggedInUser->dealer_id;

    	if($loggedInUser->can('viewAdvanceOrders'))
        {
            if($advanceOrderCompanyId == $companyId)
            	return [$advanceOrder,$alert];
            if($advanceOrderCustomerId == $distributorId)
           		return [$advanceOrder,$alert];
            if($advanceOrderCustomerId == $dealerId)
           		return [$advanceOrder,$alert];
        }
		throw new ViewOrderPermissionDeniedException;	
	}
	public function getAdvanceItems($customer)
	{
		return $advanceItems = $customer->advanceItems()->active()->get();
	}
	public function deleteCustomerAdvanceItems($company, $customerType, Array $params, User $loggedInUser)
	{
		//this function misht not be used at all, since the ui approach is changed, need to verify
		$sender = $loggedInUser;
		$advanceItems = AdvanceItem::whereIn('id', $params['item_Ids'])->get();

		foreach ($advanceItems as $advanceItem)
		{
			$recipients = [];
			$toEmailIds = [];
			$customerId = $advanceItem->advanceable->id;
			if($customerType == 'distributors')
				$customer = Distributor::find($customerId);
			if($customerType == 'dealers')
				$customer = Dealer::find($customerId);

			$advanceItemProductName = $this->getProductDisplayName($company, $customer, $advanceItem->product->category->code, $advanceItem->product->collection->code, $advanceItem->product->colour->code);
			$advanceItem->forceDelete();
			if($loggedInUser->hasRole('superadmin') || $loggedInUser->hasRole('companyAdmin'))
			{
				if($customerType == 'distributors')
				{
					$query = User::where('distributor_id','=',$customerId);
					$query->whereHas('roles',function($q) {
							$q->where('name','=','distributorAdmin');
						});
					$users = $query->get();
					
					foreach ($users as $user) {
						if($user->communication_email)
							$toEmailIds[] = $user->communication_email;
						$recipients[] = $user;
					}
				}//add Customer Admin Email Ids
				if($customerType == 'dealers')
				{
					$query = User::where('dealer_id','=',$customerId);
					$query->whereHas('roles',function($q) {
							$q->where('name','=','dealerAdmin');
						});
					$users = $query->get();
					
					foreach ($users as $user) {
						if($user->communication_email)
							$toEmailIds[] = $user->communication_email;
						$recipients[] = $user;
					}
				}//add Customer Admin Email Ids
				foreach ($recipients as $recipient) 
				{
					$notification = new Notification;
					$notification->title = 'Advance Cart Item - '. $advanceItemProductName. ' deleted.';
					$notification->text = 'Admin has deleted Item - '. $advanceItemProductName. ' from your Advance Cart.';
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
				$Qmessage = (new AdvanceCartItemDelete($advanceItemProductName))->onQueue($queue);
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
	}
	public function deleteAdvanceItems(Array $params, $customer, User $loggedInUser, $customerType)
	{
		$sender = $loggedInUser;
		$advanceItems = $customer->advanceItems()->whereIn('id', $params['item_Ids'])->get();
		$customer->advanceItems()->whereIn('id', $params['item_Ids'])->forceDelete();
		$queuePrefix = config('brand.queue_prefix');
		$queue = $queuePrefix.'.emails';
		foreach ($advanceItems as $advanceItem)
		{
			$toEmailIds = [];
			$recipients = [];
			$advanceItemProductName = $this->getProductDisplayName($customer->company, $customer, $advanceItem->product->category->code, $advanceItem->product->collection->code, $advanceItem->product->colour->code);
			if($loggedInUser->hasRole('superadmin') || $loggedInUser->hasRole('companyAdmin'))
			{
				if($customerType == 'distributors')
				{
					$query = User::where('distributor_id','=',$customer->id);
					$query->whereHas('roles',function($q) {
							$q->where('name','=','distributorAdmin');
						});
					$users = $query->get();
					
					foreach ($users as $user) {
						if($user->communication_email)
							$toEmailIds[] = $user->communication_email;
						$recipients[] = $user;
					}
				}//add Customer Admin Email Ids
				if($customerType == 'dealers')
				{
					$query = User::where('dealer_id','=',$customer->id);
					$query->whereHas('roles',function($q) {
							$q->where('name','=','dealerAdmin');
						});
					$users = $query->get();
					
					foreach ($users as $user) {
						if($user->communication_email)
							$toEmailIds[] = $user->communication_email;
						$recipients[] = $user;
					}
				}//add Customer Admin Email Ids
				foreach ($recipients as $recipient) 
				{
					$notification = new Notification;
					$notification->title = 'Advance Cart Item - '. $advanceItemProductName. ' deleted.';
					$notification->text = 'Admin has deleted Item - '. $advanceItemProductName. ' from your Advance Cart.';
					$notification->sender_id = $sender->id;
					$notification->recipient_id = $recipient->id;
					$notification->author()->associate($sender);
					$notification->sender()->associate($sender);
					$notification->recipient()->associate($recipient);
					$notification->save();
					event(new Notifications($notification));
				}
				$Qmessage = (new AdvanceCartItemDelete($advanceItemProductName))->onQueue($queue);
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
	}
	public function addToAdvanceCart($customer, $company, Array $params)
	{
		$items = [];
		$advanceItems = $params['advance_items'];
		foreach ($advanceItems as $advanceItem)
		{
			$item = new AdvanceItem;
			$item->product_id = $advanceItem['product_id'];
			$item->order_quantity = $advanceItem['order_quantity'];
			$item->rate_type = 'RL';
			$item->unit_price = $advanceItem['unit_price'];
			$item->discount = $advanceItem['discount'];
			$item->discounted_price = $advanceItem['discounted_unit_price'];
			$item->total_price = $advanceItem['total_price'];
			$item->tax_type_code = $advanceItem['tax_type_code'];
			$item->tax_percent = $advanceItem['tax_percent'];
			$item->tax_amount = $advanceItem['tax_amount'];
			$item->net_amount = $advanceItem['net_amount'];
			$item->expires_at = Carbon::now()->addSeconds(config('rosetta.advance_cart_duration'));
			$item->company()->associate($customer->company);
			$items[] = $item;
		}
		$customer->advanceItems()->saveMany($items);
		return $customer->advanceItems()->active()->get();
		//return $customer->advanceItems;
	}
	public function blockAdvanceItems($customer, $company, Array $params)
	{
		$blockItems = $params['block_items'];
		//$UUId = Uuid::uuid4();
		foreach ($blockItems as $blockItem)
		{
			$UUId = Uuid::uuid4();
			if(array_has($blockItem,'cart_id'))//Items from Advance Cart are blocked
			{
				$companyId = $customer->company->id;
				$advanceCartItem = $customer->advanceItems()->find($blockItem['cart_id']);
				$productId = $advanceCartItem->product_id;

				$current_time = Carbon::now()->format('U');
				$already_blocked_items = $customer->blockedAdvanceItems()
												  ->where('product_id','=',$productId)
												  ->where('cool_down_start_time','<',$current_time)
												  ->where('cool_down_end_time','>',$current_time)
												  ->count();

				
				if($already_blocked_items > 0)
				{
					$e = new BlockItemDeniedException;
					$e->cool_down_end_time = $customer->blockedAdvanceItems()->where('product_id','=',$productId)
												  ->where('cool_down_start_time','<',$current_time)
												  ->where('cool_down_end_time','>',$current_time)
												  ->latest()->value('cool_down_end_time');
					throw $e;
				}

				if($advanceCartItem->distributor_id == null)
				{
					
				/*	$ledgerEntries = $this->inventoryService->getLedgerEntries(Auth::user(),$companyId,$productId, $location_code);
			        $ledgerEntries = $this->inventoryService->calculateStockAgainstBatch($productId,$ledgerEntries,$customer, $companyId, "BlockFromCart");			        

					foreach ($ledgerEntries as $ile)//post items are added to cart and if the stock in Company changes and the Qty that was added to cart now becomes more than available quantity
					{
						$lot_no = $ile->getLot_No();
						if($lot_no ==  $advanceCartItem->nav_intransit_po)
						{
							$remaining_quantity = (float)$ile->getRemaining_Quantity();
							if($remaining_quantity < $advanceCartItem->order_quantity)
							{
								throw new InsufficientQuantityToBlockException; 
							}
						}
					}
				*///commented as part of #414
			
					//added as part of #414
					$stock_point_id = $advanceCartItem->stock_point_id;
					$location_code = StockPoint::where('id', '=', $stock_point_id)->first()->code;
					$product = Product::find($productId);
					//$remaining_quantity = round((float)$this->ILEService->getBatchSpecificStock($customer->company,$product->nav_id,$advanceCartItem->nav_intransit_po,true, $location_code),2);//added as part of #414

					$remaining_quantity = round((float)$this->pendingPurchaseOrdersService->getPOSpecificStock($customer->company,$product->nav_id,$advanceCartItem->nav_intransit_po,true, $location_code),2);//added as part of #414

					if($remaining_quantity < $advanceCartItem->order_quantity)
					{
						throw new InsufficientQuantityToBlockException; 
					}
				}
				else//this needs to be verified - won't be a case in advance ordering
				{
					$distributor = Distributor::find($advanceCartItem->distributor_id);

					$distributorStock = $distributor->stocks
											->where('product_id', '=', $advanceCartItem->product_id)
											->where('batch_no', '=', $advanceCartItem->batch_no)
											->first();

					if(!$distributorStock || $distributorStock->quantity < $advanceCartItem->order_quantity)
					{
						throw new InsufficientQuantityToBlockException;
					}
				}
				$item = new BlockedAdvanceItem;
				$item->product_id = $advanceCartItem->product_id;//['product_id'];
				$item->nav_intransit_po = $advanceCartItem->nav_intransit_po;
				$item->order_quantity = $advanceCartItem->order_quantity;
				$item->rate_type = $advanceCartItem->rate_type;
				$item->unit_price = $advanceCartItem->unit_price;
				$item->discount = $advanceCartItem->discount;
				$item->onlineDiscount = $advanceCartItem->onlineDiscount;
				$item->stock_point_id = $advanceCartItem->stock_point_id;
				$item->UUId = $UUId;
				$item->eta = $advanceCartItem->eta;
				if(array_has($advanceCartItem, 'distributor_id') && $advanceCartItem['distributor_id'] != null)
					$item->distributor_id = $advanceCartItem->distributor_id;
				$item->expires_at = Carbon::now()->addSeconds(config('rosetta.blocked_duration'));
				$item->cool_down_start_time = Carbon::now()->addSeconds(config('rosetta.cool_down_start_time'));//#820
				$item->cool_down_end_time = Carbon::now()->addSeconds(config('rosetta.cool_down_end_time'));//#820
				$item->company()->associate($customer->company);
				$items[] = $item;	
				$advanceCartItem->delete();
			}
			else//Items from available list are blocked
			{
				$current_time = Carbon::now()->format('U');
				$already_blocked_items = $customer->blockedAdvanceItems()
												  ->where('product_id','=',$blockItem['product_id'])
												  ->where('cool_down_start_time','<',$current_time)
												  ->where('cool_down_end_time','>',$current_time)
												  ->count();

				
				if($already_blocked_items > 0)
				{
					$e = new BlockItemDeniedException;
					$e->cool_down_end_time = $customer->blockedAdvanceItems()
												->where('product_id','=',$blockItem['product_id'])
												->where('cool_down_start_time','<',$current_time)
												->where('cool_down_end_time','>',$current_time)
												->latest()->value('cool_down_end_time');
					throw $e;
				}
				$item = new BlockedAdvanceItem;
				$item->product_id = $blockItem['product_id'];
				$item->nav_intransit_po = $blockItem['intransit_po'];
				$item->eta = $blockItem['eta'];
				$item->order_quantity = $blockItem['order_quantity'];
				$item->rate_type = $blockItem['rate_type'];
				$item->unit_price = $blockItem['unit_price'];
				$item->discount = $blockItem['discount'];
				$item->expires_at = Carbon::now()->addSeconds(config('rosetta.blocked_duration'));
				$item->cool_down_start_time = Carbon::now()->addSeconds(config('rosetta.cool_down_start_time'));//#820
				$item->cool_down_end_time = Carbon::now()->addSeconds(config('rosetta.cool_down_end_time'));//#820
			//	var_dump($item->expires_at, $item->cool_down_till);
			//	exit(0);
				$item->company()->associate($customer->company);
				if(array_has($blockItem,'onlineDiscount'))
					$item->onlineDiscount = $blockItem['onlineDiscount'];
				if(array_has($blockItem, 'stock_point_source'))
				{
					if($blockItem['stock_point_source'] == 'StockPoint')
					{
						if(array_has($blockItem, 'stock_point_code'))
						{
							$stockPoint = StockPoint::where('code', '=', $blockItem['stock_point_code'])->first();
							$item->stock_point_id = $stockPoint->id;
							$item->UUId = $UUId;
						}
					}
					else
					{
						if(array_has($blockItem, 'distributor_id') && $blockItem['distributor_id'] != null)
							$item->distributor_id = $blockItem['distributor_id'];
					}
				}
				$items[] = $item;
			}
		}
		$customer->blockedAdvanceItems()->saveMany($items);
		
		foreach($items as $item)
		{
			if($item->distributor_id == null)//if co-distributor stock is blocked no need to block on other Tenants cause Co-distributors will not be on different Tenants
			{
				$current_tenant = $customer->company->getSettingsForType('current_tenant')->get('tenant_code');
				$location_code = StockPoint::where('id', '=', $item->stock_point_id)->first()->code;
				$product = Product::find($item->product_id);
				$depotItem=[];
				$depotItem['nav_intransit_po'] = $item->nav_intransit_po;
				$depotItem['order_quantity'] = $item->order_quantity;
				$depotItem['rate_type'] = $item->rate_type;
				$depotItem['unit_price'] = $item->unit_price;
				$depotItem['discount'] = $item->discount;
				$depotItem['expires_at'] = $item->expires_at;
				$depotItem['cool_down_start_time'] = $item->cool_down_start_time;
				$depotItem['cool_down_end_time'] = $item->cool_down_end_time;
				$depotItem['product_nav_id'] = $product->nav_id;//product_id (row_id) may be different on different Tenants based on availability of Products on each Tenant so pass nav_id
				$depotItem['stock_point_code'] = $location_code;
				$depotItem['company_name'] = $customer->company->name;
			//	$depotItem['comment'] = 'Blocked on ROS';
				$depotItem['comment'] = 'Blocked on ' . $current_tenant. ' by Customer : ' . $customer->name. ' ('. $customer->nav_id. ')';


				$depotItem['distributor_id'] = null;//Disributor on one Tenant will/might not be present on other Tenants
				$company = $customer->company;
				$depotItem['UUId'] = $item->UUId;
				$tenants = $customer->company->getSettingsForType('tenants')->get('tenant_codes');//
				if($tenants != null)
					$this->depotService->blockAdvanceItem($depotItem, $company);
			}
		}
		return $customer->blockedAdvanceItems()->active()->get();
	}
	public function getBlockedAdvanceItems($customer)
	{
		return $blockedItems = $customer->blockedAdvanceItems()->active()->get();
	}
	public function unBlockAdvanceItems(Array $params, $customer)
	{
	//	$customer->blockedItems()->whereIn('id', $params['blocked_Ids'])->delete();
		$blockedItems = $customer->blockedAdvanceItems()->whereIn('id', $params['blocked_Ids'])->get();
		foreach($blockedItems as $blockedItem)
		{
			$UUId = $blockedItem->UUID;
			$company = $customer->company;
			$blockedItem->delete();
			if($UUId != '' || $UUId != null)
			{
				$this->depotService->unBlockAdvanceItem($UUId, $company);
			}
		}
	}
	public function unBlock_acrossTenants(Array $params, $companyId)//superadmin
	{
		$company = Company::find($companyId);
		$blockedItems = $company->blockedAdvanceItems()->whereIn('id', $params['blocked_Ids'])->get();
		foreach($blockedItems as $blockedItem)
		{
			$UUId = $blockedItem->UUID;
			$blockedItem->delete();
			if($UUId != '' || $UUId != null)
			{
				$this->depotService->unBlockAdvanceItem($UUId, $company);
			}
		}
	}
	public function addIntransitToAdvanceCart($customer, $company, Array $params)
	{
		$advanceItems = $params['advance_items'];
		$items = [];
		foreach ($advanceItems as $advanceItem)
		{
			if(!array_has($advanceItem,'blocked_id'))//Add To Cart
			{
				$item = new AdvanceItem;
				$item->product_id = $advanceItem['product_id'];
				$item->order_quantity = $advanceItem['order_quantity'];
				$item->rate_type = $advanceItem['rate_type'];
				$item->unit_price = $advanceItem['unit_price'];
				if(array_has($advanceItem,'onlineDiscount'))
					$item->onlineDiscount = $advanceItem['onlineDiscount'];
				$item->discount = $advanceItem['discount'];
				$discounted_price = $advanceItem['unit_price'] - ($advanceItem['unit_price'] * $advanceItem['discount'] /100);
				$item->discounted_price = $discounted_price;
				$total_amount = $discounted_price * $advanceItem['order_quantity'];
				$item->total_price = $total_amount;

				$product = Product::find($advanceItem['product_id']);
				$tax = $customer->company->region->taxes()->where('category_code','=',$product->category->code)->where('collection_code','=',$product->collection->code)->first();
				if(!$tax)
				{
					$tax = $customer->company->region->taxes()->where('category_code','=',$product->category->code)->first();
				}
				if($tax)
				{
					$tax_percent = $tax->tax_percent;
					$tax_type_code = $tax->tax_type_code;
				}
				$item->tax_type_code = $tax_type_code;
				$item->tax_percent = $tax_percent;
	            $tax_amount = ($discounted_price * $tax_percent)/100 * $advanceItem['order_quantity'];
	            $net_amount = $total_amount + $tax_amount;

				$item->tax_amount = $tax_amount;
				$item->net_amount = $net_amount;
				$item->eta = $advanceItem['eta'];
				$item->nav_intransit_po = $advanceItem['intransit_po'];
				$item->expires_at = Carbon::now()->addSeconds(config('rosetta.advance_cart_duration'));
				if(array_has($advanceItem, 'stock_point_source'))
				{
					if($advanceItem['stock_point_source'] == 'StockPoint')
					{
						if(array_has($advanceItem, 'stock_point_code'))
						{
							$stockPoint = StockPoint::where('code', '=', $advanceItem['stock_point_code'])->first();
							$item->stock_point_id = $stockPoint->id;
						}
					}
				}
				/** Add distributor ID and Super Stokist ID in advance cart Start*/
				$item->distributor_id = (!empty($customer->distributor_id))?$customer->distributor_id:null;
				$item->super_stockist_id = (!empty($customer->distributor->super_stockist_id))?$customer->distributor->super_stockist_id:null;
				/** Add distributor ID and Super Stokist ID in advance cart END*/
				$item->company()->associate($customer->company);
				$items[] = $item;
			}
			else//Move To Advance Cart from Advance Blocked Items
			{
				$unBlockItem = $customer->blockedAdvanceItems()->where('id', '=', $advanceItem['blocked_id'])->first();
				$item = new AdvanceItem;
				$item->company_id = $unBlockItem['company_id'];
				$item->product_id = $unBlockItem['product_id'];
				$item->nav_intransit_po = $unBlockItem['nav_intransit_po'];
				$item->order_quantity = $unBlockItem['order_quantity'];
				$item->rate_type = $unBlockItem['rate_type'];
				$item->unit_price = $unBlockItem['unit_price'];
				$item->discount = $unBlockItem['discount'];
				$item->stock_point_id = $unBlockItem['stock_point_id'];
				if(array_has($unBlockItem,'distributor_id'))
					$item->distributor_id = $unBlockItem['distributor_id'];
				if(array_has($unBlockItem,'onlineDiscount'))
					$item->onlineDiscount = $unBlockItem['onlineDiscount'];

				$discounted_price = $unBlockItem['unit_price'] - ($unBlockItem['unit_price'] * $unBlockItem['discount'] /100);
				$item->discounted_price = $discounted_price;
				$total_amount = $discounted_price * $unBlockItem['order_quantity'];
				$item->total_price = $total_amount;
				$product = Product::find($unBlockItem['product_id']);
				$tax = $customer->company->region->taxes()->where('category_code','=',$product->category->code)->where('collection_code','=',$product->collection->code)->first();
				if(!$tax)
				{
					$tax = $customer->company->region->taxes()->where('category_code','=',$product->category->code)->first();
				}
				if($tax)
				{
					$tax_percent = $tax->tax_percent;
					$tax_type_code = $tax->tax_type_code;
				}
				$item->tax_type_code = $tax_type_code;
				$item->tax_percent = $tax_percent;
	            $tax_amount = ($discounted_price * $tax_percent)/100 * $unBlockItem['order_quantity'];
	            $net_amount = $total_amount + $tax_amount;

				$item->tax_amount = $tax_amount;
				$item->net_amount = $net_amount;
				$item->eta = $unBlockItem['eta'];
				$item->expires_at = $unBlockItem['expires_at'];
			
				$items[] = $item;
				if(array_has($unBlockItem,'UUID'))
				{
					$UUId = $unBlockItem['UUID'];
					$this->depotService->unBlockAdvanceItem($UUId, $customer->company);
				}
				$unBlockItem->delete();
			}
		}
		$customer->advanceItems()->saveMany($items);
		return $customer->advanceItems()->active()->get();
	}
	public function getMessages($company, $customer, $itemId, User $user)
	{
		$advanceItem = $customer->advanceItems()->find($itemId);
		return $advanceItem;
	}
	public function addMessage($company, $customer, $customerType, $itemId, Array $params, User $user)
	{
		$advanceItem = $customer->advanceItems()->find($itemId);
		$message = new Message;
		$message->text = $params['text'];
		$message->author()->associate($user);
		$advanceItem->messages()->save($message);
		$advanceItemProductName = $this->getProductDisplayName($company, $customer, $advanceItem->product->category->code, $advanceItem->product->collection->code, $advanceItem->product->colour->code);
		$toEmailIds = [];
		$recipients = [];
		$adminUser = '';
		$sender = $user;
		if($user->distributor_id != null || $user->dealer_id != null)
		{
			$query = User::where('company_id','=',$company->id);
			$query->whereHas('roles',function($q) {
					$q->where('name','=','companyAdmin');
				});
			$users = $query->get();
	
			foreach ($users as $user) {
				if($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
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
			$adminUser = 'N';
		}
		else if($user->company_id != null || ($user->company_id == null && $user->distributor_id == null && $user->dealer_id == null))
		{	
			if($customerType == 'distributors')
			{
				$query = User::where('distributor_id','=',$customer->id);
				$query->whereHas('roles',function($q) {
						$q->where('name','=','distributorAdmin');
					});
				$users = $query->get();
				
				foreach ($users as $user) {
					if($user->communication_email)
						$toEmailIds[] = $user->communication_email;
					$recipients[] = $user;
				}
			}//add Customer Admin Email Ids
			if($customerType == 'super_stockists')
			{
				$query = User::where('super_stockist_id','=',$customer->id);
				$query->whereHas('roles',function($q) {
						$q->where('name','=','superStockistAdmin');
					});
				$users = $query->get();
				
				foreach ($users as $user) {
					if($user->communication_email)
						$toEmailIds[] = $user->communication_email;
					$recipients[] = $user;
				}
			}//add Customer Admin Email Ids
			if($customerType == 'dealers')
			{
				$query = User::where('dealer_id','=',$customer->id);
				$query->whereHas('roles',function($q) {
						$q->where('name','=','dealerAdmin');
					});
				$users = $query->get();
				
				foreach ($users as $user) {
					if($user->communication_email)
						$toEmailIds[] = $user->communication_email;
					$recipients[] = $user;
				}
			}//add Customer Admin Email Ids
			$adminUser = 'Y';
		}
		foreach ($recipients as $recipient) 
		{
			$notification = new Notification;
			$notification->title = 'Message on Advance Cart Item - '. $advanceItemProductName;
			$notification->text = $params['text'];
			$notification->sender_id = $sender->id;
			$notification->recipient_id = $recipient->id;
			$notification->author()->associate($sender);
			$notification->sender()->associate($sender);
			$notification->recipient()->associate($recipient);
			$notification->save();
		//	dd($notification->sender->id);
			event(new Notifications($notification));
		}
		
   		//$custType = get_class($advanceItem->advanceable) == Distributor::class ? 'Distributor' : 'Dealer';
   		if(get_class($advanceItem->advanceable) == Distributor::class)
   			$custType = 'Distributor';
   		elseif(get_class($advanceItem->advanceable) == Dealer::class)
   			$custType = 'Dealer';
   		elseif(get_class($advanceItem->advanceable) == SuperStockist::class)
   			$custType = 'Super Stockist';
		$queuePrefix = config('brand.queue_prefix');
		$queue = $queuePrefix.'.emails';
		$Qmessage = (new AdvanceCartItemMessage($advanceItem, $message, $user, $adminUser, $advanceItemProductName, $company->region->name, $company->name, $custType, $customer->name))->onQueue($queue);
		try
	    {
			if(!empty($toEmailIds))
	        	Mail::to($toEmailIds)->queue($Qmessage);
        }
       	catch(ConnectionException $e)
       	{
       		throw new RedisException;
	    }
		return $message;
	}
	public function updateAdvanceCartItem($company, $customer, $customerType, $itemId, Array $params, User $user)
	{
		$sendEmail = false;
		$sendQuantityEmail = false;
		$sendDiscountEmail = false;
		$recipients = [];
		$sender = $user;
		$advanceItem = $customer->advanceItems()->find($itemId);
		$currentQuantity = $advanceItem->order_quantity;
		$currentDiscount = $advanceItem->discount;
		if(array_has($params, 'eta'))
		{
			$advanceItem->eta = $params['eta'];
			$date = Carbon::createFromTimestampUTC($params['eta'])->setTimeZone('Asia/Kolkata')->format('d M Y');
			$sendEmail = true;
		}
		if(array_has($params, 'discount') && array_has($params, 'order_quantity') && $currentDiscount != $params['discount'] && $currentQuantity != $params['order_quantity'])
		{
			$advanceItem->discount = $params['discount'];
			$advanceItem->order_quantity = $params['order_quantity'];
			$advanceItem->discounted_price = $params['discounted_unit_price'];
			$advanceItem->total_price = $params['total_price'];
			$advanceItem->tax_amount = $params['tax_amount'];
			$advanceItem->net_amount = $params['net_amount'];
			$sendDiscountEmail = true;
			$sendQuantityEmail = true;
		}
		else if(array_has($params, 'discount') && $currentDiscount != $params['discount'])
		{
			$advanceItem->discount = $params['discount'];
			$advanceItem->total_price = $params['total_price'];
			$advanceItem->discounted_price = $params['discounted_unit_price'];
			$advanceItem->tax_amount = $params['tax_amount'];
			$advanceItem->net_amount = $params['net_amount'];
			$sendDiscountEmail = true;
		}
		else if(array_has($params, 'order_quantity') && $currentQuantity != $params['order_quantity'])
		{
			$advanceItem->order_quantity = $params['order_quantity'];
			$advanceItem->total_price = $params['total_price'];
			$advanceItem->tax_amount = $params['tax_amount'];
			$advanceItem->net_amount = $params['net_amount'];
			$sendQuantityEmail = true;
		}
		$advanceItem->save();
		$advanceItemProductName = $this->getProductDisplayName($company, $customer, $advanceItem->product->category->code, $advanceItem->product->collection->code, $advanceItem->product->colour->code);
		$toEmailIds = [];
		if($customerType == 'distributors')
		{
			$query = User::where('distributor_id','=',$customer->id);
			$query->whereHas('roles',function($q) {
					$q->where('name','=','distributorAdmin');
				});
			$users = $query->get();
			
			foreach ($users as $user) {
				if($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
			}
		}//add Customer Admin Email Ids
		if($customerType == 'dealers')
		{
			$query = User::where('dealer_id','=',$customer->id);
			$query->whereHas('roles',function($q) {
					$q->where('name','=','dealerAdmin');
				});
			$users = $query->get();
			
			foreach ($users as $user) {
				if($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
			}
		}//add Customer Admin Email Ids
		$queuePrefix = config('brand.queue_prefix');
		$queue = $queuePrefix.'.emails';
		if($sendEmail || $sendDiscountEmail || $sendQuantityEmail)
		{
        	foreach ($recipients as $recipient) 
			{
				$notification = new Notification;
				if($sendDiscountEmail && $sendQuantityEmail)
				{
					$notification->title = 'Discount & Quantity updated on Advance Cart Item';
					$Qmessage = (new AdvanceCartItemQuantityDiscount($advanceItem, $currentQuantity, $currentDiscount, $advanceItemProductName))->onQueue($queue);
					$notification->text = 'Discount on Advance Cart Item - '. $advanceItemProductName . ' changed to ' .$advanceItem->discount. '% from '. $currentDiscount. '% and Quantity changed to ' .$advanceItem->order_quantity. ' SFT from ' .$currentQuantity. ' SFT';
				}
				else if($sendDiscountEmail)
				{
					$notification->title = 'Discount updated on Advance Cart Item';
					$Qmessage = (new AdvanceCartItemDiscount($advanceItem, $currentDiscount, $advanceItemProductName))->onQueue($queue);
					$notification->text = 'Discount on Advance Cart Item - '. $advanceItemProductName . ' changed to ' .$advanceItem->discount. '% from '. $currentDiscount. '%';
				}
				else if($sendQuantityEmail)
				{
					$notification->title = 'Quantity updated on Advance Cart Item';
					$Qmessage = (new AdvanceCartItemQuantity($advanceItem, $currentQuantity, $advanceItemProductName))->onQueue($queue);
					$notification->text = 'Quantity on Advance Cart Item - '. $advanceItemProductName . ' changed to ' .$advanceItem->order_quantity. ' SFT from '. $currentQuantity. ' SFT';
				}
				else if($sendEmail)
				{
					$notification->title = 'ETA set on Advance Cart Item';
					$notification->text = 'ETA set to ' .$date. ' on Advance Cart Item - '. $advanceItemProductName;
					$Qmessage = (new AdvanceCartItemETA($advanceItem, $date, $advanceItemProductName))->onQueue($queue);
				}
				$notification->sender_id = $sender->id;
				$notification->recipient_id = $recipient->id;
				$notification->author()->associate($sender);
				$notification->sender()->associate($sender);
				$notification->recipient()->associate($recipient);
				$notification->save();
				event(new Notifications($notification));
			}
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
        return $advanceItem;
	}
	public function updateLine(Company $company, $customer, $customerType, $orderId, $lineId, User $user, Array $params)
	{
		$sendETAEmail = false;
		$recipients = [];
		$sender = $user;
		$advanceOrder = AdvanceOrder::find($orderId);
		$advanceLine = $advanceOrder->advance_lines()->find($lineId);
		$advanceLineProductName = $this->getProductDisplayName($company, $customer, $advanceLine->product->category->code, $advanceLine->product->collection->code, $advanceLine->product->colour->code);
		if(array_has($params, 'eta'))
		{
			$advanceLine->eta = $params['eta'];
			$date = Carbon::createFromTimestampUTC($params['eta'])->setTimeZone('Asia/Kolkata')->format('d M Y');
			$sendETAEmail = true;
			$advanceLine->save();
			$blanketOrder = $advanceOrder->advance_blanket_order;
			$relatedAO = $blanketOrder->relatedOrder;
			if($relatedAO)
			{
				$relatedAOLine = $relatedAO->advance_lines()->where('nav_line_no','=',$advanceLine->nav_line_no)->first();
				$relatedAOLine->eta = $params['eta'];
				$relatedAOLine->save();

				$dudCustomer = $relatedAO->advance_blanket_order->orderable;
				$query = User::where('dealer_id','=',$dudCustomer->id);
				$query->whereHas('roles',function($q) {
						$q->where('name','=','dealerAdmin');
					});
				$users = $query->get();
				$dud_user = $users->first();
				foreach ($users as $user) {
					if($user->communication_email)
						$toDealerEmailIds[] = $user->communication_email;
					$dealerRecipients[] = $user;
				}
				$query = User::where('distributor_id','=',$customer->id);
				$query->whereHas('roles',function($q) {
						$q->where('name','=','distributorAdmin');
					});
				$notifiable = (object)[
		            "order_number" => $relatedAO->advance_blanket_order->order_number,
		            "number" => $dud_user->phone,
		            "customer" => $dudCustomer->name
		        ];
		        SMSNotification::send($notifiable,new AdvanceOrderUpdatedNotification($company));//#595 Notify DUD Customer when B2B order is updated by Super/Company Admin
		        
				$product_display_name = $this->getProductDisplayName($company, $dudCustomer, $relatedAOLine->product->category->code, $relatedAOLine->product->collection->code, $relatedAOLine->product->colour->code);
				$sender = $query->first();
				$queuePrefix = config('brand.queue_prefix');
				$queue = $queuePrefix.'.emails';
			    $Qmessage = (new AdvanceOrderLineETA($relatedAOLine, $date, $product_display_name))->onQueue($queue);
			    try
    			{
			    	if(!empty($toDealerEmailIds))
    					Mail::to($toDealerEmailIds)->queue($Qmessage);
    				foreach ($dealerRecipients as $recipient) 
					{
	        			$notification = new Notification;
	        			$notification->sender_id = $sender->id;
						$notification->recipient_id = $recipient->id;
						$notification->author()->associate($sender);
						$notification->sender()->associate($sender);
						$notification->recipient()->associate($recipient);
						$notification->title = 'ETA set on Advance Order Line';
						$notification->text = 'ETA set to ' .$date. ' on Advance Order : ' . $relatedAO->order_number. ', Line : '. $product_display_name;
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
		
		$toEmailIds = [];
		if($customerType == 'distributors')
		{
			$query = User::where('distributor_id','=',$customer->id);
			$query->whereHas('roles',function($q) {
					$q->where('name','=','distributorAdmin');
				});
			$users = $query->get();
			
			foreach ($users as $user) {
				if($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
			}
		}//add Customer Admin Email Ids
		if($customerType == 'dealers')
		{
			$query = User::where('dealer_id','=',$customer->id);
			$query->whereHas('roles',function($q) {
					$q->where('name','=','dealerAdmin');
				});
			$users = $query->get();
			
			foreach ($users as $user) {
				if($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
			}
		}//add Customer Admin Email Ids
		$queuePrefix = config('brand.queue_prefix');
		$queue = $queuePrefix.'.emails';

		if($sendETAEmail)
		{
			foreach ($recipients as $recipient) 
			{
				$notification = new Notification;
				$notification->title = 'ETA set on Advance Order Line';
				$notification->text = 'ETA set to ' .$date. ' on Advance Order : ' . $advanceOrder->order_number. ', Line : '. $advanceLineProductName;
				$Qmessage = (new AdvanceOrderLineETA($advanceLine, $date, $advanceLineProductName))->onQueue($queue);
				$notification->sender_id = $sender->id;
				$notification->recipient_id = $recipient->id;
				$notification->author()->associate($sender);
				$notification->sender()->associate($sender);
				$notification->recipient()->associate($recipient);
				$notification->save();
				event(new Notifications($notification));
			}
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
		return $advanceLine;
	}
	public function getLineMessages($orderId, $lineId)
	{
		$advanceOrder = AdvanceOrder::find($orderId);
		$advanceLine = $advanceOrder->advance_lines()->find($lineId);
		return $advanceLine;
	}
	public function addLineMessage($company, $customer, $customerType, $orderId, $lineId, Array $params, User $user)
	{
		$advanceOrder = AdvanceOrder::find($orderId);
		$advanceLine = $advanceOrder->advance_lines()->find($lineId);
		$message = new Message;
		$message->text = $params['text'];
		$message->author()->associate($user);
		$advanceLine->messages()->save($message);
		$advanceLineProductName = $this->getProductDisplayName($company, $customer, $advanceLine->product->category->code, $advanceLine->product->collection->code, $advanceLine->product->colour->code);
		$toEmailIds = [];
		$recipients = [];
		$adminUser = '';
		$sender = $user;
		if($user->distributor_id != null || $user->dealer_id != null)
		{
			$query = User::where('company_id','=',$company->id);
			$query->whereHas('roles',function($q) {
					$q->where('name','=','companyAdmin');
				});
			$users = $query->get();
	
			foreach ($users as $user) {
				if($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
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
			$adminUser = 'N';
		}
		else if($user->company_id != null || ($user->company_id == null && $user->distributor_id == null && $user->dealer_id == null))
		{	
			if($customerType == 'distributors')
			{
				$query = User::where('distributor_id','=',$customer->id);
				$query->whereHas('roles',function($q) {
						$q->where('name','=','distributorAdmin');
					});
				$users = $query->get();
				
				foreach ($users as $user) {
					if($user->communication_email)
						$toEmailIds[] = $user->communication_email;
					$recipients[] = $user;
				}
			}//add Customer Admin Email Ids
			if($customerType == 'super_stockists')
			{
				$query = User::where('super_stockist_id','=',$customer->id);
				$query->whereHas('roles',function($q) {
						$q->where('name','=','superStockistAdmin');
					});
				$users = $query->get();
				
				foreach ($users as $user) {
					if($user->communication_email)
						$toEmailIds[] = $user->communication_email;
					$recipients[] = $user;
				}
			}//add Customer Admin Email Ids
			if($customerType == 'dealers')
			{
				$query = User::where('dealer_id','=',$customer->id);
				$query->whereHas('roles',function($q) {
						$q->where('name','=','dealerAdmin');
					});
				$users = $query->get();
				
				foreach ($users as $user) {
					if($user->communication_email)
						$toEmailIds[] = $user->communication_email;
					$recipients[] = $user;
				}
			}//add Customer Admin Email Ids
			$adminUser = 'Y';
		}
		foreach ($recipients as $recipient) 
		{
			$notification = new Notification;
			$notification->title = 'Message on Advance Order : ' . $advanceOrder->order_number. ', Line - '. $advanceLineProductName;
			$notification->text = $params['text'];
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
		$Qmessage = (new AdvanceOrderLineMessage($advanceLine, $message, $user, $adminUser, $advanceLineProductName))->onQueue($queue);
        try
	    {
			if(!empty($toEmailIds))
	        	Mail::to($toEmailIds)->queue($Qmessage);
        }
       	catch(ConnectionException $e)
       	{
       		throw new RedisException;
	    }
		
		return $message;
	}
	public function updateShipment($company, $customer, $orderId,Array $params)
	{
		$order = $customer->advanceOrders()->find($orderId);
		foreach($params as $param)
		{
			$line = $order->advance_lines()->find($param['id']);
			$line->received_quantity = $line->received_quantity + $param['quantity'];
			$line->save();
			$stock = $customer->stocks()
				->where('product_id','=',$line->product_id)
				->where('batch_no','=',$line->batch_no)
				->first();
			if(!$stock)
			{
				$stock = new Stock;
				$stock->product_id = $line->product_id;
				$stock->batch_no = $line->batch_no;
				$stock->quantity = 0;
				$customer->stocks()->save($stock);
			}
			$stock->quantity = $stock->quantity + $param['quantity'];
			$stock->save();
		}
		$order->load('advance_lines');
		return $order;
	}

	public function delete($company, $orderId, $reason, User $loggedInUser, $customerId, $customerType)
	{
		$message = null;
		$toEmailIds = [];
		$recipients = [];
		$sender = $loggedInUser;
		$advanceOrder = AdvanceOrder::find($orderId);
		$advanceLines = $advanceOrder->advance_lines()->get();
		$advanceLineNo = $advanceLines[0]->nav_line_no;
		foreach ($advanceLines as $advanceLine) 
		{
			if($advanceLine->nav_po_no != null)
				return [$advanceOrder, "po_created"];
		}
		$status = $this->salesOrderService->delete($company, $advanceOrder->navision_order_number, $advanceOrder->status, $advanceLineNo);
		if($status == true)
		{
			$advanceOrder->status = 'Cancelled';
			$advanceOrder->cancelation_reason = $reason;
			$advanceOrder->canceled_by = $loggedInUser->id;
			$advanceOrder->save();
			if($customerType == 'distributors')
			{
				$query = User::where('distributor_id','=',$customerId);
				$query->whereHas('roles',function($q) {
						$q->where('name','=','distributorAdmin');
					});
				$users = $query->get();
				
				foreach ($users as $user) {
					if($user->communication_email)
						$toEmailIds[] = $user->communication_email;
					$recipients[] = $user;
				}
			}//add Customer Admin Email Ids
			if($customerType == 'dealers')
			{
				$query = User::where('dealer_id','=',$customerId);
				$query->whereHas('roles',function($q) {
						$q->where('name','=','dealerAdmin');
					});
				$users = $query->get();
				
				foreach ($users as $user) {
					if($user->communication_email)
						$toEmailIds[] = $user->communication_email;
					$recipients[] = $user;
				}
			}//add Customer Admin Email Ids
			$queuePrefix = config('brand.queue_prefix');
			$queue = $queuePrefix.'.emails';
			$Qmessage = (new AdvanceOrderDelete($advanceOrder))->onQueue($queue);
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
				$notification->title = 'Cancelled Advance Order';
				$notification->text = 'Cancelled ' .$advanceOrder->order_number;
				$notification->sender_id = $sender->id;
				$notification->recipient_id = $recipient->id;
				$notification->author()->associate($sender);
				$notification->sender()->associate($sender);
				$notification->recipient()->associate($recipient);
				$notification->save();
				event(new Notifications($notification));
			}
		}
		return [$advanceOrder, $message];
	}
	public function deleteInTransitOrder($company, $orderId, $reason, User $loggedInUser, $customerId, $customerType)
	{
		$toEmailIds = [];
		$recipients = [];
		$blanketOrder = AdvanceBlanketOrder::find($orderId);
		$customer = $blanketOrder->orderable;//main distributor who placed order with company
		$blanketOrderNo = $blanketOrder->order_number;
		//$purchaseOrder = PurchaseOrder::find($orderId);
		$orderAmount = $blanketOrder->amount;
		$advanceOrders = $blanketOrder->advance_orders;
		foreach ($advanceOrders as $advanceOrder)
		{
			$advanceLines = $advanceOrder->advance_lines()->get();
			$advanceLineNo = $advanceLines[0]->nav_line_no;


			foreach ($advanceLines as $advanceLine) 
			{
				if($advanceLine->nav_po_no != null)
					return [$blanketOrder, "po_created"];
			}

			$status = $this->salesOrderService->delete($company, $advanceOrder->navision_order_number, $advanceOrder->status, $advanceLineNo);

			if($status)
			{
				$advanceOrder->status = 'Cancelled';
				$advanceOrder->cancelation_reason = $reason;
				$advanceOrder->canceled_by = $loggedInUser->id;
				$advanceOrder->save();
			}
		}

		$blanketOrder->cancelation_reason = $reason;
		$blanketOrder->canceled_by = $loggedInUser->id;
		$blanketOrder->save();
		if($blanketOrder->related_ao_id)
		{
			$mainDistributorReason =  'Please contact Distributor for cancellation reason.';
			$q = User::where('distributor_id','=',$customer->id);
			$q->whereHas('roles',function($q1) {
					$q1->where('name','=','distributorAdmin');
				});
			$sender = $q->first();
			$relatedAO = AdvanceOrder::find($blanketOrder->related_ao_id);
			$relatedAO->status = 'Cancelled';
			$relatedAO->cancelation_reason = $mainDistributorReason;
			$relatedAO->canceled_by = $sender->id;
			$relatedAO->save();
			$relatedAOLine = $relatedAO->advance_lines()->first();
			$relatedAOLine->quantity = 0;
			$relatedAOLine->save();

			$relatedAOsBlanketOrder = AdvanceBlanketOrder::find($relatedAO->blanket_order_id);
			if($relatedAOsBlanketOrder->advance_orders->count() == 1)
			{
				$relatedAOsBlanketOrder->cancelation_reason = $mainDistributorReason;
				$relatedAOsBlanketOrder->canceled_by = $sender->id;
				$relatedAOsBlanketOrder->save();
			}
			else
			{
				$all_aos_shipped = true;
				$all_aos_cancelled = true;
				foreach ($relatedAOsBlanketOrder->advance_orders as $advance_order) 
				{
					if($advance_order->status != 'Shipped' && $advance_order->status != 'Cancelled')
					{
						$all_aos_shipped = false;
					}
					if($advance_order->status != 'Cancelled')
					{
						$all_aos_cancelled = false;
					}
				}
				if($all_aos_cancelled == true)
				{
					$relatedAOsBlanketOrder->cancelation_reason = $mainDistributorReason;
					$relatedAOsBlanketOrder->canceled_by = $sender->id;
					$relatedAOsBlanketOrder->save();
				}
			}
			$dealer = Dealer::find($relatedAOsBlanketOrder->orderable_id);
			$query = User::where('dealer_id','=',$dealer->id);
			$query->whereHas('roles',function($q) {
					$q->where('name','=','dealerAdmin');
				});
			$users = $query->get();
			$dud_user = $users->first();
			$notifiable = (object)[
	            "order_number" => $relatedAOsBlanketOrder->order_number,
	            "number" => $dud_user->phone,
	            "customer" => $dealer->name
	        ];
	        SMSNotification::send($notifiable,new AdvanceOrderCancelledNotification($company));//#744 DUD
			foreach ($users as $user) {
				if($user->communication_email)
					$toDealerEmailIds[] = $user->communication_email;
				$dealerRecipients[] = $user;
			}//add end Customer Admin Email Ids

			$queuePrefix = config('brand.queue_prefix');
			$queue = $queuePrefix.'.emails';
			$Qmessage = (new AdvanceOrderDeleteDistributor($relatedAOsBlanketOrder))->onQueue($queue);
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
				$notification->title = 'Advance Order partially/completely Cancelled!';
				$notification->text = 'Distributor has partially/completely cancelled ' .$relatedAOsBlanketOrder->order_number. ' . '. $mainDistributorReason;
				$notification->sender_id = $sender->id;
				$notification->recipient_id = $recipient->id;
				$notification->author()->associate($sender);
				$notification->sender()->associate($sender);
				$notification->recipient()->associate($recipient);
				$notification->save();
				event(new Notifications($notification));
			}
		}
		$sender = $loggedInUser;
		if($customerType == 'distributors')
		{
			$query = User::where('distributor_id','=',$customerId);
			$query->whereHas('roles',function($q) {
					$q->where('name','=','distributorAdmin');
				});
			$users = $query->get();
			$distributor_user = $users->first();
			foreach ($users as $user) {
				if($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
			}
		}//add Customer Admin Email Ids
		if($customerType == 'super_stockists')
		{
			$query = User::where('super_stockist_id','=',$customerId);
			$query->whereHas('roles',function($q) {
					$q->where('name','=','superStockistAdmin');
				});
			$users = $query->get();
			$super_stockist_user = $users->first();
			foreach ($users as $user) {
				if($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
			}
		}//add Customer Admin Email Ids
		if($customerType == 'dealers' && $customer->distributor_id == null)
		{
			$query = User::where('dealer_id','=',$customerId);
			$query->whereHas('roles',function($q) {
					$q->where('name','=','dealerAdmin');
				});
			$users = $query->get();
			$dealer_user = $users->first();
			foreach ($users as $user) {
				if($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
			}
		}//add Customer Admin Email Ids
		$queuePrefix = config('brand.queue_prefix');
		$queue = $queuePrefix.'.emails';
		$Qmessage = (new AdvanceOrderDelete($blanketOrder))->onQueue($queue);
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
			$notification->title = 'Cancelled Advance Order';
			$notification->text = 'Administrator Cancelled ' .$blanketOrderNo. '. Reason: '. $reason;
			$notification->sender_id = $sender->id;
			$notification->recipient_id = $recipient->id;
			$notification->author()->associate($sender);
			$notification->sender()->associate($sender);
			$notification->recipient()->associate($recipient);
			$notification->save();
			event(new Notifications($notification));
		}
		return [$blanketOrder, null];
	}
	public function deleteInTransitLine($company, $customer_type, $customer, $advanceOrder, $advanceLine, User $user)
	{
		$sendEmail = false;
		$toEmailIds = [];
		$recipients = [];
		$sender = $user;
		$data = [];
		$blanketOrder = $advanceOrder->advance_blanket_order;
		$data['orderNumber'] = $blanketOrder->order_number;
		$data['orderId'] = $blanketOrder->id;

		$data['batch'] = $advanceLine->batch_no;
		$product = $advanceLine->product->name;
		$batch_no = $advanceLine->batch_no;

		$log = new Log;
		$log->action = 'Deleted - Advance Line';
		$log->author()->associate(Auth::user());
		$log->ip_address = \Request::ip();

		$product_display_name = $this->getProductDisplayName($company, $customer, $advanceLine->product->category->code, $advanceLine->product->collection->code, $advanceLine->product->colour->code);

		$data['product_display_name'] = $product_display_name;


		if(count($advanceOrder->advance_lines) == 1)
		{
			$status = $this->salesOrderService->delete($company, $advanceOrder->navision_order_number, $advanceOrder->status, $advanceLine->nav_line_no);
			if($status)
			{
				$advanceLine->delete();
				$advanceOrder->delete();
				$sendEmail = true;
				$log->comment = 'Deleted Advance Order Line for Product: ' .$product_display_name. ' & Batch: ' .$batch_no;
			}
			else
				throw new DeleteLineException;
		}
		else
		{
			$navAdvanceOrder = $this->salesOrderService->deleteSalesLine($company, $advanceOrder->navision_order_number, $advanceLine->nav_line_no, $advanceLine->batch_no);
			if($navAdvanceOrder)
			{
				$advanceLine->delete();
				$advanceOrder->load('advance_lines');
				$advanceOrder->amount = $advanceOrder->advance_lines->sum('net_amount');//#741
				$advanceOrder->save();
				$sendEmail = true;
				$log->comment = 'Deleted Advance Order Line for Product: ' .$product_display_name. ' & Batch: ' .$batch_no;
				list($advanceOrder,$alert) = $this->update($company, $customer, $advanceOrder->id);
			}
			else
				throw new DeleteLineException;
		}	
		$blanketOrder->logs()->save($log);
		if($customer_type == 'distributors')
		{
			$query = User::where('distributor_id','=',$customer->id);
			$query->whereHas('roles',function($q) {
					$q->where('name','=','distributorAdmin');
				});
			$users = $query->get();
			$customer_user = $users->first();
			foreach ($users as $user) {
				if($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
			}
		}//add Customer Admin Email Ids
		if($customer_type == 'super_stockists')
		{
			$query = User::where('super_stockist_id','=',$customer->id);
			$query->whereHas('roles',function($q) {
					$q->where('name','=','superStockistAdmin');
				});
			$users = $query->get();
			$customer_user = $users->first();
			foreach ($users as $user) {
				if($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
			}
		}//add Customer Admin Email Ids
		if($customer_type == 'dealers')
		{
			$query = User::where('dealer_id','=',$customer->id);
			$query->whereHas('roles',function($q) {
					$q->where('name','=','dealerAdmin');
				});
			$users = $query->get();
			$customer_user = $users->first();
			foreach ($users as $user) {
				if($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
			}
		}//add Customer Admin Email Ids
		$notifiable = (object)[
            "order_number" => $blanketOrder->order_number,
            "number" => $customer_user->phone,
            "customer" => $customer->name
        ];
        SMSNotification::send($notifiable,new AdvanceOrderUpdatedNotification($company));//#595 Notify Customer
		
		if($sendEmail)
		{
			$queuePrefix = config('brand.queue_prefix');
			$queue = $queuePrefix.'.emails';
			$Qmessage = (new AdvanceLineDelete($data))->onQueue($queue);
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
				$notification->title = 'Item from ' .$blanketOrder->order_number. ' deleted';
				$notification->text = 'Deleted Item : ' .$product_display_name. ' against Batch : ' .$batch_no;
				$notification->sender_id = $sender->id;
				$notification->recipient_id = $recipient->id;
				$notification->author()->associate($sender);
				$notification->sender()->associate($sender);
				$notification->recipient()->associate($recipient);
				$notification->save();
				event(new Notifications($notification));
			}
	    }
		return $blanketOrder->fresh();
	}
	public function deleteLine($company, $customer_type, $customer, $advanceOrder, $advanceLine, User $user)
	{
		$sendEmail = false;
		$toEmailIds = [];
		$recipients = [];
		$sender = $user;
		$advanceLineProductName = $this->getProductDisplayName($company, $customer, $advanceLine->product->category->code, $advanceLine->product->collection->code, $advanceLine->product->colour->code);

		$data = [];
		$data['orderNumber'] = $advanceOrder->order_number;
		$data['orderId'] = $advanceOrder->id;
		$data['productName'] = $advanceLineProductName;
		$batch_no = $advanceLine->batch_no;

		$log = new Log;
		$log->action = 'Deleted - Advance Order Line';
		$log->author()->associate(Auth::user());
		$log->ip_address = \Request::ip();
		$navAdvanceOrder = $this->salesOrderService->deleteSalesLine($company, $advanceOrder->navision_order_number, $advanceLine->nav_line_no, $advanceLine->batch_no);
		if($navAdvanceOrder)
		{
			$advanceLine->delete();
			$sendEmail = true;
			$log->comment = 'Deleted Advance Order Line for Product: ' .$advanceLineProductName;
		}
		else
			throw new DeleteLineException;

		list($advanceOrder,$alert) = $this->update($company, $customer, $advanceOrder->id);
		$advanceOrder->logs()->save($log);
		if($customer_type == 'distributors')
		{
			$query = User::where('distributor_id','=',$customer->id);
			$query->whereHas('roles',function($q) {
					$q->where('name','=','distributorAdmin');
				});
			$users = $query->get();
			
			foreach ($users as $user) {
				if($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
			}
		}//add Customer Admin Email Ids
		if($customer_type == 'dealers')
		{
			$query = User::where('dealer_id','=',$customer->id);
			$query->whereHas('roles',function($q) {
					$q->where('name','=','dealerAdmin');
				});
			$users = $query->get();
			
			foreach ($users as $user) {
				if($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
			}
		}//add Customer Admin Email Ids
		if($sendEmail)
		{
			$queuePrefix = config('brand.queue_prefix');
			$queue = $queuePrefix.'.emails';
			$Qmessage = (new AdvanceLineDelete($data))->onQueue($queue);
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
				$notification->title = 'Item from ' .$advanceOrder->order_number. ' deleted';
				$notification->text = 'Deleted Item : ' .$advanceLineProductName;
				$notification->sender_id = $sender->id;
				$notification->recipient_id = $recipient->id;
				$notification->author()->associate($sender);
				$notification->sender()->associate($sender);
				$notification->recipient()->associate($recipient);
				$notification->save();
				event(new Notifications($notification));
			}
	    }
		return [$advanceOrder,$alert];	
	}
	public function updateLineQtyDisc($company, $customer_type, $customer,  $advanceOrder, $advanceLine, Array $params,  User $user)
	{
		$blanketOrder = $advanceOrder->advance_blanket_order;
		$sendUnitPriceEmail = false;
		$sendQuantityEmail = false;
		$sendUnitPriceDiscountEmail = false;
		$advanceLineProductName = $this->getProductDisplayName($company, $customer, $advanceLine->product->category->code, $advanceLine->product->collection->code, $advanceLine->product->colour->code);
		$currentDiscount = $advanceLine->discount;
		$currentUnitPrice = $advanceLine->unit_price;
		$current_discounted_unit_price = round($currentUnitPrice - ($currentUnitPrice * $currentDiscount/100),2);
		$currentQuantity = $advanceLine->quantity;
		$newDiscountedUnitPrice = $current_discounted_unit_price;
		$log = new Log;
		$log->action = 'Updated - Advance Line';
		$log->author()->associate(Auth::user());
		$log->ip_address = \Request::ip();
		$newDiscount = $params['discount'];
		$newQuantity = $params['quantity'];
		if($params['discount'] != $currentDiscount && $params['quantity'] != $currentQuantity)
		{
			//$newDiscount = $params['discount'];
			$newDiscountedUnitPrice = round($currentUnitPrice - ($currentUnitPrice * $newDiscount/100),2);
			$newQuantity = $params['quantity'];
			$navAdvanceOrder = $this->salesOrderService->updateSalesLine($company, $advanceOrder->nav_key, $advanceOrder->navision_order_number, $advanceLine->nav_line_no, $newQuantity, $newDiscountedUnitPrice, null);
			$newTaxAmount = ($newDiscountedUnitPrice * $advanceLine->tax_percent/100) * $newQuantity;
			if($navAdvanceOrder)
			{
				$advanceLine->discount = $newDiscount;
				$advanceLine->amount = $newDiscountedUnitPrice * $newQuantity;
				$advanceLine->tax_amount = $newTaxAmount;
				$advanceLine->net_amount = $newTaxAmount + ($newDiscountedUnitPrice * $newQuantity);
				$advanceLine->quantity = $newQuantity;
				$advanceLine->save();
				$sendUnitPriceDiscountEmail = true;
				$log->comment = 'Changed Discount from ' .$currentDiscount. ' to ' .$newDiscount. ' & Quantity from ' .$currentQuantity. ' to ' .$newQuantity;

				if($blanketOrder->related_ao_id)
				{
					$relatedAO = $blanketOrder->relatedOrder;	
					if($relatedAO)
					{
						$relatedAOLine = $relatedAO->advance_lines()->where('nav_line_no','=',$advanceLine->nav_line_no)->first();
						$relatedAOLine_discounted_unit_price = round($relatedAOLine->unit_price - ($relatedAOLine->unit_price * $relatedAOLine->discount/100),2);
						$relatedAOLine->quantity = $newQuantity;
						$relatedAOLine->amount = $relatedAOLine_discounted_unit_price * $newQuantity;
						$relatedAOLine->tax_amount = $relatedAOLine->amount * $relatedAOLine->tax_percent/100;//#741
						$relatedAOLine->net_amount = $relatedAOLine->amount + $relatedAOLine->tax_amount;//#741
						$relatedAOLine->save();
						$relatedAO->Amount = $relatedAO->advance_lines->sum('net_amount');//#741
						$relatedAO->save();
	
						$dudCustomer = $relatedAO->advance_blanket_order->orderable;
						$query = User::where('dealer_id','=',$dudCustomer->id);
						$query->whereHas('roles',function($q) {
								$q->where('name','=','dealerAdmin');
							});
						$users = $query->get();
						$dud_user = $users->first();
						foreach ($users as $user) {
							if($user->communication_email)
								$toDealerEmailIds[] = $user->communication_email;
							$dealerRecipients[] = $user;
						}
						$query = User::where('distributor_id','=',$customer->id);
						$query->whereHas('roles',function($q) {
								$q->where('name','=','distributorAdmin');
							});
						$notifiable = (object)[
				            "order_number" => $relatedAO->advance_blanket_order->order_number,
				            "number" => $dud_user->phone,
				            "customer" => $dudCustomer->name
				        ];
				        SMSNotification::send($notifiable,new AdvanceOrderUpdatedNotification($company));//#595 Notify DUD Customer when B2B order is updated by Super/Company Admin
				        
						$product_display_name = $this->getProductDisplayName($company, $dudCustomer, $relatedAOLine->product->category->code, $relatedAOLine->product->collection->code, $relatedAOLine->product->colour->code);
						$sender = $query->first();
						$data = [];
						$data['orderNumber'] = $relatedAO->advance_blanket_order->order_number;
						$data['quantity'] = $currentQuantity;
						$data['current_discounted_unit_price'] = $relatedAOLine_discounted_unit_price;
						$data['new_discounted_unit_price'] = $relatedAOLine_discounted_unit_price;
						$data['product_display_name'] = $product_display_name;
						$queuePrefix = config('brand.queue_prefix');
						$queue = $queuePrefix.'.emails';
					    $Qmessage = (new AdvanceLineUpdateDud($relatedAOLine, $data))->onQueue($queue);
					    try
		    			{
					    	if(!empty($toDealerEmailIds))
	        					Mail::to($toDealerEmailIds)->queue($Qmessage);
	        				foreach ($dealerRecipients as $recipient) 
							{
			        			$notification = new Notification;
			        			$notification->sender_id = $sender->id;
								$notification->recipient_id = $recipient->id;
								$notification->author()->associate($sender);
								$notification->sender()->associate($sender);
								$notification->recipient()->associate($recipient);
								$notification->title = 'Updated Quantity for ' .$relatedAO->advance_blanket_order->order_number. ' item';
								$notification->text = 'Updated Quantity from ' .$currentQuantity. ' to ' .$newQuantity. ' for Item : ' .$product_display_name. ', against Batch : ' .$relatedAOLine->batch_no;
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
			}
		}
		else if($params['discount'] != $currentDiscount)
		{
			$newDiscountedUnitPrice = round($currentUnitPrice - ($currentUnitPrice * $newDiscount/100),2);
			$navAdvanceOrder = $this->salesOrderService->updateSalesLine($company, $advanceOrder->nav_key, $advanceOrder->navision_order_number, $advanceLine->nav_line_no, $params['quantity'], $newDiscountedUnitPrice, null);
			$newTaxAmount = ($newDiscountedUnitPrice * $advanceLine->tax_percent/100) * $newQuantity;
			if($navAdvanceOrder)
			{
				$advanceLine->discount = $newDiscount;
				$advanceLine->amount = $newDiscountedUnitPrice * $params['quantity'];
				$advanceLine->tax_amount = $newTaxAmount;
				$advanceLine->net_amount = $newTaxAmount + ($newDiscountedUnitPrice * $newQuantity);
				$advanceLine->save();
				$sendUnitPriceEmail = true;
				$log->comment = 'Changed Discount from ' .$currentDiscount. ' to ' .$newDiscount;
			}
		}
		else if($params['quantity'] != $currentQuantity)
		{
			$navAdvanceOrder = $this->salesOrderService->updateSalesLine($company, $advanceOrder->nav_key, $advanceOrder->navision_order_number, $advanceLine->nav_line_no, $newQuantity, $current_discounted_unit_price, null);
			$newTaxAmount = ($newDiscountedUnitPrice * $advanceLine->tax_percent/100) * $newQuantity;
			if($navAdvanceOrder)
			{
				$advanceLine->quantity = $newQuantity;
				$advanceLine->amount = $current_discounted_unit_price * $newQuantity;
				$advanceLine->tax_amount = $newTaxAmount;
				$advanceLine->net_amount = $newTaxAmount + ($newDiscountedUnitPrice * $newQuantity);
				$advanceLine->save();
				$sendQuantityEmail = true;
				$log->comment = 'Changed Quantity from ' .$currentQuantity. ' to ' .$newQuantity;

				$blanketOrder = $advanceOrder->advance_blanket_order;
				if($blanketOrder->related_ao_id)
				{
					$relatedAO = $blanketOrder->relatedOrder;	
					if($relatedAO)
					{
						$relatedAOLine = $relatedAO->advance_lines()->where('nav_line_no','=',$advanceLine->nav_line_no)->first();
						$relatedAOLine_discounted_unit_price = round($relatedAOLine->unit_price - ($relatedAOLine->unit_price * $relatedAOLine->discount/100),2);
						$relatedAOLine->quantity = $newQuantity;
						$relatedAOLine->amount = $relatedAOLine_discounted_unit_price * $newQuantity;
						$relatedAOLine->tax_amount = $relatedAOLine->amount * $relatedAOLine->tax_percent/100;//#741
						$relatedAOLine->net_amount = $relatedAOLine->amount + $relatedAOLine->tax_amount;//#741
						$relatedAOLine->save();
						$relatedAO->Amount = $relatedAO->advance_lines->sum('net_amount');//#741
						$relatedAO->save();
	
						$dudCustomer = $relatedAO->advance_blanket_order->orderable;
						$query = User::where('dealer_id','=',$dudCustomer->id);
						$query->whereHas('roles',function($q) {
								$q->where('name','=','dealerAdmin');
							});
						$users = $query->get();
						$dud_user = $users->first();
						foreach ($users as $user) {
							if($user->communication_email)
								$toDealerEmailIds[] = $user->communication_email;
							$dealerRecipients[] = $user;
						}
						$query = User::where('distributor_id','=',$customer->id);
						$query->whereHas('roles',function($q) {
								$q->where('name','=','distributorAdmin');
							});
						$notifiable = (object)[
				            "order_number" => $relatedAO->advance_blanket_order->order_number,
				            "number" => $dud_user->phone,
				            "customer" => $dudCustomer->name
				        ];
				        SMSNotification::send($notifiable,new AdvanceOrderUpdatedNotification($company));//#595 Notify DUD Customer when B2B order is updated by Super/Company Admin
						
						$product_display_name = $this->getProductDisplayName($company, $dudCustomer, $relatedAOLine->product->category->code, $relatedAOLine->product->collection->code, $relatedAOLine->product->colour->code);
						$sender = $query->first();
						$data = [];
						$data['orderNumber'] = $relatedAO->advance_blanket_order->order_number;
						$data['quantity'] = $currentQuantity;
						$data['current_discounted_unit_price'] = $relatedAOLine_discounted_unit_price;
						$data['new_discounted_unit_price'] = $relatedAOLine_discounted_unit_price;
						$data['product_display_name'] = $product_display_name;
						$queuePrefix = config('brand.queue_prefix');
						$queue = $queuePrefix.'.emails';
					    $Qmessage = (new AdvanceLineUpdateDud($relatedAOLine, $data))->onQueue($queue);
					    try
		    			{
					    	if(!empty($toDealerEmailIds))
	        					Mail::to($toDealerEmailIds)->queue($Qmessage);
	        				foreach ($dealerRecipients as $recipient) 
							{
			        			$notification = new Notification;
			        			$notification->sender_id = $sender->id;
								$notification->recipient_id = $recipient->id;
								$notification->author()->associate($sender);
								$notification->sender()->associate($sender);
								$notification->recipient()->associate($recipient);
								$notification->title = 'Updated Quantity for ' .$relatedAO->advance_blanket_order->order_number. ' item';
								$notification->text = 'Updated Quantity from ' .$currentQuantity. ' to ' .$newQuantity. ' for Item : ' .$product_display_name. ', against Batch : ' .$relatedAOLine->batch_no;
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
			}
		}
		$advanceOrder->amount = $advanceOrder->advance_lines->sum('net_amount');//#741
		$advanceOrder->save();//#741
		list($advanceOrder,$alert) = $this->update($company, $customer, $advanceOrder->id);
		$advanceOrder->logs()->save($log);
		$toEmailIds = [];
		$recipients = [];
		$sender = $user;
			
		if($customer_type == 'distributors')
		{
			$query = User::where('distributor_id','=',$customer->id);
			$query->whereHas('roles',function($q) {
					$q->where('name','=','distributorAdmin');
				});
			$users = $query->get();
			$customer_user = $users->first();
			foreach ($users as $user) {
				if($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
			}
		}//add Customer Admin Email Ids
		if($customer_type == 'super_stockists')
		{
			$query = User::where('super_stockist_id','=',$customer->id);
			$query->whereHas('roles',function($q) {
					$q->where('name','=','superStockistAdmin');
				});
			$users = $query->get();
			$customer_user = $users->first();
			foreach ($users as $user) {
				if($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
			}
		}//add Customer Admin Email Ids
		if($customer_type == 'dealers')
		{
			$query = User::where('dealer_id','=',$customer->id);
			$query->whereHas('roles',function($q) {
					$q->where('name','=','dealerAdmin');
				});
			$users = $query->get();
			$customer_user = $users->first();
			foreach ($users as $user) {
				if($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
			}
		}//add Customer Admin Email Ids
		$notifiable = (object)[
            "order_number" => $blanketOrder->order_number,
            "number" => $customer_user->phone,
            "customer" => $customer->name
        ];
        SMSNotification::send($notifiable,new AdvanceOrderUpdatedNotification($company));//#595 Notify Customer
		if($sendUnitPriceDiscountEmail || $sendQuantityEmail || $sendUnitPriceEmail)
		{
			$data = [];
			$data['orderNumber'] = $advanceOrder->order_number;
			$data['quantity'] = $currentQuantity;
			$data['current_discounted_unit_price'] = $current_discounted_unit_price;
			$data['new_discounted_unit_price'] = $newDiscountedUnitPrice;
			$data['productName'] = $advanceLineProductName;
			$queuePrefix = config('brand.queue_prefix');
			$queue = $queuePrefix.'.emails';
		    $Qmessage = (new AdvanceLineUpdate($advanceLine, $data))->onQueue($queue);
		    try
		    {
		    	if(!empty($toEmailIds))
	        		Mail::to($toEmailIds)->queue($Qmessage);
	        	foreach ($recipients as $recipient) 
				{
					$notification = new Notification;
					if($sendUnitPriceDiscountEmail)
					{
						$notification->title = 'Updated Discount and Quantity for ' .$advanceOrder->order_number. ' item';
						$notification->text = 'Updated Discount from ' .$currentDiscount. '% to ' .$newDiscount. '% and Quantity from ' .$currentQuantity. ' to ' .$newQuantity. ' for Item : ' .$advanceLineProductName;	
					}
					if($sendQuantityEmail)
					{
						$notification->title = 'Updated Quantity for ' .$advanceOrder->order_number. ' item';
						$notification->text = 'Updated Quantity from ' .$currentQuantity. ' to ' .$newQuantity. ' for Item : ' .$advanceLineProductName;	
					}
					if($sendUnitPriceEmail)
					{
						$notification->title = 'Updated Discount for ' .$advanceOrder->order_number. ' item';
						$notification->text = 'Updated Discount from ' .$currentDiscount. '% to ' .$newDiscount. '% for Item : ' .$advanceLineProductName;
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
		return [$advanceOrder,$alert];
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