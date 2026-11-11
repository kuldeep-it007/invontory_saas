<?php

namespace Rosetta\Services\SSPurchaseOrders;

use Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as SMSNotification;
use Predis\Connection\ConnectionException;
use Ramsey\Uuid\Uuid;
use Rosetta\Events\Notifications;
use Rosetta\Exceptions\RedisException;
use Rosetta\Helpers\CustomHelper;
use Rosetta\Mail\PurchaseOrderNew;
use Rosetta\Mail\PurchaseOrderNewDud;
use Rosetta\Mail\dealerSalesOrderNew;
use Rosetta\Models\Address;
use Rosetta\Models\BlanketOrder;
use Rosetta\Models\Cart\BlockedItem;
use Rosetta\Models\Cart\CartItem;
use Rosetta\Models\Company;
use Rosetta\Models\Company\StockPoint;
use Rosetta\Models\Distributor;
use Rosetta\Models\Notification;
use Rosetta\Models\Product\Product;
use Rosetta\Models\PurchaseOrder\PurchaseLine;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Models\User;
use Rosetta\Notifications\SMS\PurchaseOrderReceivedNotification;
use Rosetta\Services\CLRate\CLRateServiceInterface;
use Rosetta\Services\Depot\DepotItemsServiceInterface;
use Rosetta\Services\Discount\DiscountsServiceInterface;
use Rosetta\Services\NAV\AvailableCreditServiceInterface;
use Rosetta\Services\NAV\SalesOrderServiceInterface;
use Rosetta\Services\SSDiscount\SSDiscountServiceInterface;
use Rosetta\Services\SSPurchaseOrders\SSPurchaseOrdersServiceInterface;
use Rosetta\Services\SuperStockistPurchaseOrders\SuperStockistPurchaseOrdersServiceInterface;
use Rosetta\Services\Distributor\DistributorServiceInterface;
use Rosetta\Services\SuperStockist\SuperStockistServiceInterface;
use Rosetta\Models\Dealer;

class SSPurchaseOrdersService implements SSPurchaseOrdersServiceInterface
{
	protected $availableCreditService, $ssDiscountService, $salesOrderService, $discountService, $clRateService, $depotService, $distributorService, $superStockistService;
	public function __construct(AvailableCreditServiceInterface $availableCreditService, SSDiscountServiceInterface $ssDiscountService, SalesOrderServiceInterface $salesOrderService, DiscountsServiceInterface $discountService, CLRateServiceInterface $clRateService, DepotItemsServiceInterface $depotService, DistributorServiceInterface $distributorService, SuperStockistServiceInterface $superStockistService)
	{
		$this->availableCreditService = $availableCreditService;
		$this->ssDiscountService      = $ssDiscountService;
		$this->salesOrderService      = $salesOrderService;
		$this->discountService        = $discountService;
		$this->clRateService          = $clRateService;
		$this->depotService 	      = resolve('Rosetta\Services\Depot\DepotItemsServiceInterface');
		$this->distributorService 	  = $distributorService;
		$this->superStockistService   = $superStockistService;
	}
	public function createBlanketOrder(Company $company, $customer, array $params, User $user, $customerType)
	{
		$recipients = [];
		$toEmailIds = [];
		$toDealerEmailIds = [];
		$sender = $user;

		$collection = collect($params['purchase_lines']);
		$orderAmount = $collection->sum('amount');
		$creditLimit = $customer->credit_limit;
		$balanceDue = $customer->overdue_balance;

		if ($orderAmount > $creditLimit) {
			$message = "Your order cannot be processed at this time due to negative credit limit. Please contact your Relationship Manager for further actions.";
			return [null, null, $message];
		}
		if ($orderAmount <= $creditLimit && $balanceDue > 0) {
			$message = "Your order cannot be processed at this time due to overdue payments. Please contact your Relationship Manager for further actions.";
			return [null, null, $message];
		}

		$groupedByStockPoint = $collection->groupBy('stock_point_id');
		$allInvalidLines = collect([]);
		$message = null;
		$orderNotPlacedCnt = 0;
		$stockPointCnt = 0;
		$companyStockPointCnt = 0;
		$dudBlanketOrder = null;

		$dudBlanketOrder = new BlanketOrder;
		if (array_has($params, 'source')) //#767
			$dudBlanketOrder->source = $params['source'];
		if (array_has($params, 'OnBehalf')) //#767
			$dudBlanketOrder->on_behalf = $params['OnBehalf'];
		$dudBlanketOrder->company_id = $company->id;
		if ($customer->distributor_id)
			$dudBlanketOrder->distributor_id = $customer->distributor_id;
		if (array_has($params, 'end_customer_id'))
			$dudBlanketOrder->end_customer_id = $params['end_customer_id'];
		if (array_has($params, 'installer_id'))
			$dudBlanketOrder->engineer_id = $params['installer_id'];
		$dudBlanketOrder->save();

		foreach ($groupedByStockPoint as $stock_point_id => $purchase_lines) {
			$stockPointCnt++;
			if ($stock_point_id === "" || $stock_point_id === null) //SS or Co SS Stock
			{
				$ss_lines = $purchase_lines;
				$ssLinesGrouped = collect($purchase_lines)->groupBy('super_stockist_id');

				foreach ($ssLinesGrouped as $grouped_ss_id => $ss_purchase_lines) {
					if ($grouped_ss_id == $customer->distributor->super_stockist_id) {
						//Distributor's SS's Stock
						list($purchaseOrder, $invalidLines, $message) = $this->createDistToSSOrder($company, $customer, $params, $user, $ss_purchase_lines, $dudBlanketOrder, $grouped_ss_id);
						if (count($invalidLines)) {
							$allInvalidLines = $allInvalidLines->concat($invalidLines);
						}
						if ($purchaseOrder === null) {
							$orderNotPlacedCnt++;
						}
						$distToSSBlanketOrder = $dudBlanketOrder;
					} else if ($grouped_ss_id > 0) {
						/* SS to Another SS order */
						list($purchaseOrder, $invalidLines, $message) = $this->createDistToSSOrder($company, $customer, $params, $user, $ss_purchase_lines, $dudBlanketOrder, $grouped_ss_id);
						if (count($invalidLines)) {
							$allInvalidLines = $allInvalidLines->concat($invalidLines);
						}
						if ($purchaseOrder === null) {
							$orderNotPlacedCnt++;
						}
						$distToSSBlanketOrder = $dudBlanketOrder;
					} else //Co SS Stock
					{
						$distributorLinesGrouped = collect($ss_purchase_lines)->groupBy('distributor_id');
						foreach ($distributorLinesGrouped as $grouped_distributor_id => $distributor_purchase_lines) {
							if ($grouped_distributor_id == $customer->distributor_id) {
								//Dealer Distributor's Stock
								list($purchaseOrder, $invalidLines, $message) = $this->createDealerToDistributorOrder($company, $customer, $params, $user, $distributor_purchase_lines, $dudBlanketOrder, $grouped_distributor_id);
								if (count($invalidLines)) {
									$allInvalidLines = $allInvalidLines->concat($invalidLines);
								}
								if ($purchaseOrder === null) {
									$orderNotPlacedCnt++;
								}
								$distToSSBlanketOrder = $dudBlanketOrder;
							} else if ($grouped_distributor_id > 0) {
								//Other distributor Stock			
								list($purchaseOrder, $invalidLines, $message) = $this->createDealerToDistributorOrder($company, $customer, $params, $user, $distributor_purchase_lines, $dudBlanketOrder, $grouped_distributor_id);
								if (count($invalidLines)) {
									$allInvalidLines = $allInvalidLines->concat($invalidLines);
								}
								if ($purchaseOrder === null) {
									$orderNotPlacedCnt++;
								}
								$distToSSBlanketOrder = $dudBlanketOrder;
							} else {
								//Co distributor Stock
							}
						}
					}
				}
			} else {
				//Company Stock
				$ssToCompBlanketOrder = new BlanketOrder;
				if (array_has($params, 'source')) //#767
					$ssToCompBlanketOrder->source = $params['source'];
				if (array_has($params, 'OnBehalf')) //#767
					$ssToCompBlanketOrder->on_behalf = $params['OnBehalf'];
				$ssToCompBlanketOrder->company_id = $company->id;
				$ssToCompBlanketOrder->save();

				$stock_point = StockPoint::where('id', '=', $stock_point_id)->first();
				$company_lines = $purchase_lines;
				list($ssToCompPurchaseOrder, $invalidLines, $message) = $this->create($company, $customer, $customerType, $params, $user, $company_lines, $ssToCompBlanketOrder, $stock_point_id);
				/* create new purchase orders for dist to SS and dealer with distributor*/
				if ($ssToCompPurchaseOrder) {
					$customer->distributor->superStockist->blanketOrders()->save($ssToCompBlanketOrder);

					///////DIST SS ORDERING
					$distToSSBlanketOrder = new BlanketOrder;
					if (array_has($params, 'source')) //#767
						$distToSSBlanketOrder->source = $params['source'];
					if (array_has($params, 'OnBehalf')) //#767
						$distToSSBlanketOrder->on_behalf = $params['OnBehalf'];
					$distToSSBlanketOrder->company_id = $company->id;
					$distToSSBlanketOrder->super_stockist_id = $customer->distributor->super_stockist_id;
					$distToSSBlanketOrder->save();


					/* this is order placed for dealer create new PO based on original PO*/
					$distToSSPurchaseOrder = new PurchaseOrder;
					$distToSSPurchaseOrder->shipping_method = $params['shipping_method'];
					$distToSSPurchaseOrder->special_request = $params['special_request'];
					$distToSSPurchaseOrder->UUID = Uuid::uuid4();
					$distToSSPurchaseOrder->navision_order_number = '';
					$distToSSPurchaseOrder->super_stockist_id = $customer->distributor->super_stockist_id;
					$distToSSPurchaseOrder->blanket_order_id = $distToSSBlanketOrder->id;
					$distToSSPurchaseOrder->stock_point_id = $stock_point_id;
					$distToSSPurchaseOrder->nav_key = '';
					$distToSSPurchaseOrder->company_id = $company->id;
					$distToSSPurchaseOrder->status = $ssToCompPurchaseOrder->status;
					$distToSSPurchaseOrder->shipping_method = $params['shipping_method'];
					$distToSSPurchaseOrder->special_request = $params['special_request'];
					$distToSSPurchaseOrder->save();

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
					$distToSSPurchaseOrder->addresses()->save($address);

					$originalParamLines = collect($params['purchase_lines']);
					$totalAmount = 0;
					foreach ($ssToCompPurchaseOrder->purchase_lines as $temp_purchase_line) {
						$tempLine = new PurchaseLine;
						$tempLine->nav_line_no = $temp_purchase_line->nav_line_no;
						$tempLine->product_id = $temp_purchase_line->product_id;
						$tempLine->purchase_order_id = $distToSSPurchaseOrder->id;
						$tempLine->quantity = $temp_purchase_line->quantity;
						$tempLine->batch_no = $temp_purchase_line->batch_no;
						$selectedLine = $originalParamLines->where('product_id', '=', $temp_purchase_line->product_id)
							->where('batch_no', '=', $temp_purchase_line->batch_no)
							->where('quantity', '=', round($temp_purchase_line->quantity, 2)) //#843
							->first();
						$tempLine->unit_price = $selectedLine['unit_price'];

						$product = Product::find($temp_purchase_line->product_id);
						//$ss_discount = round($this->ssDiscountService->getSSDiscount('DISTRIBUTO',$customer->distributor,$product, $customer->distributor->superStockist->id),2);
						$customerType = 'DISTRIBUTO';
						if ($customer->distributor->super_stockist_id > 0) {
							$customerType = 'SUPER_STOCKIST_DISTRIBUTOR';
						}
						$ss_discount = round($this->discountService->getDiscount($customerType, $customer->distributor, $product, $customer->distributor->superStockist->id), 2);

						$tempLine->discount = $ss_discount;

						$ss_discountedPrice = round($selectedLine['unit_price'] - ($selectedLine['unit_price'] * $ss_discount / 100), 2);
						$ss_lineAmount = $ss_discountedPrice * $temp_purchase_line->quantity;
						$tempLine->amount = $ss_lineAmount;

						$tempLine->rate_type = $temp_purchase_line->rate_type;
						$tempLine->tax_type_code = $temp_purchase_line->tax_type_code;
						$tempLine->tax_percent = $temp_purchase_line->tax_percent;
						$tempLine->tax_amount = ((float) $tempLine->amount) * $tempLine->tax_percent / 100;
						$tempLine->net_amount = $tempLine->tax_amount + $tempLine->amount;
						$distToSSPurchaseOrder->purchase_lines()->save($tempLine);
						$totalAmount += (float)$tempLine->amount;
					}
					$distToSSPurchaseOrder->Amount = $totalAmount;
					$distToSSPurchaseOrder->save();

					$ssToCompBlanketOrder->related_po_id = $distToSSPurchaseOrder->id;
					$ssToCompBlanketOrder->save();
					$customer->distributor->orders()->save($distToSSPurchaseOrder);
					$customer->distributor->blanketOrders()->save($distToSSBlanketOrder);


					///////DUD TO DIST OORDERING




					$duDToDistPurchaseOrder = new PurchaseOrder;
					$duDToDistPurchaseOrder->shipping_method = $params['shipping_method'];
					$duDToDistPurchaseOrder->special_request = $params['special_request'];
					$duDToDistPurchaseOrder->UUID = Uuid::uuid4();
					$duDToDistPurchaseOrder->navision_order_number = '';
					$duDToDistPurchaseOrder->distributor_id = $customer->distributor_id;
					$duDToDistPurchaseOrder->blanket_order_id = $dudBlanketOrder->id;
					$duDToDistPurchaseOrder->nav_key = '';
					$duDToDistPurchaseOrder->company_id = $company->id;
					$duDToDistPurchaseOrder->status = $ssToCompPurchaseOrder->status;
					$duDToDistPurchaseOrder->shipping_method = $params['shipping_method'];
					$duDToDistPurchaseOrder->special_request = $params['special_request'];
					$duDToDistPurchaseOrder->stock_point_id = $stock_point_id;
					$duDToDistPurchaseOrder->save();

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
					$duDToDistPurchaseOrder->addresses()->save($address);

					$originalParamLines = collect($params['purchase_lines']);
					$totalAmount = 0;
					foreach ($ssToCompPurchaseOrder->purchase_lines as $temp_purchase_line) {
						$tempLine = new PurchaseLine;
						$tempLine->nav_line_no = $temp_purchase_line->nav_line_no;
						$tempLine->product_id = $temp_purchase_line->product_id;
						$tempLine->purchase_order_id = $duDToDistPurchaseOrder->id;
						$tempLine->quantity = $temp_purchase_line->quantity;
						$tempLine->batch_no = $temp_purchase_line->batch_no;
						$selectedLine = $originalParamLines->where('product_id', '=', $temp_purchase_line->product_id)
							->where('batch_no', '=', $temp_purchase_line->batch_no)
							->where('quantity', '=', round($temp_purchase_line->quantity, 2)) //#843
							->first();
						$tempLine->unit_price = $selectedLine['unit_price'];
						$tempLine->discount = $selectedLine['discount'];
						$tempLine->amount = $selectedLine['amount'];
						$tempLine->rate_type = $temp_purchase_line->rate_type;
						$tempLine->tax_type_code = $temp_purchase_line->tax_type_code;
						$tempLine->tax_percent = $temp_purchase_line->tax_percent;
						$tempLine->tax_amount = ((float) $tempLine->amount) * $tempLine->tax_percent / 100;
						$tempLine->net_amount = $tempLine->tax_amount + $tempLine->amount;
						$duDToDistPurchaseOrder->purchase_lines()->save($tempLine);
						$totalAmount += (float)$tempLine->amount;
					}
					$duDToDistPurchaseOrder->Amount = $totalAmount;
					$duDToDistPurchaseOrder->save();

					$distToSSBlanketOrder->related_po_id = $duDToDistPurchaseOrder->id;
					$distToSSBlanketOrder->save();
					$customer->orders()->save($duDToDistPurchaseOrder);



					//////


				}

				if (count($invalidLines)) {
					$allInvalidLines = $allInvalidLines->concat($invalidLines);
				}
				if ($ssToCompPurchaseOrder === null) {
					$orderNotPlacedCnt++;
				}
				$companyStockPointCnt++;
			}
		}
		if ($orderNotPlacedCnt == $stockPointCnt) {
			$dudBlanketOrder->forceDelete();
			if (!empty($distToSSBlanketOrder))
				$distToSSBlanketOrder->forceDelete();
			if (!empty($ssToCompBlanketOrder))
				$ssToCompBlanketOrder->forceDelete();
			return [null, $allInvalidLines, $message];
		}
		if ($allInvalidLines->count() == count($params['purchase_lines'])) {
			$dudBlanketOrder->forceDelete();
			if ($distToSSBlanketOrder)
				$distToSSBlanketOrder->forceDelete();
			if ($ssToCompBlanketOrder)
				$ssToCompBlanketOrder->forceDelete();
			return [null, $allInvalidLines, $message];
		}

		$customer->blanketOrders()->save($dudBlanketOrder);

		if ($customerType == 'dealers') {
			$query = User::where('dealer_id', '=', $customer->id);
			$query->whereHas('roles', function ($q) {
				$q->where('name', '=', 'dealerAdmin');
			});
			$users = $query->get();
			foreach ($users as $user) {
				if ($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
			}
		} //add Customer Admin Email Ids

		CustomHelper::sendWhatsappNotification($dudBlanketOrder);
		foreach ($recipients as $recipient) {
			$notification = new Notification;
			$notification->title = 'Created new Purchase Order';
			$notification->text = 'New Purchase Order ' . $dudBlanketOrder->order_number . ' has been created';
			$notification->sender_id = $sender->id;
			$notification->recipient_id = $recipient->id;
			$notification->author()->associate($sender);
			$notification->sender()->associate($sender);
			$notification->recipient()->associate($recipient);
			$notification->save();
			event(new Notifications($notification, $dudBlanketOrder));
		}
		$queuePrefix = config('brand.queue_prefix');
		$queue = $queuePrefix . '.emails';
		$data = [];

		$Qmessage = (new PurchaseOrderNewDud($dudBlanketOrder))->onQueue($queue);
		try {
			//if(!empty($toEmailIds))
			//	Mail::to($toEmailIds)->queue($Qmessage);
		} catch (ConnectionException $e) {
			throw new RedisException;
		}

		$toMainDistributorEmailIds = [];
		$mainDistributorRecipients = [];
		if ($customerType == 'dealers' && $customer->distributor_id != null) {
			$query = User::where('distributor_id', '=', $customer->distributor_id);
			$query->whereHas('roles', function ($q) {
				$q->where('name', '=', 'distributorAdmin');
			});
			$users = $query->get();
			$distributor_user = $users->first();
			foreach ($users as $user) {
				if ($user->communication_email)
					$toMainDistributorEmailIds[] = $user->communication_email;
				$mainDistributorRecipients[] = $user;
			}
			$notifiable = (object)[
				"number" => $distributor_user->phone,
				"supplier" => $customer->distributor->name,
				"customer" => $customer->name
			];
			SMSNotification::send($notifiable, new PurchaseOrderReceivedNotification($company)); //#595 Notify Distributor for DUD Order

		} //add Main Distributor Admin Email Ids
		CustomHelper::sendWhatsappNotification($dudBlanketOrder);
		foreach ($mainDistributorRecipients as $recipient) {
			$notification = new Notification;
			$notification->title = 'Created new Dealer Order';
			$notification->text = 'New Dealer Order ' . $dudBlanketOrder->order_number . ' has been created';
			$notification->sender_id = $sender->id;
			$notification->recipient_id = $recipient->id;
			$notification->author()->associate($sender);
			$notification->sender()->associate($sender);
			$notification->recipient()->associate($recipient);
			$notification->save();
			event(new Notifications($notification, $dudBlanketOrder));
		}
		$queuePrefix = config('brand.queue_prefix');
		$queue = $queuePrefix . '.emails';
		$Qmessage = (new dealerSalesOrderNew($dudBlanketOrder))->onQueue($queue);
		try {
			//if(!empty($toMainDistributorEmailIds))
			//	Mail::to($toMainDistributorEmailIds)->queue($Qmessage);
		} catch (ConnectionException $e) {
			throw new RedisException;
		}
		return [$dudBlanketOrder, $allInvalidLines, $message];
	}
	public function createDistToSSOrder(Company $company, $customer, array $params, User $user, $ss_lines, $dudBlanketOrder, $stockPointId)
	{
		$distributor = Distributor::find($customer->distributor_id);
		$superStockist = SuperStockist::find($customer->distributor->super_stockist_id);

		$message = null;
		$soAmount = 0;
		$ss_soAmount = 0;
		$recipients = [];
		$sender = $user;

		$validated = $this->validatePurchaseLineswithSSStock($company, $ss_lines->all(), $customer, $params['order_from'], $stockPointId);
		if (!count($validated['valid'])) {
			return [null, $validated['invalid'], $message];
		}
		$lines = $validated['valid'];
		$lineNo = 10000;
		$newLineNo = 10000;
		$cnt = 1;
		foreach ($lines as $line) {
			$validLine = $line;
			$validLine['line_no'] = $newLineNo;
			$batch_nos[$newLineNo] = $line['batch_no'];
			$cnt++;
			$newLineNo = $lineNo * $cnt;
			$unitPrice = (float)$line['unit_price'];

			$product = Product::find($line['product_id']);
			//$ss_discount = round($this->ssDiscountService->getSSDiscount('DISTRIBUTO',$distributor,$product, $superStockist->id),2);
			$customerType = 'DISTRIBUTO';
			if ($distributor->super_stockist_id > 0) {
				$customerType = 'SUPER_STOCKIST_DISTRIBUTOR';
			}
			$ss_discount = round($this->discountService->getDiscount($customerType, $distributor, $product, $superStockist->id), 2);
			//Discount that is sent in params is for DUD to Distributor Order taken from Customer Discounts table, it needs to calculated for Dist to SS bbased on SS Discounts table
			$validLine['ss_discount'] = $ss_discount;
			$ss_discountedPrice = round($unitPrice - ($unitPrice * $ss_discount / 100), 2);
			$ss_lineAmount = $ss_discountedPrice * $line['quantity'];
			$validLine['ss_lineAmount'] = $ss_lineAmount;
			$ss_soAmount = $ss_soAmount + $ss_lineAmount;
			$discountedPrice = round($unitPrice - ($unitPrice * $line['discount'] / 100), 2); //$line['discount'] is the one provided in the cart item that is end customer discount, in this case DUD discount
			$lineAmount = $discountedPrice * $line['quantity'];
			$soAmount = $soAmount + $lineAmount;


			$taxRecord = $company->region->taxes()->where('category_code', '=', $product->category->code)->where('collection_code', '=', $product->collection->code)->first();
			if (!$taxRecord) {
				$taxRecord = $company->region->taxes()->where('category_code', '=', $product->category->code)->first();
			}
			if ($taxRecord) {
				$validLine['tax_type_code'] = $taxRecord->tax_type_code;
				$validLine['tax_percent'] = $taxRecord->tax_percent;
			} else {
				$validLine['tax_type_code'] = 0;
				$validLine['tax_percent'] = 0;
			}

			$validLines[] = $validLine;
		}
		//start of Dist to SS BO and PO
		$distPurchaseOrder = new PurchaseOrder;
		$distPurchaseOrder->shipping_method = $params['shipping_method'];
		$distPurchaseOrder->special_request = $params['special_request'];
		$UUId = Uuid::uuid4();
		$distPurchaseOrder->UUID = $UUId;
		$distPurchaseOrder->nav_key = $UUId;
		$distPurchaseOrder->status = 'Approved';
		$distPurchaseOrder->amount = $ss_soAmount;
		$distPurchaseOrder->company_id = $company->id;
		$distPurchaseOrder->super_stockist_id = $superStockist->id;
		$distPurchaseOrder->navision_order_number = '';
		if (array_has($params, 'end_customer_id'))
			$distPurchaseOrder->end_customer_id = $params['end_customer_id'];
		if (array_has($params, 'installer_id'))
			$distPurchaseOrder->engineer_id = $params['installer_id'];

		if ($stockPointId) {
			$distPurchaseOrder->ss_stock_point_id = $stockPointId;
		}
		$distributor->orders()->save($distPurchaseOrder);

		foreach ($validLines as $purchaseLine) //Distribbutor to SS PO
		{
			$distPurchaseOrderLine = new PurchaseLine;
			$distPurchaseOrderLine->product_id = $purchaseLine['product_id'];
			$distPurchaseOrderLine->nav_line_no = $purchaseLine['line_no'];
			$distPurchaseOrderLine->discount = $purchaseLine['ss_discount'];
			$distPurchaseOrderLine->unit_price = $purchaseLine['unit_price'];
			$distPurchaseOrderLine->quantity = $purchaseLine['quantity'];
			$distPurchaseOrderLine->rate_type = $purchaseLine['rate_type'];
			$distPurchaseOrderLine->amount = $purchaseLine['ss_lineAmount'];
			$distPurchaseOrderLine->batch_no = $purchaseLine['batch_no'];
			$distPurchaseOrderLine->tax_type_code = $purchaseLine['tax_type_code'];
			$distPurchaseOrderLine->tax_percent = $purchaseLine['tax_percent'];
			$distPurchaseOrderLine->tax_amount = $distPurchaseOrderLine->amount * $distPurchaseOrderLine->tax_percent / 100;
			$distPurchaseOrderLine->net_amount = $distPurchaseOrderLine->tax_amount + $distPurchaseOrderLine->amount;
			$distPurchaseOrder->purchase_lines()->save($distPurchaseOrderLine);
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
		$distPurchaseOrder->addresses()->save($address);

		$distBlanketOrder = new BlanketOrder; //SS Blanket Order
		if (array_has($params, 'source')) //#767
			$distBlanketOrder->source = $params['source'];
		if (array_has($params, 'OnBehalf')) //#767
			$distBlanketOrder->on_behalf = $params['OnBehalf'];
		$distBlanketOrder->company_id = $company->id;
		if ($customer->distributor->super_stockist_id)
			$distBlanketOrder->super_stockist_id = $customer->distributor->super_stockist_id;
		if (array_has($params, 'end_customer_id'))
			$distBlanketOrder->end_customer_id = $params['end_customer_id'];
		if (array_has($params, 'installer_id'))
			$distBlanketOrder->engineer_id = $params['installer_id'];
		$distBlanketOrder->save();

		$distPurchaseOrder->blanket_order_id = $distBlanketOrder->id; //SS BO Id on Distributor to SS's PO
		$distPurchaseOrder->save();
		$distributor->blanketOrders()->save($distBlanketOrder);
		if ($distBlanketOrder) {
			$purchaseOrder = new PurchaseOrder;
			$purchaseOrder->shipping_method = $params['shipping_method'];
			$purchaseOrder->special_request = $params['special_request'];
			$UUId = Uuid::uuid4();
			$purchaseOrder->UUID = $UUId;
			$purchaseOrder->nav_key = $UUId;
			$purchaseOrder->status = 'Approved';
			$purchaseOrder->amount = $soAmount;
			$purchaseOrder->company_id = $company->id;
			$purchaseOrder->distributor_id = $customer->distributor_id;
			$purchaseOrder->navision_order_number = '';
			if (array_has($params, 'end_customer_id'))
				$purchaseOrder->end_customer_id = $params['end_customer_id'];
			if (array_has($params, 'installer_id'))
				$purchaseOrder->engineer_id = $params['installer_id'];
			$purchaseOrder->blanket_order_id = $dudBlanketOrder->id;
			if ($stockPointId) {
				$purchaseOrder->ss_stock_point_id = $stockPointId;
			}
			$customer->orders()->save($purchaseOrder);
			foreach ($validLines as $purchaseLine) {
				$purchaseOrderLine = new PurchaseLine;
				$purchaseOrderLine->product_id = $purchaseLine['product_id'];
				$purchaseOrderLine->nav_line_no = $purchaseLine['line_no'];
				$purchaseOrderLine->discount = $purchaseLine['discount'];
				$purchaseOrderLine->unit_price = $purchaseLine['unit_price'];
				$purchaseOrderLine->quantity = $purchaseLine['quantity'];
				$purchaseOrderLine->rate_type = $purchaseLine['rate_type'];
				$purchaseOrderLine->amount = ($purchaseLine['unit_price'] - ($purchaseLine['unit_price'] * ($purchaseLine['discount'] / 100))) * round($purchaseLine['quantity'], 2);
				$purchaseOrderLine->tax_type_code = $purchaseLine['tax_type_code'];
				$purchaseOrderLine->tax_percent = $purchaseLine['tax_percent'];
				$purchaseOrderLine->tax_amount = $purchaseOrderLine->amount * $purchaseOrderLine->tax_percent / 100;
				$purchaseOrderLine->net_amount = $purchaseOrderLine->tax_amount + $purchaseOrderLine->amount;
				$purchaseOrderLine->batch_no = $purchaseLine['batch_no'];
				$purchaseOrder->purchase_lines()->save($purchaseOrderLine);
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
			$purchaseOrder->addresses()->save($address);

			//$purchase_lines = $validated['valid'];
			foreach ($lines as $line) {
				if ($params['order_from'] == "cart") {
					$item = CartItem::find($line['item_id']);
					$item->delete();
				} else if ($params['order_from'] == "blocked") {
					$item = BlockedItem::find($line['item_id']);
					$item->delete();
				}

				if ($stockPointId == $superStockist->id) {
					$currentStock = $superStockist->stocks->where('product_id', '=', $line['product_id'])
						->where('batch_no', '=', $line['batch_no'])->first();
					if (!empty($currentStock)) {
						$newStock = $currentStock->quantity - $line['quantity'];
						$currentStock->quantity = $newStock;
						$currentStock->save();
						//adjust SS Stock
					}
				}
			}
			$distBlanketOrder->related_po_id = $purchaseOrder->id; //DUD PO Id on Dist BO
			$distBlanketOrder->save();

			/** Create SS order for other SS stockpoint */
			if (($superStockist->id != $stockPointId) && $stockPointId != '') {
				$superStockist = SuperStockist::find($stockPointId);
				$ssToSsBlanketOrder = new BlanketOrder;
				//SS Blanket Order
				if (array_has($params, 'source')) //#767
					$ssToSsBlanketOrder->source = $params['source'];
				if (array_has($params, 'OnBehalf')) //#767
					$ssToSsBlanketOrder->on_behalf = $params['OnBehalf'];
				$ssToSsBlanketOrder->company_id = $company->id;
				if ($stockPointId)
					$ssToSsBlanketOrder->super_stockist_id = $stockPointId; //$customer->super_stockist_id;
				if (array_has($params, 'end_customer_id'))
					$ssToSsBlanketOrder->end_customer_id = $params['end_customer_id'];
				if (array_has($params, 'installer_id'))
					$ssToSsBlanketOrder->engineer_id = $params['installer_id'];
				$ssToSsBlanketOrder->save();

				if ($ssToSsBlanketOrder) {
					$ssToSsPurchaseOrder = new PurchaseOrder;
					$ssToSsPurchaseOrder->shipping_method = $params['shipping_method'];
					$ssToSsPurchaseOrder->special_request = $params['special_request'];
					$UUId = Uuid::uuid4();
					$ssToSsPurchaseOrder->UUID = $UUId;
					$ssToSsPurchaseOrder->nav_key = $UUId;
					$ssToSsPurchaseOrder->status = 'Approved';
					$ssToSsPurchaseOrder->amount = $soAmount;
					$ssToSsPurchaseOrder->company_id = $company->id;
					$ssToSsPurchaseOrder->navision_order_number = '';
					if ($stockPointId)
						$ssToSsPurchaseOrder->super_stockist_id = $stockPointId; //$customer->super_stockist_id;
					if (array_has($params, 'end_customer_id'))
						$ssToSsPurchaseOrder->end_customer_id = $params['end_customer_id'];
					if (array_has($params, 'installer_id'))
						$ssToSsPurchaseOrder->engineer_id = $params['installer_id'];
					$ssToSsPurchaseOrder->blanket_order_id = $ssToSsBlanketOrder->id;
					if ($stockPointId) {
						$ssToSsPurchaseOrder->ss_stock_point_id = $stockPointId;
					}
					$customer->distributor->superStockist->orders()->save($ssToSsPurchaseOrder);
					//$customer->superStockist->orders()->save($ssToSsPurchaseOrder);
					//$superStockist->orders()->save($ssToSsPurchaseOrder);
					foreach ($validLines as $purchaseLine) {
						$ssToSspurchaseOrderLine = new PurchaseLine;
						$ssToSspurchaseOrderLine->product_id = $purchaseLine['product_id'];
						$ssToSspurchaseOrderLine->nav_line_no = $purchaseLine['line_no'];
						$ssToSspurchaseOrderLine->discount = $purchaseLine['discount'];
						$ssToSspurchaseOrderLine->unit_price = $purchaseLine['unit_price'];
						$ssToSspurchaseOrderLine->quantity = $purchaseLine['quantity'];
						$ssToSspurchaseOrderLine->rate_type = $purchaseLine['rate_type'];
						$ssToSspurchaseOrderLine->amount = ($purchaseLine['unit_price'] - ($purchaseLine['unit_price'] * ($purchaseLine['discount'] / 100))) * round($purchaseLine['quantity'], 2);
						$ssToSspurchaseOrderLine->tax_type_code = $purchaseLine['tax_type_code'];
						$ssToSspurchaseOrderLine->tax_percent = $purchaseLine['tax_percent'];
						$ssToSspurchaseOrderLine->tax_amount = $ssToSspurchaseOrderLine->amount * $ssToSspurchaseOrderLine->tax_percent / 100;
						$ssToSspurchaseOrderLine->net_amount = $ssToSspurchaseOrderLine->tax_amount + $ssToSspurchaseOrderLine->amount;
						$ssToSspurchaseOrderLine->batch_no = $purchaseLine['batch_no'];
						$ssToSsPurchaseOrder->purchase_lines()->save($ssToSspurchaseOrderLine);
					}
					$sSOrderAddress = new Address;
					$sSOrderAddress->nav_address_code = $params['shipping_address']['code'];
					$sSOrderAddress->address = $params['shipping_address']['address'];
					$sSOrderAddress->address_2 = $params['shipping_address']['address_2'];
					$sSOrderAddress->post_code = $params['shipping_address']['post_code'];
					$sSOrderAddress->city = $params['shipping_address']['city'];
					$sSOrderAddress->state = $params['shipping_address']['state'];
					$sSOrderAddress->country_region_code = $params['shipping_address']['country_region_code'];
					$sSOrderAddress->contact = $params['shipping_address']['contact'];
					$sSOrderAddress->name = $params['shipping_address']['name'];
					$sSOrderAddress->phone_no = $params['shipping_address']['phone_no'];
					$ssToSsPurchaseOrder->addresses()->save($sSOrderAddress);

					//$purchase_lines = $validated['valid'];
					foreach ($lines as $line) {
						$currentStock = $superStockist->stocks->where('product_id', '=', $line['product_id'])
							->where('batch_no', '=', $line['batch_no'])->first();
						$newStock = $currentStock->quantity - $line['quantity'];
						$currentStock->quantity = $newStock;
						$currentStock->save();
						//adjust SS Stock
					}
					$ssToSsBlanketOrder->related_po_id = $distPurchaseOrder->id; //DUD PO Id on Dist BO
					$ssToSsBlanketOrder->save();
					$customer->distributor->superStockist->blanketOrders()->save($ssToSsBlanketOrder);
					//return [$purchaseOrder, $validated['invalid'], $message];
				}
			}

			return [$purchaseOrder, $validated['invalid'], $message];
		}
	}
	public function validatePurchaseLineswithSSStock(Company $company, array $lines, $customer, $order_from, $sSStockPointId = '')
	{
		if ((get_class($customer) == Distributor::class)) {
			$super_stockist_id = $customer->super_stockist_id;
		} elseif (!empty($customer->distributor)) {
			$super_stockist_id = $customer->distributor->super_stockist_id;
		} else {
			$super_stockist_id = $customer->id;
		}

		if (($sSStockPointId != $super_stockist_id) && $sSStockPointId != '') {
			$super_stockist_id = $sSStockPointId;
		}
		$superStockist = SuperStockist::find($super_stockist_id);
		$collection = collect($lines);
		$groupedByProduct = $collection->groupBy('product_id');
		$grouped = [];
		$validated = [
			'valid' => [],
			'invalid' => [],
		];
		foreach ($groupedByProduct as $product_id => $purchase_lines) {
			$purchaseLinesSorted = $purchase_lines->groupBy('batch_no');
			$group = [
				'product' => Product::find($product_id),
				'lines' => [],
			];
			foreach ($purchaseLinesSorted as $lot_no => $pLines) {
				$group['lines'][$lot_no] = $pLines->sortByDesc('quantity');
			}
			$grouped[] = $group;
		}
		foreach ($grouped as $group) {
			$product = $group['product'];
			foreach ($group['lines'] as $lot_no => $lines) {
				$quantity = 0;
				$stock = $superStockist->stocks->where('product_id', '=', $product->id)
					->where('batch_no', '=', $lot_no)
					->sum('quantity');

				$blockedQuntityQuery = BlockedItem::active()->where('product_id', '=', $product->id)
					->where('batch_no', '=', $lot_no)
					->where('super_stockist_id', '=', $super_stockist_id);
				if ($order_from == 'blocked') {
					$blockedQuntityQuery->where('blockable_id', '!=', $customer->id)
						->where('blockable_type', '=', get_class($customer));
				}

				$blockedQuntity = $blockedQuntityQuery->sum('order_quantity');

				$stock -= $blockedQuntity;
				foreach ($lines as $line) {
					if ($line['quantity'] <= ($stock - $quantity)) {
						$quantity += $line['quantity'];
						$validated['valid'][] = $line;
					} else {
						$validated['invalid'][] = $line;
					}
				}
			}
		}
		return $validated;
	}
	public function create(Company $company, $customer, $customerType, array $params, User $user, $company_lines, $blanketOrder, $stockPointId)
	{
		$message = null;
		$soAmount = 0;
		$recipients = [];
		$sender = $user;
		$purchaseOrder = new PurchaseOrder;
		$purchaseOrder->shipping_method = $params['shipping_method'];
		$purchaseOrder->special_request = array_get($params, 'special_request', '');
		$UUId = Uuid::uuid4();
		$purchaseOrder->UUID = $UUId;
		$serverUrl = config('app.url');
		$callBackUrl = $serverUrl . "/api/webhook/purchase_orders/" . $UUId;
		$stock_point = StockPoint::where('id', '=', $stockPointId)->first();
		$validated = $this->salesOrderService->validateSalesOrder($company, $company_lines, $customer, $params['order_from'], $stock_point->code);
		if (!count($validated['valid'])) {
			return [null, $validated['invalid'], $message];
		}
		$lines = $validated['valid'];
		$lineNo = 10000;
		$newLineNo = 10000;
		$cnt = 1;
		foreach ($lines as $line) {
			$validLine = $line;
			$validLine['line_no'] = $newLineNo;
			$batch_nos[$newLineNo] = $line['batch_no'];
			$cnt++;
			$newLineNo = $lineNo * $cnt;
			$validLines[] = $validLine;
			$unitPrice = (float)$line['unit_price'];
			$unitPrice = round($unitPrice - ($unitPrice * $line['discount'] / 100), 2);
			$lineAmount = $unitPrice * $line['quantity'];
			$soAmount = $soAmount + $lineAmount;
		}
		$paramsUpdates = [
			"shipping_method" => $params['shipping_method'],
			"special_request" => array_get($params, 'special_request', ''),
			"shipping_address" => $params['shipping_address'],
			"order_from" => $params['order_from'],
			"purchase_lines" => $validLines,
		];
		$validLinesCollection = collect($validLines);

		if ((get_class($customer) == SuperStockist::class)) {
			$superStockist = $customer;
		} elseif (get_class($customer) == Distributor::class) {
			$superStockist = $customer->superStockist;
		} else {
			$superStockist = $customer->distributor->superStockist;
		}

		/* update pricing for each line, take pricing for SS*/
		foreach ($paramsUpdates['purchase_lines'] as &$pLine) {
			$product = Product::find($pLine['product_id']);
			$discount = $this->discountService->getDiscount('SUPER_STOCKIST', $superStockist, $product);
			$priceObj = $product->prices()->where('company_id', '=', $company->id)->first();
			$price = 0;
			if ($priceObj) {
				$price = (float)$priceObj->price;
			}

			if ($product->category->allow_cl && ($pLine['rate_type'] == 'CL')) {
				$clPercent = $this->clRateService->getRate($company, $product, 'SUPER_STOCKIST', $superStockist->id);
				$price = $price + ($price * $clPercent / 100);
				$pLine['unit_price'] = $price;
			} else {
				$pLine['unit_price'] = $price;
			}

			$pLine['discount'] = (float)$discount;
		}
		$navPurchaseOrder = $this->salesOrderService->create($company, $superStockist, $paramsUpdates, $callBackUrl, $batch_nos, $stock_point->code);

		$purchaseOrder->navision_order_number = $navPurchaseOrder->getNo();
		if ($navPurchaseOrder->getStatus() == 'Released') {
			$purchaseOrder->status = 'Approved';
		} else {
			$purchaseOrder->status = $navPurchaseOrder->getStatus();
		}
		$purchaseOrder->amount = $navPurchaseOrder->getAmount();
		$purchaseOrder->nav_key = $navPurchaseOrder->getKey();
		$purchaseOrder->company_id = $company->id;
		$purchaseOrder->blanket_order_id = $blanketOrder->id;
		$purchaseOrder->stock_point_id = $stockPointId;
		$superStockist->orders()->save($purchaseOrder);

		$navPurchaseLines = $navPurchaseOrder->getSalesLines()->getSales_Order_Line();
		if (!is_array($navPurchaseLines)) {
			$navPurchaseLines = [$navPurchaseLines];
		}
		$paramsUpdatesLineCollection = collect($paramsUpdates['purchase_lines']);
		foreach ($navPurchaseLines as $purchaseLine) //Lines from NAV
		{
			$line_id = $purchaseLine->getLine_No();
			$purchaseOrderLine = new PurchaseLine;
			$product = Product::where('nav_id', '=', $purchaseLine->getNo())->get()->first();
			$tax = $company->region->taxes()->where('category_code', '=', $product->category->code)->where('collection_code', '=', $product->collection->code)->first();
			if (!$tax) {
				$tax = $company->region->taxes()->where('category_code', '=', $product->category->code)->first();
			}
			if ($tax) {
				$purchaseOrderLine->tax_type_code = $tax->tax_type_code;
				$purchaseOrderLine->tax_percent = $tax->tax_percent;
				$purchaseOrderLine->tax_amount = ((float) $purchaseLine->getLine_Amount()) * $purchaseOrderLine->tax_percent / 100;
			} else {
				$purchaseOrderLine->tax_percent = 0;
				$purchaseOrderLine->tax_amount = 0;
			}
			$purchaseOrderLine->product_id = $product->id;
			$purchaseOrderLine->nav_line_no = $purchaseLine->getLine_No();
			$line = $validLinesCollection->where('line_no', '=', $purchaseLine->getLine_No())->first();
			$line1 = $paramsUpdatesLineCollection->where('product_id', '=', $product->id)
				->where('batch_no', '=', $batch_nos[$line_id])
				->first();
			$purchaseOrderLine->discount = $line1['discount'];
			$purchaseOrderLine->unit_price = $line1['unit_price'];
			$purchaseOrderLine->quantity = $purchaseLine->getQuantity();
			$purchaseOrderLine->rate_type = $purchaseLine->getRate_Type();
			$purchaseOrderLine->amount = $purchaseLine->getLine_Amount();
			$purchaseOrderLine->net_amount = $purchaseOrderLine->tax_amount + $purchaseOrderLine->amount;

			$purchaseOrderLine->batch_no = $batch_nos[$line_id];
			$purchaseOrder->purchase_lines()->save($purchaseOrderLine);
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
		$purchaseOrder->addresses()->save($address);
		$purchaseOrder->load('purchase_lines');
		$purchaseOrder->load('addresses');

		$purchase_lines = $validated['valid'];
		foreach ($purchase_lines as $purchase_line) {
			if ($params['order_from'] == "cart") {
				$item = CartItem::find($purchase_line['item_id']);
				$item->delete();
			} else if ($params['order_from'] == "blocked") {
				$item = BlockedItem::find($purchase_line['item_id']);
				$UUId = $item->UUID;
				$this->depotService->unBlock($UUId, $customer->company); //#672
				$item->delete();
			}
		}

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
			->where('company_id', '=', null)
			->where('super_stockist_id', '=', null);
		$superAdminEmailQuery->whereHas('roles', function ($q) {
			$q->where('name', '=', 'superadmin');
		});
		$superAdminEmailIds = $superAdminEmailQuery->get();
		foreach ($superAdminEmailIds as $user) {
			if ($user->communication_email)
				$toEmailIds[] = $user->communication_email;
			$recipients[] = $user;
		} //add Super Admin Email Ids
		if ($customerType == 'dealers' && $customer->distributor_id != null) //email to Dud
		{
			$superStockistId = (get_class($customer) == Distributor::class) ? $customer->super_stockist_id : $customer->distributor->super_stockist_id;
			$q = User::where('super_stockist_id', '=', $superStockistId);
			$q->whereHas('roles', function ($q1) {
				$q1->where('name', '=', 'superStockistAdmin');
			});
			$sender = $q->first();
			$mainSSUsers = $q->get();
			foreach ($mainSSUsers as $user) {
				if ($user->communication_email)
					$toMainSSEmailIds[] = $user->communication_email;
				$mainSSRecipients[] = $user;
			}
			CustomHelper::sendWhatsappNotification($blanketOrder);
			foreach ($mainSSRecipients as $recipient) {
				$notification = new Notification;
				$notification->title = 'Created new Purchase Order';
				$notification->text = 'New Purchase ' . $blanketOrder->order_number . ' has been created';
				$notification->sender_id = $sender->id;
				$notification->recipient_id = $recipient->id;
				$notification->author()->associate($sender);
				$notification->sender()->associate($sender);
				$notification->recipient()->associate($recipient);
				$notification->save();
				event(new Notifications($notification, $blanketOrder));
			}
			$queuePrefix = config('brand.queue_prefix');
			$queue = $queuePrefix . '.emails';
			$Qmessage = (new PurchaseOrderNew($blanketOrder))->onQueue($queue);
			try {
				//if(!empty($toMainSSEmailIds))
				//	Mail::to($toMainSSEmailIds)->queue($Qmessage);
			} catch (ConnectionException $e) {
				throw new RedisException;
			}
		}

		CustomHelper::sendWhatsappNotification($blanketOrder);
		foreach ($recipients as $recipient) {
			$notification = new Notification;
			$notification->title = 'Created new Purchase Order';
			$notification->text = 'New Purchase Order ' . $blanketOrder->order_number . ' has been created';
			$notification->sender_id = $sender->id;
			$notification->recipient_id = $recipient->id;
			$notification->author()->associate($sender);
			$notification->sender()->associate($sender);
			$notification->recipient()->associate($recipient);
			$notification->save();
			event(new Notifications($notification, $blanketOrder));
		}
		$queuePrefix = config('brand.queue_prefix');
		$queue = $queuePrefix . '.emails';
		$Qmessage = (new PurchaseOrderNew($blanketOrder))->onQueue($queue);
		try {
			//if(!empty($toEmailIds))
			//	Mail::to($toEmailIds)->queue($Qmessage);
		} catch (ConnectionException $e) {
			throw new RedisException;
		}
		return [$purchaseOrder, $validated['invalid'], $message];
	}

	public function createDealerToDistributorOrder(Company $company, $customer, array $params, User $user, $distributor_lines, $dudBlanketOrder, $stockPointId)
	{
		$distributor = Distributor::find($customer->distributor_id);

		$message = null;
		$soAmount = 0;
		$ss_soAmount = 0;
		$recipients = [];
		$sender = $user;

		$validated = $this->validatePurchaseLineswithDistributorsStock($company, $distributor_lines->all(), $customer, $params['order_from'], $stockPointId);

		if (!count($validated['valid'])) {
			return [null, $validated['invalid'], $message];
		}
		$lines = $validated['valid'];
		$lineNo = 10000;
		$newLineNo = 10000;
		$cnt = 1;
		foreach ($lines as $line) {
			$validLine = $line;
			$validLine['line_no'] = $newLineNo;
			$batch_nos[$newLineNo] = $line['batch_no'];
			$cnt++;
			$newLineNo = $lineNo * $cnt;
			$unitPrice = (float)$line['unit_price'];

			$product = Product::find($line['product_id']);

			$customerType = 'DISTRIBUTO';
			if ($distributor->super_stockist_id > 0) {
				$customerType = 'SUPER_STOCKIST_DISTRIBUTOR';
			}
			if (($distributor->super_stockist_id != $stockPointId) && $stockPointId != '') {
				$customerType = 'DISTRIBUTO';
			}

			$ss_discount = round($this->discountService->getDiscount($customerType, $distributor, $product), 2); //Discount that is sent in params is for DUD to Distributor Order taken from Customer Discounts table, it needs to calculated for Dist to SS bbased on SS Discounts table
			$validLine['ss_discount'] = $ss_discount;
			$ss_discountedPrice = round($unitPrice - ($unitPrice * $ss_discount / 100), 2);
			$ss_lineAmount = $ss_discountedPrice * $line['quantity'];
			$validLine['ss_lineAmount'] = $ss_lineAmount;
			$ss_soAmount = $ss_soAmount + $ss_lineAmount;
			$discountedPrice = round($unitPrice - ($unitPrice * $line['discount'] / 100), 2); //$line['discount'] is the one provided in the cart item that is end customer discount, in this case DUD discount
			$lineAmount = $discountedPrice * $line['quantity'];
			$soAmount = $soAmount + $lineAmount;


			$taxRecord = $company->region->taxes()->where('category_code', '=', $product->category->code)->where('collection_code', '=', $product->collection->code)->first();
			if (!$taxRecord) {
				$taxRecord = $company->region->taxes()->where('category_code', '=', $product->category->code)->first();
			}
			if ($taxRecord) {
				$validLine['tax_type_code'] = $taxRecord->tax_type_code;
				$validLine['tax_percent'] = $taxRecord->tax_percent;
			} else {
				$validLine['tax_type_code'] = 0;
				$validLine['tax_percent'] = 0;
			}

			$validLines[] = $validLine;
		}
		//start of Dist to SS BO and PO
		$distPurchaseOrder = new PurchaseOrder;
		$distPurchaseOrder->shipping_method = $params['shipping_method'];
		$distPurchaseOrder->special_request = $params['special_request'];
		$UUId = Uuid::uuid4();
		$distPurchaseOrder->UUID = $UUId;
		$distPurchaseOrder->nav_key = $UUId;
		$distPurchaseOrder->status = 'Approved';
		$distPurchaseOrder->amount = $ss_soAmount;
		$distPurchaseOrder->company_id = $company->id;
		$distPurchaseOrder->distributor_id = $distributor->id;
		$distPurchaseOrder->blanket_order_id = $dudBlanketOrder->id;
		$distPurchaseOrder->navision_order_number = '';
		if (array_has($params, 'end_customer_id'))
			$distPurchaseOrder->end_customer_id = $params['end_customer_id'];
		if (array_has($params, 'installer_id'))
			$distPurchaseOrder->engineer_id = $params['installer_id'];

		if ($stockPointId) {
			if (get_class($customer) == Dealer::class) {
				$distPurchaseOrder->dist_stock_point_id = $stockPointId;
				//$distPurchaseOrder->distributor_id = $stockPointId;
			} else {
				$distPurchaseOrder->ss_stock_point_id = $stockPointId;
			}
		}
		$customer->orders()->save($distPurchaseOrder);

		foreach ($validLines as $purchaseLine) //Distribbutor to SS PO
		{
			$distPurchaseOrderLine = new PurchaseLine;
			$distPurchaseOrderLine->product_id = $purchaseLine['product_id'];
			$distPurchaseOrderLine->nav_line_no = $purchaseLine['line_no'];
			$distPurchaseOrderLine->discount = $purchaseLine['ss_discount'];
			$distPurchaseOrderLine->unit_price = $purchaseLine['unit_price'];
			$distPurchaseOrderLine->quantity = $purchaseLine['quantity'];
			$distPurchaseOrderLine->rate_type = $purchaseLine['rate_type'];
			$distPurchaseOrderLine->amount = $purchaseLine['ss_lineAmount'];
			$distPurchaseOrderLine->batch_no = $purchaseLine['batch_no'];
			$distPurchaseOrderLine->tax_type_code = $purchaseLine['tax_type_code'];
			$distPurchaseOrderLine->tax_percent = $purchaseLine['tax_percent'];
			$distPurchaseOrderLine->tax_amount = $distPurchaseOrderLine->amount * $distPurchaseOrderLine->tax_percent / 100;
			$distPurchaseOrderLine->net_amount = $distPurchaseOrderLine->tax_amount + $distPurchaseOrderLine->amount;
			$distPurchaseOrder->purchase_lines()->save($distPurchaseOrderLine);
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
		$distPurchaseOrder->addresses()->save($address);


		/** Create Distributor order for other Distributor stockpoint */
		if (($distributor->id != $stockPointId) && $stockPointId != '') {
			$stokistDistributor = Distributor::find($stockPointId);
			$distToDistBlanketOrder = new BlanketOrder;
			//SS Blanket Order
			if (array_has($params, 'source')) //#767
				$distToDistBlanketOrder->source = $params['source'];
			if (array_has($params, 'OnBehalf')) //#767
				$distToDistBlanketOrder->on_behalf = $params['OnBehalf'];
			$distToDistBlanketOrder->company_id = $company->id;

			//if ($stockPointId)
			$distToDistBlanketOrder->distributor_id = $stockPointId; //$customer->distributor_id;
			if (array_has($params, 'end_customer_id'))
				$distToDistBlanketOrder->end_customer_id = $params['end_customer_id'];
			if (array_has($params, 'installer_id'))
				$distToDistBlanketOrder->engineer_id = $params['installer_id'];
			$distToDistBlanketOrder->save();

			if ($distToDistBlanketOrder) {
				$distToDistPurchaseOrder = new PurchaseOrder;
				$distToDistPurchaseOrder->shipping_method = $params['shipping_method'];
				$distToDistPurchaseOrder->special_request = $params['special_request'];
				$UUId = Uuid::uuid4();
				$distToDistPurchaseOrder->UUID = $UUId;
				$distToDistPurchaseOrder->nav_key = $UUId;
				$distToDistPurchaseOrder->status = 'Approved';
				$distToDistPurchaseOrder->amount = $soAmount;
				$distToDistPurchaseOrder->company_id = $company->id;
				$distToDistPurchaseOrder->navision_order_number = '';
				if (array_has($params, 'end_customer_id'))
					$distToDistPurchaseOrder->end_customer_id = $params['end_customer_id'];
				if (array_has($params, 'installer_id'))
					$distToDistPurchaseOrder->engineer_id = $params['installer_id'];
				$distToDistPurchaseOrder->blanket_order_id = $distToDistBlanketOrder->id;
				if ($stockPointId) {
					$distToDistPurchaseOrder->dist_stock_point_id = $stockPointId;
					$distToDistPurchaseOrder->distributor_id = $stockPointId;
				}
				$customer->distributor->orders()->save($distToDistPurchaseOrder);
				//$customer->superStockist->orders()->save($distToDistPurchaseOrder);
				//$superStockist->orders()->save($distToDistPurchaseOrder);
				foreach ($validLines as $purchaseLine) {
					$distToDistPurchaseOrderLine = new PurchaseLine;
					$distToDistPurchaseOrderLine->product_id = $purchaseLine['product_id'];
					$distToDistPurchaseOrderLine->nav_line_no = $purchaseLine['line_no'];
					$distToDistPurchaseOrderLine->discount = $purchaseLine['discount'];
					$distToDistPurchaseOrderLine->unit_price = $purchaseLine['unit_price'];
					$distToDistPurchaseOrderLine->quantity = $purchaseLine['quantity'];
					$distToDistPurchaseOrderLine->rate_type = $purchaseLine['rate_type'];
					$distToDistPurchaseOrderLine->amount = ($purchaseLine['unit_price'] - ($purchaseLine['unit_price'] * ($purchaseLine['discount'] / 100))) * round($purchaseLine['quantity'], 2);
					$distToDistPurchaseOrderLine->tax_type_code = $purchaseLine['tax_type_code'];
					$distToDistPurchaseOrderLine->tax_percent = $purchaseLine['tax_percent'];
					$distToDistPurchaseOrderLine->tax_amount = $distToDistPurchaseOrderLine->amount * $distToDistPurchaseOrderLine->tax_percent / 100;
					$distToDistPurchaseOrderLine->net_amount = $distToDistPurchaseOrderLine->tax_amount + $distToDistPurchaseOrderLine->amount;
					$distToDistPurchaseOrderLine->batch_no = $purchaseLine['batch_no'];
					$distToDistPurchaseOrder->purchase_lines()->save($distToDistPurchaseOrderLine);
				}
				$sSOrderAddress = new Address;
				$sSOrderAddress->nav_address_code = $params['shipping_address']['code'];
				$sSOrderAddress->address = $params['shipping_address']['address'];
				$sSOrderAddress->address_2 = $params['shipping_address']['address_2'];
				$sSOrderAddress->post_code = $params['shipping_address']['post_code'];
				$sSOrderAddress->city = $params['shipping_address']['city'];
				$sSOrderAddress->state = $params['shipping_address']['state'];
				$sSOrderAddress->country_region_code = $params['shipping_address']['country_region_code'];
				$sSOrderAddress->contact = $params['shipping_address']['contact'];
				$sSOrderAddress->name = $params['shipping_address']['name'];
				$sSOrderAddress->phone_no = $params['shipping_address']['phone_no'];
				$distToDistPurchaseOrder->addresses()->save($sSOrderAddress);

				//$purchase_lines = $validated['valid'];
				foreach ($lines as $line) {
					$currentStock = $stokistDistributor->stocks->where('product_id', '=', $line['product_id'])
						->where('batch_no', '=', $line['batch_no'])->first();
					$newStock = $currentStock->quantity - $line['quantity'];
					$currentStock->quantity = $newStock;
					$currentStock->save();
					//adjust Distributor Stock
				}
				$distToDistBlanketOrder->related_po_id = $distPurchaseOrder->id; //DUD PO Id on Dist BO
				$distToDistBlanketOrder->save();
				$customer->distributor->blanketOrders()->save($distToDistBlanketOrder);
				//return [$purchaseOrder, $validated['invalid'], $message];
			}
		}

		//$purchase_lines = $validated['valid'];
		foreach ($lines as $line) {
			if ($params['order_from'] == "cart") {
				$item = CartItem::find($line['item_id']);
				(!empty($item)) ? $item->delete() : null;
			} else if ($params['order_from'] == "blocked") {
				$item = BlockedItem::find($line['item_id']);
				(!empty($item)) ? $item->delete() : null;
			}
			if ($stockPointId > 0) {
				$otherDistributorHasStock = Distributor::find($stockPointId);
				$currentStock = $otherDistributorHasStock->stocks->where('product_id', '=', $line['product_id'])
					->where('batch_no', '=', $line['batch_no'])->first();
			} else {
				$currentStock = $distributor->stocks->where('product_id', '=', $line['product_id'])
					->where('batch_no', '=', $line['batch_no'])->first();
			}
			$newStock = $currentStock->quantity - $line['quantity'];
			$currentStock->quantity = $newStock;
			$currentStock->save();
			//adjust distributor Stock
		}

		return [$distPurchaseOrder, $validated['invalid'], $message];
	}

	public function validatePurchaseLineswithDistributorsStock(Company $company, array $lines, $customer, $order_from, $distStockPointId = '')
	{
		if ((get_class($customer) == Dealer::class)) {
			$distributor_id = $customer->distributor_id;
		} else {
			$distributor_id = $customer->id;
		}

		if (($distStockPointId != $distributor_id) && $distStockPointId != '') {
			$distributor_id = $distStockPointId;
		}

		$distributor = Distributor::find($distributor_id);
		$collection = collect($lines);
		$groupedByProduct = $collection->groupBy('product_id');
		$grouped = [];
		$validated = [
			'valid' => [],
			'invalid' => [],
		];
		foreach ($groupedByProduct as $product_id => $purchase_lines) {
			$purchaseLinesSorted = $purchase_lines->groupBy('batch_no');
			$group = [
				'product' => Product::find($product_id),
				'lines' => [],
			];
			foreach ($purchaseLinesSorted as $lot_no => $pLines) {
				$group['lines'][$lot_no] = $pLines->sortByDesc('quantity');
			}
			$grouped[] = $group;
		}
		foreach ($grouped as $group) {
			$product = $group['product'];
			foreach ($group['lines'] as $lot_no => $lines) {
				$quantity = 0;
				$stock = $distributor->stocks->where('product_id', '=', $product->id)
					->where('batch_no', '=', $lot_no)
					->sum('quantity');

				$blockedQuntityQuery = BlockedItem::active()->where('product_id', '=', $product->id)
					->where('batch_no', '=', $lot_no)
					->where('distributor_id', '=', $distributor_id);
				if ($order_from == 'blocked') {
					$blockedQuntityQuery->where('blockable_id', '!=', $customer->id)
						->where('blockable_type', '=', get_class($customer));
				}

				$blockedQuntity = $blockedQuntityQuery->sum('order_quantity');

				$stock -= $blockedQuntity;
				foreach ($lines as $line) {
					if ($line['quantity'] <= ($stock - $quantity)) {
						$quantity += $line['quantity'];
						$validated['valid'][] = $line;
					} else {
						$validated['invalid'][] = $line;
					}
				}
			}
		}
		return $validated;
	}

	public function createBlanketOrderForDistributor(Company $company, $customer, array $params, User $user, $customerType, $sSStockPointId = '')
	{		
		$superStockist = null;
		if ((get_class($customer) == SuperStockist::class)) {
			$superStockist = $customer;
		} elseif (get_class($customer) == Distributor::class) {
			$superStockist = $customer->superStockist;
		} elseif (!empty($customer->distributor->superStockist) && get_class($customer) == Dealer::class) {
			$superStockist = $customer->distributor->superStockist;
		}
		$recipients = [];
		$toEmailIds = [];
		$toDealerEmailIds = [];
		$sender = $user;

		$collection = collect($params['purchase_lines']);
		$orderAmount = $collection->sum('amount');
		$creditLimit = $customer->credit_limit;
		$balanceDue = $customer->overdue_balance;

		if ($orderAmount > $creditLimit) {
			$message = "Your order cannot be processed at this time due to negative credit limit. Please contact your Relationship Manager for further actions.";
			//return [null, null, $message];
		}
		if ($orderAmount <= $creditLimit && $balanceDue > 0) {
			$message = "Your order cannot be processed at this time due to overdue payments. Please contact your Relationship Manager for further actions.";
			return [null, null, $message];
		}

		$groupedByStockPoint = $collection->groupBy('stock_point_id');
		$allInvalidLines = collect([]);
		$message = null;
		$orderNotPlacedCnt = 0;
		$stockPointCnt = 0;
		$companyStockPointCnt = 0;
		$distToSSBlanketOrder = new BlanketOrder;
		if (array_has($params, 'source')) //#767
			$distToSSBlanketOrder->source = $params['source'];
		if (array_has($params, 'OnBehalf')) //#767
			$distToSSBlanketOrder->on_behalf = $params['OnBehalf'];
		$distToSSBlanketOrder->company_id = $company->id;
		if ($customer->distributor_id)
			$distToSSBlanketOrder->distributor_id = $customer->distributor_id;
		if ($customer->super_stockist_id)
			$distToSSBlanketOrder->super_stockist_id = $customer->super_stockist_id;
		if (array_has($params, 'end_customer_id'))
			$distToSSBlanketOrder->end_customer_id = $params['end_customer_id'];
		if (array_has($params, 'installer_id'))
			$distToSSBlanketOrder->engineer_id = $params['installer_id'];
		$distToSSBlanketOrder->save();

		foreach ($groupedByStockPoint as $stock_point_id => $purchase_lines) {
			$stockPointCnt++;
			if ($stock_point_id === "" || $stock_point_id === null) //SS or Co SS Stock
			{

				$ss_lines = collect($purchase_lines);
				$ssLinesGrouped = $ss_lines->groupBy('super_stockist_id');

				foreach ($ssLinesGrouped as $grouped_ss_id => $ss_purchase_lines) {
					if ($grouped_ss_id == $customer->super_stockist_id) {
						/* Distributor's SS's Stock */
						list($purchaseOrder, $invalidLines, $message) = $this->createDistToSSOrderWhenCustomerIsDistributor($company, $customer, $params, $user, $ss_purchase_lines, $distToSSBlanketOrder, $grouped_ss_id);
						if (count($invalidLines)) {
							$allInvalidLines = $allInvalidLines->concat($invalidLines);
						}
						if ($purchaseOrder === null) {
							$orderNotPlacedCnt++;
						}
					} else if ($grouped_ss_id > 0) {
						/*Get stock from another SS*/
						//$distToSSBlanketOrder->super_stockist_id = $grouped_ss_id;
						//$distToSSBlanketOrder->save();
						if ($customerType == "super_stockists") {
							list($purchaseOrder, $invalidLines, $message) = $this->createSSToSSOrderWhenCustomerIsSS($company, $customer, $params, $user, $ss_purchase_lines, $distToSSBlanketOrder, $grouped_ss_id);
						} elseif ($customerType == "dealers") {
							list($purchaseOrder, $invalidLines, $message) = $this->createBlanketOrderForSsByDealerUnderCompany($company, $customer, $params, $user, $ss_purchase_lines, $distToSSBlanketOrder, $grouped_ss_id);
						} else {
							list($purchaseOrder, $invalidLines, $message) = $this->createDistToSSOrderWhenCustomerIsDistributor($company, $customer, $params, $user, $ss_purchase_lines, $distToSSBlanketOrder, $grouped_ss_id);
						}

						if (count($invalidLines)) {
							$allInvalidLines = $allInvalidLines->concat($invalidLines);
						}
						if ($purchaseOrder === null) {
							$orderNotPlacedCnt++;
						}
					} else {
						/*Co SS Stock*/
						$distributorLinesGrouped = $ss_purchase_lines->groupBy('distributor_id');
						foreach ($distributorLinesGrouped as $grouped_dist_id => $dist_purchase_lines) {
							/* Distributor's SS's Stock */
							list($purchaseOrder, $invalidLines, $message) = $this->createDistToDistOrderWhenCustomerIsDistributor($company, $customer, $params, $user, $dist_purchase_lines, $distToSSBlanketOrder, $grouped_dist_id);

							if (count($invalidLines)) {
								$allInvalidLines = $allInvalidLines->concat($invalidLines);
							}
							if ($purchaseOrder === null) {
								$orderNotPlacedCnt++;
							}
							// } else {
							// 	/*Co SS Stock*/
							// }
						}
					}
				}
			} else //Company Stock
			{
				/*$ssToCompBlanketOrder = new BlanketOrder;
				if (array_has($params, 'source')) //#767
					$ssToCompBlanketOrder->source = $params['source'];
				if (array_has($params, 'OnBehalf')) //#767
					$ssToCompBlanketOrder->on_behalf = $params['OnBehalf'];
				$ssToCompBlanketOrder->company_id = $company->id;
				$ssToCompBlanketOrder->save();*/

				$stock_point = StockPoint::where('id', '=', $stock_point_id)->first();
				$company_lines = $purchase_lines;
				list($ssToCompPurchaseOrder, $invalidLines, $message) = $this->create($company, $customer, $customerType, $params, $user, $company_lines, $distToSSBlanketOrder, $stock_point_id);
				//$distToSSBlanketOrder->related_po_id = $ssToCompPurchaseOrder->id;
				/* create new purchase orders for dist to SS and dealer with distributor*/
				if ((get_class($customer) != SuperStockist::class) && $ssToCompPurchaseOrder) {
					///////DIST SS ORDERING
					$distToSSBlanketOrderNew = new BlanketOrder;
					if (array_has($params, 'source')) //#767
						$distToSSBlanketOrderNew->source = $params['source'];
					if (array_has($params, 'OnBehalf')) //#767
						$distToSSBlanketOrderNew->on_behalf = $params['OnBehalf'];
					$distToSSBlanketOrderNew->company_id = $company->id;
					$distToSSBlanketOrderNew->super_stockist_id = $customer->super_stockist_id;
					$distToSSBlanketOrderNew->save();
					/* this is order placed for dealer create new PO based on original PO*/
					$distToSSPurchaseOrder = new PurchaseOrder;
					$distToSSPurchaseOrder->shipping_method = $params['shipping_method'];
					$distToSSPurchaseOrder->special_request = $params['special_request'];
					$distToSSPurchaseOrder->UUID = Uuid::uuid4();
					$distToSSPurchaseOrder->navision_order_number = '';
					$distToSSPurchaseOrder->super_stockist_id = $customer->super_stockist_id;
					$distToSSPurchaseOrder->blanket_order_id = $distToSSBlanketOrderNew->id;
					$distToSSPurchaseOrder->stock_point_id = $stock_point_id;
					$distToSSPurchaseOrder->nav_key = '';
					$distToSSPurchaseOrder->company_id = $company->id;
					$distToSSPurchaseOrder->status = $ssToCompPurchaseOrder->status;
					$distToSSPurchaseOrder->shipping_method = $params['shipping_method'];
					$distToSSPurchaseOrder->special_request = $params['special_request'];
					$distToSSPurchaseOrder->save();

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
					$distToSSPurchaseOrder->addresses()->save($address);

					$originalParamLines = collect($params['purchase_lines']);
					$totalAmount = 0;
					foreach ($ssToCompPurchaseOrder->purchase_lines as $temp_purchase_line) {
						$tempLine = new PurchaseLine;
						$tempLine->nav_line_no = $temp_purchase_line->nav_line_no;
						$tempLine->product_id = $temp_purchase_line->product_id;
						$tempLine->purchase_order_id = $distToSSPurchaseOrder->id;
						$tempLine->quantity = $temp_purchase_line->quantity;
						$tempLine->batch_no = $temp_purchase_line->batch_no;
						$selectedLine = $originalParamLines->where('product_id', '=', $temp_purchase_line->product_id)
							->where('batch_no', '=', $temp_purchase_line->batch_no)
							->where('quantity', '=', round($temp_purchase_line->quantity, 2)) //#843
							->first();
						$tempLine->unit_price = $selectedLine['unit_price'];

						$sSStockPointId = (!empty($superStockist->id)) ? $superStockist->id : $sSStockPointId;
						$product = Product::find($temp_purchase_line->product_id);
						//$ss_discount = ($sSStockPointId)?round($this->ssDiscountService->getSSDiscount('DISTRIBUTO', $customer, $product, $sSStockPointId), 2):0;

						$customerType = 'DISTRIBUTO';
						if ($customer->super_stockist_id > 0) {
							$customerType = 'SUPER_STOCKIST_DISTRIBUTOR';
						}
						if (($customer->super_stockist_id != $sSStockPointId) && $sSStockPointId != '') {
							$customerType = 'DISTRIBUTO';
						}
						$ss_discount = ($sSStockPointId) ? round($this->discountService->getDiscount($customerType, $customer, $product, $sSStockPointId), 2) : 0;

						$tempLine->discount = $ss_discount;

						$ss_discountedPrice = round($selectedLine['unit_price'] - ($selectedLine['unit_price'] * $ss_discount / 100), 2);
						$ss_lineAmount = $ss_discountedPrice * $temp_purchase_line->quantity;
						$tempLine->amount = $ss_lineAmount;

						$tempLine->rate_type = $temp_purchase_line->rate_type;
						$tempLine->tax_type_code = $temp_purchase_line->tax_type_code;
						$tempLine->tax_percent = $temp_purchase_line->tax_percent;
						$tempLine->tax_amount = ((float) $tempLine->amount) * $tempLine->tax_percent / 100;
						$tempLine->net_amount = $tempLine->tax_amount + $tempLine->amount;
						$distToSSPurchaseOrder->purchase_lines()->save($tempLine);
						$totalAmount += (float)$tempLine->amount;
					}
					$distToSSPurchaseOrder->Amount = $totalAmount;
					$distToSSPurchaseOrder->save();

					//$ssToCompBlanketOrder->related_po_id = $distToSSPurchaseOrder->id;
					//$ssToCompBlanketOrder->save();

					$customer->orders()->save($distToSSPurchaseOrder);
					/*
					$distToSSBlanketOrder->related_po_id = $distToSSPurchaseOrder->id;
					$distToSSBlanketOrder->super_stockist_id = $customer->super_stockist_id;
					$customer->blanketOrders()->save($distToSSBlanketOrder);
					*/
					$superStockist->orders()->save($ssToCompPurchaseOrder);
					$superStockist->blanketOrders()->save($distToSSBlanketOrder);
				}
				
				if (count($invalidLines)) {
					$allInvalidLines = $allInvalidLines->concat($invalidLines);
				}
				if ($ssToCompPurchaseOrder === null) {
					$orderNotPlacedCnt++;
				}
				$companyStockPointCnt++;				
				$distToSSBlanketOrder->super_stockist_id = null;
				$distToSSBlanketOrder->save();

				if(!empty($distToSSBlanketOrderNew)){
					$distToSSBlanketOrder = $distToSSBlanketOrderNew;
				}				
			}
		}

		$customer->blanketOrders()->save($distToSSBlanketOrder);

		if ($orderNotPlacedCnt == $stockPointCnt) {
			$distToSSBlanketOrder->forceDelete();
			if ($distToSSBlanketOrder)
				$distToSSBlanketOrder->forceDelete();
			if (!empty($ssToCompBlanketOrder))
				$ssToCompBlanketOrder->forceDelete();
			return [null, $allInvalidLines, $message];
		}
		if ($allInvalidLines->count() == count($params['purchase_lines'])) {
			$distToSSBlanketOrder->forceDelete();
			if ($distToSSBlanketOrder)
				$distToSSBlanketOrder->forceDelete();
			if (!empty($ssToCompBlanketOrder))
				$ssToCompBlanketOrder->forceDelete();
			return [null, $allInvalidLines, $message];
		}

		if ($customerType == 'dealers') {
			$query = User::where('dealer_id', '=', $customer->id);
			$query->whereHas('roles', function ($q) {
				$q->where('name', '=', 'dealerAdmin');
			});
			$users = $query->get();
			foreach ($users as $user) {
				if ($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
			}
		} //add Customer Admin Email Ids

		CustomHelper::sendWhatsappNotification($distToSSBlanketOrder);
		foreach ($recipients as $recipient) {
			$notification = new Notification;
			$notification->title = 'Created new Purchase Order';
			$notification->text = 'New Purchase Order ' . $distToSSBlanketOrder->order_number . ' has been created';
			$notification->sender_id = $sender->id;
			$notification->recipient_id = $recipient->id;
			$notification->author()->associate($sender);
			$notification->sender()->associate($sender);
			$notification->recipient()->associate($recipient);
			$notification->save();
			event(new Notifications($notification, $distToSSBlanketOrder));
		}
		$queuePrefix = config('brand.queue_prefix');
		$queue = $queuePrefix . '.emails';
		$data = [];

		$Qmessage = (new PurchaseOrderNewDud($distToSSBlanketOrder))->onQueue($queue);
		try {
			if (!empty($toEmailIds))
				Mail::to($toEmailIds)->queue($Qmessage);
		} catch (ConnectionException $e) {
			throw new RedisException;
		}

		$toMainDistributorEmailIds = [];
		$mainDistributorRecipients = [];
		if ($customerType == 'dealers' && $customer->id != null) {
			$query = User::where('distributor_id', '=', $customer->id);
			$query->whereHas('roles', function ($q) {
				$q->where('name', '=', 'distributorAdmin');
			});
			$users = $query->get();
			$distributor_user = $users->first();
			foreach ($users as $user) {
				if ($user->communication_email)
					$toMainDistributorEmailIds[] = $user->communication_email;
				$mainDistributorRecipients[] = $user;
			}

			if (!empty($distributor_user)) {
				$notifiable = (object)[
					"number" => $distributor_user->phone,
					"supplier" => $customer->name,
					"customer" => $customer->name
				];
				SMSNotification::send($notifiable, new PurchaseOrderReceivedNotification($company)); //#595 Notify Distributor for DUD Order
			}
		} //add Main Distributor Admin Email Ids

		CustomHelper::sendWhatsappNotification($distToSSBlanketOrder);
		foreach ($mainDistributorRecipients as $recipient) {
			$notification = new Notification;
			$notification->title = 'Created new Dealer Order';
			$notification->text = 'New Dealer Order ' . $distToSSBlanketOrder->order_number . ' has been created';
			$notification->sender_id = $sender->id;
			$notification->recipient_id = $recipient->id;
			$notification->author()->associate($sender);
			$notification->sender()->associate($sender);
			$notification->recipient()->associate($recipient);
			$notification->save();
			event(new Notifications($notification, $distToSSBlanketOrder));
		}
		$queuePrefix = config('brand.queue_prefix');
		$queue = $queuePrefix . '.emails';
		$Qmessage = (new dealerSalesOrderNew($distToSSBlanketOrder))->onQueue($queue);
		try {
			if (!empty($toMainDistributorEmailIds))
				Mail::to($toMainDistributorEmailIds)->queue($Qmessage);
		} catch (ConnectionException $e) {
			throw new RedisException;
		}
		return [$distToSSBlanketOrder, $allInvalidLines, $message];
	}

	public function createDistToSSOrderWhenCustomerIsDistributor(Company $company, $customer, array $params, User $user, $ss_lines, $dudBlanketOrder, $sSStockPointId)
	{
		$distributor = Distributor::find($customer->id);
		#Check for Super Stockist Id when distributor under company place order from super stockist stock point
		$superStockistID = (!empty($customer->super_stockist_id)) ? $customer->super_stockist_id : $sSStockPointId;
		$superStockist = SuperStockist::find($superStockistID);

		$message = null;
		$soAmount = 0;
		$ss_soAmount = 0;
		$recipients = [];
		$sender = $user;

		$validated = $this->validatePurchaseLineswithSSStock($company, $ss_lines->all(), $customer, $params['order_from'], $sSStockPointId);

		if (!count($validated['valid'])) {
			return [null, $validated['invalid'], $message];
		}
		$lines = $validated['valid'];
		$lineNo = 10000;
		$newLineNo = 10000;
		$cnt = 1;
		foreach ($lines as $line) {
			$validLine = $line;
			$validLine['line_no'] = $newLineNo;
			$batch_nos[$newLineNo] = $line['batch_no'];
			$cnt++;
			$newLineNo = $lineNo * $cnt;
			$unitPrice = (float)$line['unit_price'];

			$product = Product::find($line['product_id']);
			//$ss_discount = round($this->ssDiscountService->getSSDiscount('DISTRIBUTO', $distributor, $product, $superStockist->id), 2); 
			$customerType = 'DISTRIBUTO';
			if ($distributor->super_stockist_id > 0) {
				$customerType = 'SUPER_STOCKIST_DISTRIBUTOR';
			}
			if (($distributor->super_stockist_id != $sSStockPointId) && $sSStockPointId != '') {
				$customerType = 'DISTRIBUTO';
			}

			$ss_discount = round($this->discountService->getDiscount($customerType, $distributor, $product, $superStockist->id), 2);
			//Discount that is sent in params is for DUD to Distributor Order taken from Customer Discounts table, it needs to calculated for Dist to SS bbased on SS Discounts table

			$validLine['ss_discount'] = $ss_discount;
			$ss_discountedPrice = round($unitPrice - ($unitPrice * $ss_discount / 100), 2);
			$ss_lineAmount = $ss_discountedPrice * $line['quantity'];
			$validLine['ss_lineAmount'] = $ss_lineAmount;
			$ss_soAmount = $ss_soAmount + $ss_lineAmount;
			$discountedPrice = round($unitPrice - ($unitPrice * $line['discount'] / 100), 2); //$line['discount'] is the one provided in the cart item that is end customer discount, in this case DUD discount
			$lineAmount = $discountedPrice * $line['quantity'];
			$soAmount = $soAmount + $lineAmount;


			$taxRecord = $company->region->taxes()->where('category_code', '=', $product->category->code)->where('collection_code', '=', $product->collection->code)->first();
			if (!$taxRecord) {
				$taxRecord = $company->region->taxes()->where('category_code', '=', $product->category->code)->first();
			}
			if ($taxRecord) {
				$validLine['tax_type_code'] = $taxRecord->tax_type_code;
				$validLine['tax_percent'] = $taxRecord->tax_percent;
			} else {
				$validLine['tax_type_code'] = 0;
				$validLine['tax_percent'] = 0;
			}

			$validLines[] = $validLine;
		}
		//start of Dist to SS BO and PO
		$distPurchaseOrder = new PurchaseOrder;
		$distPurchaseOrder->shipping_method = $params['shipping_method'];
		$distPurchaseOrder->special_request = $params['special_request'];
		$UUId = Uuid::uuid4();
		$distPurchaseOrder->UUID = $UUId;
		$distPurchaseOrder->nav_key = $UUId;
		$distPurchaseOrder->status = 'Approved';
		$distPurchaseOrder->amount = $ss_soAmount;
		$distPurchaseOrder->company_id = $company->id;
		$distPurchaseOrder->super_stockist_id = $superStockist->id;
		$distPurchaseOrder->blanket_order_id = $dudBlanketOrder->id; //SS BO Id on Distributor to SS's PO
		$distPurchaseOrder->navision_order_number = '';
		if (array_has($params, 'end_customer_id')) {
			$distPurchaseOrder->end_customer_id = $params['end_customer_id'];
		}
		if (array_has($params, 'installer_id')) {
			$distPurchaseOrder->engineer_id = $params['installer_id'];
		}

		if ($sSStockPointId) {
			$distPurchaseOrder->ss_stock_point_id = $sSStockPointId;
		}
		$distributor->orders()->save($distPurchaseOrder);

		/*
		Update SS ID in Blanket Order
		$blanketOrder = BlanketOrder::where('id', $dudBlanketOrder->id)->first();
		$blanketOrder->super_stockist_id = $superStockist->id;
		$blanketOrder->save();
		*/

		/* Distribbutor to SS PO*/
		foreach ($validLines as $purchaseLine) {
			$distPurchaseOrderLine = new PurchaseLine;
			$distPurchaseOrderLine->product_id = $purchaseLine['product_id'];
			$distPurchaseOrderLine->nav_line_no = $purchaseLine['line_no'];
			$distPurchaseOrderLine->discount = $purchaseLine['ss_discount'];
			$distPurchaseOrderLine->unit_price = $purchaseLine['unit_price'];
			$distPurchaseOrderLine->quantity = $purchaseLine['quantity'];
			$distPurchaseOrderLine->rate_type = $purchaseLine['rate_type'];
			$distPurchaseOrderLine->amount = $purchaseLine['ss_lineAmount'];
			$distPurchaseOrderLine->batch_no = $purchaseLine['batch_no'];
			$distPurchaseOrderLine->tax_type_code = $purchaseLine['tax_type_code'];
			$distPurchaseOrderLine->tax_percent = $purchaseLine['tax_percent'];
			$distPurchaseOrderLine->tax_amount = $distPurchaseOrderLine->amount * $distPurchaseOrderLine->tax_percent / 100;
			$distPurchaseOrderLine->net_amount = $distPurchaseOrderLine->tax_amount + $distPurchaseOrderLine->amount;
			$distPurchaseOrder->purchase_lines()->save($distPurchaseOrderLine);

			if ($distPurchaseOrder->status == 'Approved') {
				$stockParams['category_code'] = $product->category->code;
				$stockParams['collection_code'] = $product->collection->code;
				$stockParams['colour_code'] = $product->colour->code;
				$stockParams['batch_no'] = $purchaseLine['batch_no'];
				$stockParams['stock'] = $purchaseLine['quantity'];
				// $this->distributorService->addStock($company->id, $distributor->id, $stockParams);
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
		$distPurchaseOrder->addresses()->save($address);

		if ($customer->distributor_id != '') {
			$distBlanketOrder = new BlanketOrder; //SS Blanket Order
			if (array_has($params, 'source')) //#767
				$distBlanketOrder->source = $params['source'];
			if (array_has($params, 'OnBehalf')) //#767
				$distBlanketOrder->on_behalf = $params['OnBehalf'];
			$distBlanketOrder->company_id = $company->id;
			if ($customer->super_stockist_id)
				$distBlanketOrder->super_stockist_id = $customer->super_stockist_id;
			if (array_has($params, 'end_customer_id'))
				$distBlanketOrder->end_customer_id = $params['end_customer_id'];
			if (array_has($params, 'installer_id'))
				$distBlanketOrder->engineer_id = $params['installer_id'];
			$distBlanketOrder->save();

			/*
			$distPurchaseOrder->blanket_order_id = $distBlanketOrder->id; //SS BO Id on Distributor to SS's PO
			$distPurchaseOrder->save();
			$distributor->blanketOrders()->save($distBlanketOrder);
			*/
			if ($distBlanketOrder) {
				$purchaseOrder = new PurchaseOrder;
				$purchaseOrder->shipping_method = $params['shipping_method'];
				$purchaseOrder->special_request = $params['special_request'];
				$UUId = Uuid::uuid4();
				$purchaseOrder->UUID = $UUId;
				$purchaseOrder->nav_key = $UUId;
				$purchaseOrder->status = 'Approved';
				$purchaseOrder->amount = $soAmount;
				$purchaseOrder->company_id = $company->id;
				$purchaseOrder->distributor_id = $customer->id;
				$purchaseOrder->navision_order_number = '';
				if (array_has($params, 'end_customer_id'))
					$purchaseOrder->end_customer_id = $params['end_customer_id'];
				if (array_has($params, 'installer_id'))
					$purchaseOrder->engineer_id = $params['installer_id'];
				$purchaseOrder->blanket_order_id = $dudBlanketOrder->id;

				if ($sSStockPointId) {
					$purchaseOrder->ss_stock_point_id = $sSStockPointId;
				}

				$customer->superStockist->orders()->save($purchaseOrder);
				foreach ($validLines as $purchaseLine) {
					$purchaseOrderLine = new PurchaseLine;
					$purchaseOrderLine->product_id = $purchaseLine['product_id'];
					$purchaseOrderLine->nav_line_no = $purchaseLine['line_no'];
					$purchaseOrderLine->discount = $purchaseLine['discount'];
					$purchaseOrderLine->unit_price = $purchaseLine['unit_price'];
					$purchaseOrderLine->quantity = $purchaseLine['quantity'];
					$purchaseOrderLine->rate_type = $purchaseLine['rate_type'];
					$purchaseOrderLine->amount = ($purchaseLine['unit_price'] - ($purchaseLine['unit_price'] * ($purchaseLine['discount'] / 100))) * round($purchaseLine['quantity'], 2);
					$purchaseOrderLine->tax_type_code = $purchaseLine['tax_type_code'];
					$purchaseOrderLine->tax_percent = $purchaseLine['tax_percent'];
					$purchaseOrderLine->tax_amount = $purchaseOrderLine->amount * $purchaseOrderLine->tax_percent / 100;
					$purchaseOrderLine->net_amount = $purchaseOrderLine->tax_amount + $purchaseOrderLine->amount;
					$purchaseOrderLine->batch_no = $purchaseLine['batch_no'];
					$purchaseOrder->purchase_lines()->save($purchaseOrderLine);
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
				$purchaseOrder->addresses()->save($address);

				$distBlanketOrder->related_po_id = $distPurchaseOrder->id; //DUD PO Id on Dist BO
				$distBlanketOrder->save();
				return [$purchaseOrder, $validated['invalid'], $message];
			}
		}

		if (($superStockist->id != $sSStockPointId) && $sSStockPointId != '') {
			$superStockist = SuperStockist::find($sSStockPointId);

			$distBlanketOrder = new BlanketOrder;
			//SS Blanket Order
			if (array_has($params, 'source')) //#767
				$distBlanketOrder->source = $params['source'];
			if (array_has($params, 'OnBehalf')) //#767
				$distBlanketOrder->on_behalf = $params['OnBehalf'];
			$distBlanketOrder->company_id = $company->id;
			if ($sSStockPointId)
				$distBlanketOrder->super_stockist_id = $sSStockPointId; //$customer->super_stockist_id;
			if (array_has($params, 'end_customer_id'))
				$distBlanketOrder->end_customer_id = $params['end_customer_id'];
			if (array_has($params, 'installer_id'))
				$distBlanketOrder->engineer_id = $params['installer_id'];
			$distBlanketOrder->save();

			if ($distBlanketOrder) {
				$purchaseOrder = new PurchaseOrder;
				$purchaseOrder->shipping_method = $params['shipping_method'];
				$purchaseOrder->special_request = $params['special_request'];
				$UUId = Uuid::uuid4();
				$purchaseOrder->UUID = $UUId;
				$purchaseOrder->nav_key = $UUId;
				$purchaseOrder->status = 'Approved';
				$purchaseOrder->amount = $soAmount;
				$purchaseOrder->company_id = $company->id;
				$purchaseOrder->super_stockist_id = $sSStockPointId;
				$purchaseOrder->navision_order_number = '';
				if (array_has($params, 'end_customer_id'))
					$purchaseOrder->end_customer_id = $params['end_customer_id'];
				if (array_has($params, 'installer_id'))
					$purchaseOrder->engineer_id = $params['installer_id'];
				$purchaseOrder->blanket_order_id = $distBlanketOrder->id;
				if ($sSStockPointId) {
					$purchaseOrder->ss_stock_point_id = $sSStockPointId;
				}
				$customer->superStockist->orders()->save($purchaseOrder);
				//$superStockist->orders()->save($purchaseOrder);
				foreach ($validLines as $purchaseLine) {
					$purchaseOrderLine = new PurchaseLine;
					$purchaseOrderLine->product_id = $purchaseLine['product_id'];
					$purchaseOrderLine->nav_line_no = $purchaseLine['line_no'];
					$purchaseOrderLine->discount = $purchaseLine['discount'];
					$purchaseOrderLine->unit_price = $purchaseLine['unit_price'];
					$purchaseOrderLine->quantity = $purchaseLine['quantity'];
					$purchaseOrderLine->rate_type = $purchaseLine['rate_type'];
					$purchaseOrderLine->amount = ($purchaseLine['unit_price'] - ($purchaseLine['unit_price'] * ($purchaseLine['discount'] / 100))) * round($purchaseLine['quantity'], 2);
					$purchaseOrderLine->tax_type_code = $purchaseLine['tax_type_code'];
					$purchaseOrderLine->tax_percent = $purchaseLine['tax_percent'];
					$purchaseOrderLine->tax_amount = $purchaseOrderLine->amount * $purchaseOrderLine->tax_percent / 100;
					$purchaseOrderLine->net_amount = $purchaseOrderLine->tax_amount + $purchaseOrderLine->amount;
					$purchaseOrderLine->batch_no = $purchaseLine['batch_no'];
					$purchaseOrder->purchase_lines()->save($purchaseOrderLine);
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
				$purchaseOrder->addresses()->save($address);

				//$purchase_lines = $validated['valid'];
				foreach ($lines as $line) {
					$currentStock = $superStockist->stocks->where('product_id', '=', $line['product_id'])
						->where('batch_no', '=', $line['batch_no'])->first();
					$newStock = $currentStock->quantity - $line['quantity'];
					$currentStock->quantity = $newStock;
					//$currentStock->save();
					//adjust SS Stock
				}
				$distBlanketOrder->related_po_id = $distPurchaseOrder->id; //DUD PO Id on Dist BO
				$distBlanketOrder->save();
				$customer->superStockist->blanketOrders()->save($distBlanketOrder);
				//return [$purchaseOrder, $validated['invalid'], $message];
			}
		}
		foreach ($lines as $line) {
			if ($params['order_from'] == "cart") {
				$item = CartItem::find($line['item_id']);
				$item->delete();
			} else if ($params['order_from'] == "blocked") {
				$item = BlockedItem::find($line['item_id']);
				$item->delete();
			}

			$superStockistID = (!empty($superStockist->id) && $superStockist->id > 0) ? $superStockist->id : $sSStockPointId;
			if ($superStockistID > 0) {
				$superStockistStockist = SuperStockist::find($superStockistID);
				$currentStock = $superStockistStockist->stocks->where('product_id', '=', $line['product_id'])
					->where('batch_no', '=', $line['batch_no'])->first();
				$newStock = $currentStock->quantity - $line['quantity'];
				$currentStock->quantity = $newStock;
				$currentStock->save();
				//adjust SS Stock
			}
		}
		return [$distPurchaseOrder, $validated['invalid'], $message];
	}

	public function createSSToSSOrderWhenCustomerIsSS(Company $company, $customer, array $params, User $user, $ss_lines, $dudBlanketOrder, $sSStockPointId)
	{
		$message = null;
		$soAmount = 0;
		$ss_soAmount = 0;
		$recipients = [];
		$sender = $user;

		$validated = $this->validatePurchaseLineswithSSStock($company, $ss_lines->all(), $customer, $params['order_from'], $sSStockPointId);

		if (!count($validated['valid'])) {
			return [null, $validated['invalid'], $message];
		}
		$lines = $validated['valid'];
		$lineNo = 10000;
		$newLineNo = 10000;
		$cnt = 1;
		foreach ($lines as $line) {
			$validLine = $line;
			$validLine['line_no'] = $newLineNo;
			$batch_nos[$newLineNo] = $line['batch_no'];
			$cnt++;
			$newLineNo = $lineNo * $cnt;
			$unitPrice = (float)$line['unit_price'];

			$product = Product::find($line['product_id']);
			//$ss_discount = round($this->ssDiscountService->getSSDiscount('SUPER_STOCKIST', $customer, $product, $sSStockPointId), 2);
			$ss_discount = round($this->discountService->getDiscount('SUPER_STOCKIST', $customer, $product, $sSStockPointId), 2);
			//Discount that is sent in params, it needs to calculated for SS to SS bbased on SS Discounts table
			$validLine['ss_discount'] = $ss_discount;
			$ss_discountedPrice = round($unitPrice - ($unitPrice * $ss_discount / 100), 2);
			$ss_lineAmount = $ss_discountedPrice * $line['quantity'];
			$validLine['ss_lineAmount'] = $ss_lineAmount;
			$ss_soAmount = $ss_soAmount + $ss_lineAmount;
			$discountedPrice = round($unitPrice - ($unitPrice * $line['discount'] / 100), 2); //$line['discount'] is the one provided in the cart item that is end customer discount, in this case DUD discount
			$lineAmount = $discountedPrice * $line['quantity'];
			$soAmount = $soAmount + $lineAmount;


			$taxRecord = $company->region->taxes()->where('category_code', '=', $product->category->code)->where('collection_code', '=', $product->collection->code)->first();
			if (!$taxRecord) {
				$taxRecord = $company->region->taxes()->where('category_code', '=', $product->category->code)->first();
			}
			if ($taxRecord) {
				$validLine['tax_type_code'] = $taxRecord->tax_type_code;
				$validLine['tax_percent'] = $taxRecord->tax_percent;
			} else {
				$validLine['tax_type_code'] = 0;
				$validLine['tax_percent'] = 0;
			}

			$validLines[] = $validLine;
		}
		//start of Dist to SS BO and PO
		$distPurchaseOrder = new PurchaseOrder;
		$distPurchaseOrder->shipping_method = $params['shipping_method'];
		$distPurchaseOrder->special_request = $params['special_request'];
		$UUId = Uuid::uuid4();
		$distPurchaseOrder->UUID = $UUId;
		$distPurchaseOrder->nav_key = $UUId;
		$distPurchaseOrder->status = 'Approved';
		$distPurchaseOrder->amount = $ss_soAmount;
		$distPurchaseOrder->company_id = $company->id;
		$distPurchaseOrder->super_stockist_id = $sSStockPointId;
		$distPurchaseOrder->blanket_order_id = $dudBlanketOrder->id; //SS BO Id on SS's PO
		$distPurchaseOrder->navision_order_number = '';
		if (array_has($params, 'end_customer_id')) {
			$distPurchaseOrder->end_customer_id = $params['end_customer_id'];
		}
		if (array_has($params, 'installer_id')) {
			$distPurchaseOrder->engineer_id = $params['installer_id'];
		}

		if ($sSStockPointId) {
			$distPurchaseOrder->ss_stock_point_id = $sSStockPointId;
		}
		$customer->orders()->save($distPurchaseOrder);


		/* Distribbutor to SS PO*/
		foreach ($validLines as $purchaseLine) {
			$distPurchaseOrderLine = new PurchaseLine;
			$distPurchaseOrderLine->product_id = $purchaseLine['product_id'];
			$distPurchaseOrderLine->nav_line_no = $purchaseLine['line_no'];
			$distPurchaseOrderLine->discount = $purchaseLine['ss_discount'];
			$distPurchaseOrderLine->unit_price = $purchaseLine['unit_price'];
			$distPurchaseOrderLine->quantity = $purchaseLine['quantity'];
			$distPurchaseOrderLine->rate_type = $purchaseLine['rate_type'];
			$distPurchaseOrderLine->amount = $purchaseLine['ss_lineAmount'];
			$distPurchaseOrderLine->batch_no = $purchaseLine['batch_no'];
			$distPurchaseOrderLine->tax_type_code = $purchaseLine['tax_type_code'];
			$distPurchaseOrderLine->tax_percent = $purchaseLine['tax_percent'];
			$distPurchaseOrderLine->tax_amount = $distPurchaseOrderLine->amount * $distPurchaseOrderLine->tax_percent / 100;
			$distPurchaseOrderLine->net_amount = $distPurchaseOrderLine->tax_amount + $distPurchaseOrderLine->amount;
			$distPurchaseOrder->purchase_lines()->save($distPurchaseOrderLine);

			if ($distPurchaseOrder->status == 'Approved') {
				$stockParams['category_code'] = $product->category->code;
				$stockParams['collection_code'] = $product->collection->code;
				$stockParams['colour_code'] = $product->colour->code;
				$stockParams['batch_no'] = $purchaseLine['batch_no'];
				$stockParams['stock'] = $purchaseLine['quantity'];
				// $this->superStockistService->addStock($company->id, $customer->id, $stockParams);
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
		$distPurchaseOrder->addresses()->save($address);

		foreach ($lines as $line) {
			if ($params['order_from'] == "cart") {
				$item = CartItem::find($line['item_id']);
				$item->delete();
			} else if ($params['order_from'] == "blocked") {
				$item = BlockedItem::find($line['item_id']);
				$item->delete();
			}
			if($sSStockPointId>0){
				$superStockistStockpoint = SuperStockist::find($sSStockPointId);
				$currentStock = $superStockistStockpoint->stocks->where('product_id', '=', $line['product_id'])
				->where('batch_no', '=', $line['batch_no'])->first();
			}else{
				$currentStock = $customer->stocks->where('product_id', '=', $line['product_id'])
				->where('batch_no', '=', $line['batch_no'])->first();
			}
			$newStock = $currentStock->quantity - $line['quantity'];
			$currentStock->quantity = $newStock;
			$currentStock->save();
			//adjust SS Stock
		}
		return [$distPurchaseOrder, $validated['invalid'], $message];
	}

	public function createBlanketOrderForSsByDealerUnderCompany(Company $company, $customer, array $params, User $user, $ss_lines, $blanketOrder, $sSStockPointId)
	{
		$dealer = Dealer::find($customer->id);
		#Check for Super Stockist Id when dealer under company place order from super stockist stock point
		$superStockistID = (!empty($customer->super_stockist_id)) ? $customer->super_stockist_id : $sSStockPointId;
		$superStockist = SuperStockist::find($superStockistID);

		$message = null;
		$soAmount = 0;
		$ss_soAmount = 0;
		$recipients = [];
		$sender = $user;

		$validated = $this->validatePurchaseLineswithSSStock($company, $ss_lines->all(), $customer, $params['order_from'], $sSStockPointId);

		if (!count($validated['valid'])) {
			return [null, $validated['invalid'], $message];
		}
		$lines = $validated['valid'];
		$lineNo = 10000;
		$newLineNo = 10000;
		$cnt = 1;
		foreach ($lines as $line) {
			$validLine = $line;
			$validLine['line_no'] = $newLineNo;
			$batch_nos[$newLineNo] = $line['batch_no'];
			$cnt++;
			$newLineNo = $lineNo * $cnt;
			$unitPrice = (float)$line['unit_price'];

			$product = Product::find($line['product_id']);
			//$ss_discount = round($this->ssDiscountService->getSSDiscount('DEALER', $dealer, $product, $superStockist->id), 2); 
			$customerType = "DEALER";
			if ($dealer->distributor_id > 0) {
				$customerType = 'SUPER_STOCKIST_DEALER';
			}
			$ss_discount = round($this->discountService->getDiscount($customerType, $dealer, $product, $superStockist->id), 2);

			//Discount that is sent in params is for DUD to dealer Order taken from Customer Discounts table, it needs to calculated for Dist to SS bbased on SS Discounts table
			$validLine['ss_discount'] = $ss_discount;
			$ss_discountedPrice = round($unitPrice - ($unitPrice * $ss_discount / 100), 2);
			$ss_lineAmount = $ss_discountedPrice * $line['quantity'];
			$validLine['ss_lineAmount'] = $ss_lineAmount;
			$ss_soAmount = $ss_soAmount + $ss_lineAmount;
			$discountedPrice = round($unitPrice - ($unitPrice * $line['discount'] / 100), 2); //$line['discount'] is the one provided in the cart item that is end customer discount, in this case DUD discount
			$lineAmount = $discountedPrice * $line['quantity'];
			$soAmount = $soAmount + $lineAmount;

			$taxRecord = $company->region->taxes()->where('category_code', '=', $product->category->code)->where('collection_code', '=', $product->collection->code)->first();
			if (!$taxRecord) {
				$taxRecord = $company->region->taxes()->where('category_code', '=', $product->category->code)->first();
			}
			if ($taxRecord) {
				$validLine['tax_type_code'] = $taxRecord->tax_type_code;
				$validLine['tax_percent'] = $taxRecord->tax_percent;
			} else {
				$validLine['tax_type_code'] = 0;
				$validLine['tax_percent'] = 0;
			}

			$validLines[] = $validLine;
		}
		//start of Dist to SS BO and PO
		$dealerPurchaseOrder = new PurchaseOrder;
		$dealerPurchaseOrder->shipping_method = $params['shipping_method'];
		$dealerPurchaseOrder->special_request = $params['special_request'];
		$UUId = Uuid::uuid4();
		$dealerPurchaseOrder->UUID = $UUId;
		$dealerPurchaseOrder->nav_key = $UUId;
		$dealerPurchaseOrder->status = 'Approved';
		$dealerPurchaseOrder->amount = $ss_soAmount;
		$dealerPurchaseOrder->company_id = $company->id;
		$dealerPurchaseOrder->super_stockist_id = $superStockist->id;
		$dealerPurchaseOrder->blanket_order_id = $blanketOrder->id; //SS BO Id on dealer to SS's PO
		$dealerPurchaseOrder->navision_order_number = '';
		if (array_has($params, 'end_customer_id')) {
			$dealerPurchaseOrder->end_customer_id = $params['end_customer_id'];
		}
		if (array_has($params, 'installer_id')) {
			$dealerPurchaseOrder->engineer_id = $params['installer_id'];
		}

		if ($sSStockPointId) {
			$dealerPurchaseOrder->ss_stock_point_id = $sSStockPointId;
		}
		$dealer->orders()->save($dealerPurchaseOrder);

		/* Distribbutor to SS PO*/
		foreach ($validLines as $purchaseLine) {
			$dealerPurchaseOrderLine = new PurchaseLine;
			$dealerPurchaseOrderLine->product_id = $purchaseLine['product_id'];
			$dealerPurchaseOrderLine->nav_line_no = $purchaseLine['line_no'];
			$dealerPurchaseOrderLine->discount = $purchaseLine['ss_discount'];
			$dealerPurchaseOrderLine->unit_price = $purchaseLine['unit_price'];
			$dealerPurchaseOrderLine->quantity = $purchaseLine['quantity'];
			$dealerPurchaseOrderLine->rate_type = $purchaseLine['rate_type'];
			$dealerPurchaseOrderLine->amount = $purchaseLine['ss_lineAmount'];
			$dealerPurchaseOrderLine->batch_no = $purchaseLine['batch_no'];
			$dealerPurchaseOrderLine->tax_type_code = $purchaseLine['tax_type_code'];
			$dealerPurchaseOrderLine->tax_percent = $purchaseLine['tax_percent'];
			$dealerPurchaseOrderLine->tax_amount = $dealerPurchaseOrderLine->amount * $dealerPurchaseOrderLine->tax_percent / 100;
			$dealerPurchaseOrderLine->net_amount = $dealerPurchaseOrderLine->tax_amount + $dealerPurchaseOrderLine->amount;
			$dealerPurchaseOrder->purchase_lines()->save($dealerPurchaseOrderLine);
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
		$dealerPurchaseOrder->addresses()->save($address);

		foreach ($lines as $line) {
			if ($params['order_from'] == "cart") {
				$item = CartItem::find($line['item_id']);
				$item->delete();
			} else if ($params['order_from'] == "blocked") {
				$item = BlockedItem::find($line['item_id']);
				$item->delete();
			}
			$currentStock = $superStockist->stocks->where('product_id', '=', $line['product_id'])
				->where('batch_no', '=', $line['batch_no'])->first();
			$newStock = $currentStock->quantity - $line['quantity'];
			$currentStock->quantity = $newStock;
			$currentStock->save();
			//adjust SS Stock
		}
		return [$dealerPurchaseOrder, $validated['invalid'], $message];
	}

	public function createDistToSSStockWhenCustomerIsDistributor(Company $company, $customer, array $params, User $user, $ss_lines, $dudBlanketOrder, $sSStockPointId)
	{
		$distributor = Distributor::find($customer->id);
		$superStockist = SuperStockist::find($customer->super_stockist_id);

		$message = null;
		$soAmount = 0;
		$ss_soAmount = 0;
		$recipients = [];
		$sender = $user;

		$validated = $this->validatePurchaseLineswithSSStock($company, $ss_lines->all(), $customer, $params['order_from']);
		if (!count($validated['valid'])) {
			return [null, $validated['invalid'], $message];
		}
		$lines = $validated['valid'];
		$lineNo = 10000;
		$newLineNo = 10000;
		$cnt = 1;
		foreach ($lines as $line) {
			$validLine = $line;
			$validLine['line_no'] = $newLineNo;
			$batch_nos[$newLineNo] = $line['batch_no'];
			$cnt++;
			$newLineNo = $lineNo * $cnt;
			$unitPrice = (float)$line['unit_price'];

			$product = Product::find($line['product_id']);
			//$ss_discount = round($this->ssDiscountService->getSSDiscount('DISTRIBUTO', $distributor, $product, $superStockist->id), 2);
			$customerType = 'DISTRIBUTO';
			if ($distributor->super_stockist_id > 0) {
				$customerType = 'SUPER_STOCKIST_DISTRIBUTOR';
			}
			if (($distributor->super_stockist_id != $sSStockPointId) && $sSStockPointId != '') {
				$customerType = 'DISTRIBUTO';
			}
			$ss_discount = round($this->discountService->getDiscount($customerType, $distributor, $product, $superStockist->id), 2);
			//Discount that is sent in params is for DUD to Distributor Order taken from Customer Discounts table, it needs to calculated for Dist to SS bbased on SS Discounts table
			$validLine['ss_discount'] = $ss_discount;
			$ss_discountedPrice = round($unitPrice - ($unitPrice * $ss_discount / 100), 2);
			$ss_lineAmount = $ss_discountedPrice * $line['quantity'];
			$validLine['ss_lineAmount'] = $ss_lineAmount;
			$ss_soAmount = $ss_soAmount + $ss_lineAmount;
			$discountedPrice = round($unitPrice - ($unitPrice * $line['discount'] / 100), 2); //$line['discount'] is the one provided in the cart item that is end customer discount, in this case DUD discount
			$lineAmount = $discountedPrice * $line['quantity'];
			$soAmount = $soAmount + $lineAmount;


			$taxRecord = $company->region->taxes()->where('category_code', '=', $product->category->code)->where('collection_code', '=', $product->collection->code)->first();
			if (!$taxRecord) {
				$taxRecord = $company->region->taxes()->where('category_code', '=', $product->category->code)->first();
			}
			if ($taxRecord) {
				$validLine['tax_type_code'] = $taxRecord->tax_type_code;
				$validLine['tax_percent'] = $taxRecord->tax_percent;
			} else {
				$validLine['tax_type_code'] = 0;
				$validLine['tax_percent'] = 0;
			}

			$validLines[] = $validLine;
		}
		//start of Dist to SS BO and PO
		$distPurchaseOrder = new PurchaseOrder;
		$distPurchaseOrder->shipping_method = $params['shipping_method'];
		$distPurchaseOrder->special_request = $params['special_request'];
		$UUId = Uuid::uuid4();
		$distPurchaseOrder->UUID = $UUId;
		$distPurchaseOrder->nav_key = $UUId;
		$distPurchaseOrder->status = 'Approved';
		$distPurchaseOrder->amount = $ss_soAmount;
		$distPurchaseOrder->company_id = $company->id;
		$distPurchaseOrder->super_stockist_id = $superStockist->id;
		$distPurchaseOrder->blanket_order_id = $dudBlanketOrder->id; //SS BO Id on Distributor to SS's PO
		$distPurchaseOrder->navision_order_number = '';
		if (array_has($params, 'end_customer_id'))
			$distPurchaseOrder->end_customer_id = $params['end_customer_id'];
		if (array_has($params, 'installer_id'))
			$distPurchaseOrder->engineer_id = $params['installer_id'];
		if ($sSStockPointId) {
			$distPurchaseOrder->ss_stock_point_id = $sSStockPointId;
		}
		$distributor->orders()->save($distPurchaseOrder);

		/*
		Update SS ID in Blanket Order
		$blanketOrder = BlanketOrder::where('id', $dudBlanketOrder->id)->first();
		$blanketOrder->super_stockist_id = $superStockist->id;
		$blanketOrder->save();
		*/

		/* Distribbutor to SS PO*/
		foreach ($validLines as $purchaseLine) {
			$distPurchaseOrderLine = new PurchaseLine;
			$distPurchaseOrderLine->product_id = $purchaseLine['product_id'];
			$distPurchaseOrderLine->nav_line_no = $purchaseLine['line_no'];
			$distPurchaseOrderLine->discount = $purchaseLine['ss_discount'];
			$distPurchaseOrderLine->unit_price = $purchaseLine['unit_price'];
			$distPurchaseOrderLine->quantity = $purchaseLine['quantity'];
			$distPurchaseOrderLine->rate_type = $purchaseLine['rate_type'];
			$distPurchaseOrderLine->amount = $purchaseLine['ss_lineAmount'];
			$distPurchaseOrderLine->batch_no = $purchaseLine['batch_no'];
			$distPurchaseOrderLine->tax_type_code = $purchaseLine['tax_type_code'];
			$distPurchaseOrderLine->tax_percent = $purchaseLine['tax_percent'];
			$distPurchaseOrderLine->tax_amount = $distPurchaseOrderLine->amount * $distPurchaseOrderLine->tax_percent / 100;
			$distPurchaseOrderLine->net_amount = $distPurchaseOrderLine->tax_amount + $distPurchaseOrderLine->amount;
			$distPurchaseOrder->purchase_lines()->save($distPurchaseOrderLine);

			if ($distPurchaseOrder->status == 'Approved') {
				$stockParams['category_code'] = $product->category->code;
				$stockParams['collection_code'] = $product->collection->code;
				$stockParams['colour_code'] = $product->colour->code;
				$stockParams['batch_no'] = $purchaseLine['batch_no'];
				$stockParams['stock'] = $purchaseLine['quantity'];
				// $this->distributorService->addStock($company->id, $distributor->id, $stockParams);
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
		$distPurchaseOrder->addresses()->save($address);

		if ($customer->distributor_id != '') {
			$distBlanketOrder = new BlanketOrder; //SS Blanket Order
			if (array_has($params, 'source')) //#767
				$distBlanketOrder->source = $params['source'];
			if (array_has($params, 'OnBehalf')) //#767
				$distBlanketOrder->on_behalf = $params['OnBehalf'];
			$distBlanketOrder->company_id = $company->id;
			if ($customer->super_stockist_id)
				$distBlanketOrder->super_stockist_id = $customer->super_stockist_id;
			if (array_has($params, 'end_customer_id'))
				$distBlanketOrder->end_customer_id = $params['end_customer_id'];
			if (array_has($params, 'installer_id'))
				$distBlanketOrder->engineer_id = $params['installer_id'];
			$distBlanketOrder->save();

			/*
			$distPurchaseOrder->blanket_order_id = $distBlanketOrder->id; //SS BO Id on Distributor to SS's PO
			$distPurchaseOrder->save();
			$distributor->blanketOrders()->save($distBlanketOrder);
			*/
			if ($distBlanketOrder) {
				$purchaseOrder = new PurchaseOrder;
				$purchaseOrder->shipping_method = $params['shipping_method'];
				$purchaseOrder->special_request = $params['special_request'];
				$UUId = Uuid::uuid4();
				$purchaseOrder->UUID = $UUId;
				$purchaseOrder->nav_key = $UUId;
				$purchaseOrder->status = 'Approved';
				$purchaseOrder->amount = $soAmount;
				$purchaseOrder->company_id = $company->id;
				$purchaseOrder->distributor_id = $customer->id;
				$purchaseOrder->navision_order_number = '';
				if (array_has($params, 'end_customer_id'))
					$purchaseOrder->end_customer_id = $params['end_customer_id'];
				if (array_has($params, 'installer_id'))
					$purchaseOrder->engineer_id = $params['installer_id'];
				$purchaseOrder->blanket_order_id = $dudBlanketOrder->id;
				if ($sSStockPointId) {
					$purchaseOrder->ss_stock_point_id = $sSStockPointId;
				}
				$customer->superStockist->orders()->save($purchaseOrder);
				foreach ($validLines as $purchaseLine) {
					$purchaseOrderLine = new PurchaseLine;
					$purchaseOrderLine->product_id = $purchaseLine['product_id'];
					$purchaseOrderLine->nav_line_no = $purchaseLine['line_no'];
					$purchaseOrderLine->discount = $purchaseLine['discount'];
					$purchaseOrderLine->unit_price = $purchaseLine['unit_price'];
					$purchaseOrderLine->quantity = $purchaseLine['quantity'];
					$purchaseOrderLine->rate_type = $purchaseLine['rate_type'];
					$purchaseOrderLine->amount = ($purchaseLine['unit_price'] - ($purchaseLine['unit_price'] * ($purchaseLine['discount'] / 100))) * round($purchaseLine['quantity'], 2);
					$purchaseOrderLine->tax_type_code = $purchaseLine['tax_type_code'];
					$purchaseOrderLine->tax_percent = $purchaseLine['tax_percent'];
					$purchaseOrderLine->tax_amount = $purchaseOrderLine->amount * $purchaseOrderLine->tax_percent / 100;
					$purchaseOrderLine->net_amount = $purchaseOrderLine->tax_amount + $purchaseOrderLine->amount;
					$purchaseOrderLine->batch_no = $purchaseLine['batch_no'];
					$purchaseOrder->purchase_lines()->save($purchaseOrderLine);
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
				$purchaseOrder->addresses()->save($address);

				//$purchase_lines = $validated['valid'];
				foreach ($lines as $line) {
					if ($params['order_from'] == "cart") {
						$item = CartItem::find($line['item_id']);
						//$item->delete();
					} else if ($params['order_from'] == "blocked") {
						$item = BlockedItem::find($line['item_id']);
						//$item->delete();
					}
					$currentStock = $superStockist->stocks->where('product_id', '=', $line['product_id'])
						->where('batch_no', '=', $line['batch_no'])->first();
					$newStock = $currentStock->quantity - $line['quantity'];
					$currentStock->quantity = $newStock;
					$currentStock->save();
					//adjust SS Stock
				}
				$distBlanketOrder->related_po_id = $distPurchaseOrder->id; //DUD PO Id on Dist BO
				$distBlanketOrder->save();
				//return [$purchaseOrder, $validated['invalid'], $message];
			}
		}
		//dd('==---999000000000-----------', $distPurchaseOrder);
		foreach ($lines as $line) {
			if ($params['order_from'] == "cart") {
				$item = CartItem::find($line['item_id']);
				$item->delete();
			} else if ($params['order_from'] == "blocked") {
				$item = BlockedItem::find($line['item_id']);
				$item->delete();
			}
			$currentStock = $superStockist->stocks->where('product_id', '=', $line['product_id'])
				->where('batch_no', '=', $line['batch_no'])->first();
			$newStock = $currentStock->quantity - $line['quantity'];
			$currentStock->quantity = $newStock;
			$currentStock->save();
			//adjust SS Stock
		}
		return [$distPurchaseOrder, $validated['invalid'], $message];
	}


	public function createDistToDistOrderWhenCustomerIsDistributor(Company $company, $customer, array $params, User $user, $dist_lines, $dudBlanketOrder, $distStockPointId)
	{
		$message = null;
		$soAmount = 0;
		$ss_soAmount = 0;
		$recipients = [];
		$sender = $user;

		$validated = $this->validatePurchaseLineswithDistributorsStock($company, $dist_lines->all(), $customer, $params['order_from'], $distStockPointId);

		if (!count($validated['valid'])) {
			return [null, $validated['invalid'], $message];
		}
		$lines = $validated['valid'];
		$lineNo = 10000;
		$newLineNo = 10000;
		$cnt = 1;
		foreach ($lines as $line) {
			$validLine = $line;
			$validLine['line_no'] = $newLineNo;
			$batch_nos[$newLineNo] = $line['batch_no'];
			$cnt++;
			$newLineNo = $lineNo * $cnt;
			$unitPrice = (float)$line['unit_price'];

			$product = Product::find($line['product_id']);
			$discount = round($this->discountService->getDiscount('DIST_TO_DIST', $customer, $product, $distStockPointId), 2); //Discount that is sent in params, it needs to calculated for Dist to Dist based on Distributor Discounts table
			$validLine['discount'] = $discount;
			$dist_discountedPrice = round($unitPrice - ($unitPrice * $discount / 100), 2);
			$dist_lineAmount = $dist_discountedPrice * $line['quantity'];
			$validLine['dist_lineAmount'] = $dist_lineAmount;
			$ss_soAmount = $ss_soAmount + $dist_lineAmount;
			$discountedPrice = round($unitPrice - ($unitPrice * $line['discount'] / 100), 2); //$line['discount'] is the one provided in the cart item that is end customer discount, in this case DUD discount
			$lineAmount = $discountedPrice * $line['quantity'];
			$soAmount = $soAmount + $lineAmount;


			$taxRecord = $company->region->taxes()->where('category_code', '=', $product->category->code)->where('collection_code', '=', $product->collection->code)->first();
			if (!$taxRecord) {
				$taxRecord = $company->region->taxes()->where('category_code', '=', $product->category->code)->first();
			}
			if ($taxRecord) {
				$validLine['tax_type_code'] = $taxRecord->tax_type_code;
				$validLine['tax_percent'] = $taxRecord->tax_percent;
			} else {
				$validLine['tax_type_code'] = 0;
				$validLine['tax_percent'] = 0;
			}

			$validLines[] = $validLine;
		}
		//start of Dist to SS BO and PO
		$distPurchaseOrder = new PurchaseOrder;
		$distPurchaseOrder->shipping_method = $params['shipping_method'];
		$distPurchaseOrder->special_request = $params['special_request'];
		$UUId = Uuid::uuid4();
		$distPurchaseOrder->UUID = $UUId;
		$distPurchaseOrder->nav_key = $UUId;
		$distPurchaseOrder->status = 'Approved';
		$distPurchaseOrder->amount = $ss_soAmount;
		$distPurchaseOrder->company_id = $company->id;
		$distPurchaseOrder->distributor_id = $distStockPointId;
		$distPurchaseOrder->blanket_order_id = $dudBlanketOrder->id; //SS BO Id on SS's PO
		$distPurchaseOrder->navision_order_number = '';
		if (array_has($params, 'end_customer_id')) {
			$distPurchaseOrder->end_customer_id = $params['end_customer_id'];
		}
		if (array_has($params, 'installer_id')) {
			$distPurchaseOrder->engineer_id = $params['installer_id'];
		}

		if ($distStockPointId) {
			$distPurchaseOrder->dist_stock_point_id = $distStockPointId;
			$dudBlanketOrder->distributor_id = $distStockPointId;
			$dudBlanketOrder->save();
		}
		$customer->orders()->save($distPurchaseOrder);


		/* Distribbutor to SS PO*/
		foreach ($validLines as $purchaseLine) {
			$distPurchaseOrderLine = new PurchaseLine;
			$distPurchaseOrderLine->product_id = $purchaseLine['product_id'];
			$distPurchaseOrderLine->nav_line_no = $purchaseLine['line_no'];
			$distPurchaseOrderLine->discount = $purchaseLine['discount'];
			$distPurchaseOrderLine->unit_price = $purchaseLine['unit_price'];
			$distPurchaseOrderLine->quantity = $purchaseLine['quantity'];
			$distPurchaseOrderLine->rate_type = $purchaseLine['rate_type'];
			$distPurchaseOrderLine->amount = $purchaseLine['dist_lineAmount'];
			$distPurchaseOrderLine->batch_no = $purchaseLine['batch_no'];
			$distPurchaseOrderLine->tax_type_code = $purchaseLine['tax_type_code'];
			$distPurchaseOrderLine->tax_percent = $purchaseLine['tax_percent'];
			$distPurchaseOrderLine->tax_amount = $distPurchaseOrderLine->amount * $distPurchaseOrderLine->tax_percent / 100;
			$distPurchaseOrderLine->net_amount = $distPurchaseOrderLine->tax_amount + $distPurchaseOrderLine->amount;
			$distPurchaseOrder->purchase_lines()->save($distPurchaseOrderLine);

			if ($distPurchaseOrder->status == 'Approved') {
				$stockParams['category_code'] = $product->category->code;
				$stockParams['collection_code'] = $product->collection->code;
				$stockParams['colour_code'] = $product->colour->code;
				$stockParams['batch_no'] = $purchaseLine['batch_no'];
				$stockParams['stock'] = $purchaseLine['quantity'];
				// $this->distributorService->addStock($company->id, $customer->id, $stockParams);
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
		$distPurchaseOrder->addresses()->save($address);

		foreach ($lines as $line) {
			if ($params['order_from'] == "cart") {
				$item = CartItem::find($line['item_id']);
				$item->delete();
			} else if ($params['order_from'] == "blocked") {
				$item = BlockedItem::find($line['item_id']);
				$item->delete();
			}

			if ($customer->id != $distStockPointId) {
				$stockistDistributor = Distributor::find($distStockPointId);
			} else {
				$stockistDistributor = $customer;
			}

			$currentStock = $stockistDistributor->stocks->where('product_id', '=', $line['product_id'])
				->where('batch_no', '=', $line['batch_no'])->first();
			$newStock = $currentStock->quantity - $line['quantity'];
			$currentStock->quantity = $newStock;
			$currentStock->save();
			//adjust Dist Stock
		}
		return [$distPurchaseOrder, $validated['invalid'], $message];
	}
}
