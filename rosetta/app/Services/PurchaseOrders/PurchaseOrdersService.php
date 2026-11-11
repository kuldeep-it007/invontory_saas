<?php

namespace Rosetta\Services\PurchaseOrders;

use Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as SMSNotification;
use Predis\Connection\ConnectionException;
use Ramsey\Uuid\Uuid;
use Rosetta\Events\Notifications;
use Rosetta\Exceptions\Order\DeleteLineException;
use Rosetta\Exceptions\Order\InvalidLinesPresentException;
use Rosetta\Exceptions\Order\UpdateLineException;
use Rosetta\Exceptions\Order\ViewOrderPermissionDeniedException;
use Rosetta\Exceptions\RedisException;
use Rosetta\Mail\CoDistributorSalesOrderNew;
use Rosetta\Mail\PurchaseLineDelete;
use Rosetta\Mail\PurchaseLineUpdate;
use Rosetta\Mail\PurchaseLineUpdateDud;
use Rosetta\Mail\PurchaseOrderDelete;
use Rosetta\Mail\PurchaseOrderDeleteCoDistributor;
use Rosetta\Mail\PurchaseOrderDeleteDistributor;
use Rosetta\Mail\PurchaseOrderNew;
use Rosetta\Mail\PurchaseOrderNewDistributor;
use Rosetta\Mail\PurchaseOrderNewDud;
use Rosetta\Mail\PurchaseOrderNewDudOnline;
use Rosetta\Mail\PurchaseOrderNewOnline;
use Rosetta\Mail\SalesOrderNew;
use Rosetta\Mail\dealerSalesOrderNew;
use Rosetta\Models\Address;
use Rosetta\Models\BlanketOrder;
use Rosetta\Models\Cart\BlockedItem;
use Rosetta\Models\Cart\CartItem;
use Rosetta\Models\Company;
use Rosetta\Models\Company\StockPoint;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\Log;
use Rosetta\Models\Notification;
use Rosetta\Models\Product\Product;
use Rosetta\Models\Product\Stock;
use Rosetta\Models\PurchaseOrder\PurchaseLine;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
use Rosetta\Models\PurchaseOrder\addresses;
use Rosetta\Models\Setting;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Models\Transaction;
use Rosetta\Models\User;
use Rosetta\NAV\WS\Customer\Blocked;
use Rosetta\NAV\WS\SalesOrder\getNo;
use Rosetta\NAV\WS\SalesOrder\getSalesLines;
use Rosetta\NAV\WS\SalesOrder\getSales_Order_Line;
use Rosetta\Notifications\SMS\PurchaseOrderCancelledNotification;
use Rosetta\Notifications\SMS\PurchaseOrderReceivedNotification;
use Rosetta\Notifications\SMS\PurchaseOrderUpdatedNotification;
use Rosetta\Pdf\DeliveryChallan;
use Rosetta\Services\CLRate\CLRateServiceInterface;
use Rosetta\Services\CoDistributor\CoDistributorDiscountServiceInterface;
use Rosetta\Services\Depot\DepotItemsServiceInterface;
use Rosetta\Services\Discount\DiscountsServiceInterface;
use Rosetta\Services\NAV\AvailableCreditServiceInterface;
use Rosetta\Services\NAV\CustomerServiceInterface;
use Rosetta\Services\NAV\PSIServiceInterface;
use Rosetta\Services\NAV\SalesOrderServiceInterface;
use Rosetta\Services\PaymentGateway\PaymentGatewayServiceInterface;
use Rosetta\Services\PurchaseOrders\PurchaseOrdersServiceInterface;


class PurchaseOrdersService implements PurchaseOrdersServiceInterface
{
	protected $salesOrderService, $psiService, $availableCreditService, $discountService, $coDistributorDiscountService, $clRateService, $paymentGatewayService, $customerService, $depotService;
	public function __construct(
		SalesOrderServiceInterface $salesOrderService,
		PSIServiceInterface $psiService,
		AvailableCreditServiceInterface $availableCreditService,
		DiscountsServiceInterface $discountService,
		CoDistributorDiscountServiceInterface $coDistributorDiscountService,
		CLRateServiceInterface $clRateService,
		PaymentGatewayServiceInterface $paymentGatewayService,
		CustomerServiceInterface $customerService,
		DepotItemsServiceInterface $depotService
	) {
		$this->salesOrderService            = $salesOrderService;
		$this->psiService                   = $psiService;
		$this->availableCreditService       = $availableCreditService;
		$this->discountService              = $discountService;
		$this->coDistributorDiscountService = $coDistributorDiscountService;
		$this->clRateService                = $clRateService;
		$this->paymentGatewayService        = $paymentGatewayService;
		$this->customerService              = $customerService;
		$this->depotService 				= resolve('Rosetta\Services\Depot\DepotItemsServiceInterface'); //$depotService;
	}
	public function create(Company $company, $customer, $customerType, array $params, User $user, $company_lines, $blanketOrder, $stockPointId, bool $online = false)
	{
		$message = null;
		$soAmount = 0;
		/*	if($customer->distributor_id == null)
		{
			$availableCredit = $this->availableCreditService->chechAvailableCredit($company, $customer->nav_id);
			$balanceDue = $availableCredit->getBalance_Due_LCY();
			$creditLimit = $availableCredit->getCalcAvailableCreditUI();
		}
		else
		{
			$creditLimit = $customer->credit_limit;
			$balanceDue = $customer->overdue_balance;
		}*/
		$recipients = [];
		$sender = $user;
		$purchaseOrder = new PurchaseOrder;
		//$purchaseOrder->order_number = "PO-".substr(strrev(time()),0,8);
		$purchaseOrder->shipping_method = $params['shipping_method'];
		$purchaseOrder->special_request = array_get($params, 'special_request', '');
		$UUId = Uuid::uuid4();
		$purchaseOrder->UUID = $UUId;
		$serverUrl = config('app.url');
		$callBackUrl = $serverUrl . "/api/webhook/purchase_orders/" . $UUId;
		//$validated = $this->salesOrderService->validateSalesOrder($company,$params,$customer);
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
			//	$line['discount'] = $line['discount'] + $line['onlineDiscount'];
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

		if ($customer->distributor_id != null) {
			/* update pricing for each line, take pricing for distributor*/
			foreach ($paramsUpdates['purchase_lines'] as &$pLine) {
				$product = Product::find($pLine['product_id']);
				$discount = $this->discountService->getDiscount('DISTRIBUTO', $customer->distributor, $product);
				$priceObj = $product->prices()->where('company_id', '=', $company->id)->first();
				$price = 0;
				if ($priceObj) {
					$price = (float)$priceObj->price;
				}

				if ($product->category->allow_cl && ($pLine['rate_type'] == 'CL')) {
					$clPercent = $this->clRateService->getRate($company, $product, 'DISTRIBUTO', $customer->distributor_id);
					$price = $price + ($price * $clPercent / 100);
					$pLine['unit_price'] = $price;
				} else {
					$pLine['unit_price'] = $price;
				}

				$pLine['discount'] = (float)$discount;
			}
			$distributor = (get_class($customer) == Distributor::class) ? $customer : $customer->distributor;
			$navPurchaseOrder = $this->salesOrderService->create($company, $distributor, $paramsUpdates, $callBackUrl, $batch_nos, $stock_point->code);
		} else {
			$navPurchaseOrder = $this->salesOrderService->create($company, $customer, $paramsUpdates, $callBackUrl, $batch_nos, $stock_point->code);
		}
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
		if ($customer->distributor_id != null)
			$customer->distributor->orders()->save($purchaseOrder);
		else
			$customer->orders()->save($purchaseOrder);
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
			$quantity = $purchaseLine->getQuantity();
			$purchaseOrderLine->product_id = $product->id;
			$purchaseOrderLine->nav_line_no = $purchaseLine->getLine_No();
			$line = $validLinesCollection->where('line_no', '=', $purchaseLine->getLine_No())->first();
			$line1 = $paramsUpdatesLineCollection->where('product_id', '=', $product->id)
				->where('batch_no', '=', $batch_nos[$line_id])
				->first();
			$purchaseOrderLine->discount = $line1['discount'];
			$purchaseOrderLine->unit_price = $line1['unit_price'];
			$purchaseOrderLine->quantity = $quantity;
			$purchaseOrderLine->rate_type = $purchaseLine->getRate_Type();
			$purchaseOrderLine->amount = $purchaseLine->getLine_Amount();
			$purchaseOrderLine->net_amount = $purchaseOrderLine->tax_amount + $purchaseOrderLine->amount;

			/** Handle Shipment & recieved */
			//$purchaseOrderLine->shipped_quantity  = $quantity;
			//$purchaseOrderLine->received_quantity = $quantity;

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
				($item) ? $item->delete() : '';
			} else if ($params['order_from'] == "blocked") {
				$item = BlockedItem::find($purchase_line['item_id']);
				$UUId = $item->UUID;
				$this->depotService->unBlock($UUId, $customer->company); //#672
				($item) ? $item->delete() : '';
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
		if ($customerType == 'dealers' && $customer->distributor_id != null) //email to Dud
		{
			$q = User::where('distributor_id', '=', $customer->distributor_id);
			$q->whereHas('roles', function ($q1) {
				$q1->where('name', '=', 'distributorAdmin');
			});
			$sender = $q->first();
			$mainDistributorUsers = $q->get();
			foreach ($mainDistributorUsers as $user) {
				if ($user->communication_email)
					$toMainDistributorEmailIds[] = $user->communication_email;
				$mainDistributorRecipients[] = $user;
			}

			foreach ($mainDistributorRecipients as $recipient) {
				$notification = new Notification;
				$notification->title = 'Created new Purchase Order';
				$notification->text = 'New Purchase ' . $blanketOrder->order_number . ' has been created';
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
			$Qmessage = (new PurchaseOrderNew($blanketOrder))->onQueue($queue);
			try {
				// if (!empty($toMainDistributorEmailIds))
				// 	Mail::to($toMainDistributorEmailIds)->queue($Qmessage);
			} catch (ConnectionException $e) {
				throw new RedisException;
			}
		}

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
			event(new Notifications($notification));
		}
		$queuePrefix = config('brand.queue_prefix');
		$queue = $queuePrefix . '.emails';
		$Qmessage = (new PurchaseOrderNew($blanketOrder))->onQueue($queue);
		try {
			// if (!empty($toEmailIds))
			// 	Mail::to($toEmailIds)->queue($Qmessage);
		} catch (ConnectionException $e) {
			throw new RedisException;
		}


		return [$purchaseOrder, $validated['invalid'], $message];
	}
	public function createBlanketOrder(Company $company, $customer, array $params, User $user, $customerType, bool $online = false, Transaction $transaction = null)
	{
		$recipients = [];
		$toEmailIds = [];
		$toDealerEmailIds = [];
		$sender = $user;
		$blanketOrder = new BlanketOrder;
		if (array_has($params, 'source')) //#767
			$blanketOrder->source = $params['source'];
		if (array_has($params, 'OnBehalf')) //#767
			$blanketOrder->on_behalf = $params['OnBehalf'];
		$blanketOrder->company_id = $company->id;
		if ($customer->distributor_id)
			$blanketOrder->distributor_id = $customer->distributor_id;
		if ($customer->super_stockist_id)
			$blanketOrder->super_stockist_id = $customer->super_stockist_id;
		if (array_has($params, 'end_customer_id'))
			$blanketOrder->end_customer_id = $params['end_customer_id'];
		if (array_has($params, 'installer_id'))
			$blanketOrder->engineer_id = $params['installer_id'];
		$blanketOrder->save();

		$collection = collect($params['purchase_lines']);
		$orderAmount = $collection->sum('amount');
		if (!$online) {
			if ($customer->distributor_id == null) {
				$availableCredit = $this->availableCreditService->chechAvailableCredit($company, $customer->nav_id);
				$balanceDue = $availableCredit->getBalance_Due_LCY();
				$creditLimit = $availableCredit->getCalcAvailableCreditUI();
			} else {
				$creditLimit = $customer->credit_limit;
				$balanceDue = $customer->overdue_balance;
			}
			if ($orderAmount > $creditLimit) {
				$message = "Your order cannot be processed at this time due to negative credit limit. Please contact your Relationship Manager for further actions.";
				return [null, null, $message];
			}
			if ($orderAmount <= $creditLimit && $balanceDue > 0) {
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
		foreach ($groupedByStockPoint as $stock_point_id => $purchase_lines) {
			$stockPointCnt++;
			if ($stock_point_id === "" || $stock_point_id === null) {
				####################################################################################################
				$ssLinesGrouped = collect($purchase_lines)->groupBy('super_stockist_id');

				foreach ($ssLinesGrouped as $grouped_ss_id => $ss_purchase_lines) {
					if ($grouped_ss_id > 0) {
						if (!empty($customer->distributor)) {
							/*dealer to Distributor */
							list($purchaseOrder, $invalidLines, $message) = $this->createSSOrder($company, $customer, $params, $user, $ss_purchase_lines, $blanketOrder, $grouped_ss_id);
						} else {
							/* SS to Another SS order */
							list($purchaseOrder, $invalidLines, $message) = $this->createSSOrder($company, $customer, $params, $user, $ss_purchase_lines, $blanketOrder, $grouped_ss_id);
						}
						if (count($invalidLines)) {
							$allInvalidLines = $allInvalidLines->concat($invalidLines);
						}
						if ($purchaseOrder === null) {
							$orderNotPlacedCnt++;
						}
					} else //Co SS Stock
					{
						$distributroLinesGrouped = collect($ss_purchase_lines)->groupBy('distributor_id');
						foreach ($distributroLinesGrouped as $grouped_distributor_id => $distributor_purchase_lines) {
							if ($grouped_distributor_id > 0) {
								list($purchaseOrder, $invalidLines, $message) = $this->createDudOrder($company, $customer, $params, $user, $distributor_purchase_lines, $blanketOrder, $grouped_distributor_id);
								if (count($invalidLines)) {
									$allInvalidLines = $allInvalidLines->concat($invalidLines);
								}
								if ($purchaseOrder === null) {
									$orderNotPlacedCnt++;
								}
							}

							/*if ($grouped_distributor_id == $customer->distributor_id) {
								list($purchaseOrder, $invalidLines, $message) = $this->createDudOrder($company, $customer, $params, $user, $distributor_purchase_lines, $blanketOrder, $stock_point_id);
								if (count($invalidLines)) {
									$allInvalidLines = $allInvalidLines->concat($invalidLines);
								}
								if ($purchaseOrder === null) {
									$orderNotPlacedCnt++;
								}
							} else if ($customer->distributor > 0) {
								$coDistributor = Distributor::find($grouped_distributor_id);
								$coDistributor_user = User::where('distributor_id', '=', $grouped_distributor_id)->first();
								//$distributor = Distributor::find($customer->distributor_id);
								$coDistributorBlanketOrder = new BlanketOrder;
								//$coDistributorBlanketOrder->order_number = "PO-".substr(strrev(time()),0,8);
								$coDistributorBlanketOrder->company_id = $company->id;
								$coDistributorBlanketOrder->distributor_id = $coDistributor->id;
								$coDistributorBlanketOrder->save();
								list($coDistributorPurchaseOrder, $invalidLines, $message) = $this->createCoDistributorOrder($company, $customer->distributor, $coDistributor, $user, $distributor_purchase_lines, $params, $coDistributorBlanketOrder);
								if ($coDistributorPurchaseOrder) {
									$customer->distributor->blanketOrders()->save($coDistributorBlanketOrder);
									$newPurchaseOrder = new PurchaseOrder;
									//$newPurchaseOrder->order_number = "PO-".substr(strrev(time()),0,8);
									$newPurchaseOrder->shipping_method = $params['shipping_method'];
									$newPurchaseOrder->special_request = $params['special_request'];
									$newPurchaseOrder->UUID = Uuid::uuid4();
									$newPurchaseOrder->navision_order_number = '';
									$newPurchaseOrder->distributor_id = $customer->distributor_id;
									$newPurchaseOrder->blanket_order_id = $blanketOrder->id;
									$newPurchaseOrder->nav_key = '';
									$newPurchaseOrder->company_id = $company->id;
									$newPurchaseOrder->status = $coDistributorPurchaseOrder->status;
									$newPurchaseOrder->shipping_method = $params['shipping_method'];
									$newPurchaseOrder->special_request = $params['special_request'];
									$newPurchaseOrder->save();

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
									$newPurchaseOrder->addresses()->save($address);

									$originalParamLines = collect($distributor_purchase_lines);
									$totalAmount = 0;
									foreach ($coDistributorPurchaseOrder->purchase_lines as $temp_purchase_line) {
										$tempLine = new PurchaseLine;
										$tempLine->nav_line_no = $temp_purchase_line->nav_line_no;
										$tempLine->product_id = $temp_purchase_line->product_id;
										$tempLine->purchase_order_id = $newPurchaseOrder->id;
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
										$newPurchaseOrder->purchase_lines()->save($tempLine);
										$totalAmount += (float)$tempLine->amount;
									}
									$newPurchaseOrder->Amount = $totalAmount;
									$newPurchaseOrder->save();

									$coDistributorBlanketOrder->related_po_id = $newPurchaseOrder->id;
									$coDistributorBlanketOrder->save();
									$customer->orders()->save($newPurchaseOrder);
									$notifiable = (object)[
										"number" => $coDistributor_user->phone,
										"supplier" => $coDistributor->name,
										"customer" => $customer->distributor->name
									];
									SMSNotification::send($notifiable, new PurchaseOrderReceivedNotification($company)); //#595 Notify Co-Distriutor about DUD Order
								}
							} else {
								list($coDistributorPurchaseOrder, $invalidLines, $message) = $this->createDealerOrder($company, $customer, $params, $user, $distributor_purchase_lines, $blanketOrder, $grouped_distributor_id);
							}*/
						}
					}
				}
				####################################################################################################
			} else {
				//Dist or Dlr under Comp's order
				if (($customer->distributor_id != null)/* && ($customerType!='dealers') */) {
					$distributorBlanketOrder = new BlanketOrder;
					if (array_has($params, 'source')) //#767
						$distributorBlanketOrder->source = $params['source'];
					if (array_has($params, 'OnBehalf')) //#767
						$distributorBlanketOrder->on_behalf = $params['OnBehalf'];
					//$distributorBlanketOrder->order_number = "PO-".substr(strrev(time()),0,8);
					$distributorBlanketOrder->company_id = $company->id;
					$distributorBlanketOrder->save();
					$effectiveBlanketOrder = $distributorBlanketOrder;
				} else //Dist or Dlr under Comp's order
				{
					$distributorBlanketOrder = $effectiveBlanketOrder = $blanketOrder;
				}
				$stock_point = StockPoint::where('id', '=', $stock_point_id)->first();
				$company_lines = $purchase_lines;
				list($purchaseOrder, $invalidLines, $message) = $this->create($company, $customer, $customerType, $params, $user, $company_lines, $effectiveBlanketOrder, $stock_point_id);
				/* create new purchase order for dealer with distributor*/
				if ($purchaseOrder && $customer->distributor) {
					$customer->distributor->blanketOrders()->save($distributorBlanketOrder);
					/* this is order placed for dealer create new PO based on original PO*/
					$newPurchaseOrder = new PurchaseOrder;
					//$newPurchaseOrder->order_number = "PO-".substr(strrev(time()),0,8);
					$newPurchaseOrder->shipping_method = $params['shipping_method'];
					$newPurchaseOrder->special_request = $params['special_request'];
					$newPurchaseOrder->UUID = Uuid::uuid4();
					$newPurchaseOrder->navision_order_number = '';
					$newPurchaseOrder->distributor_id = $customer->distributor_id;
					if ($stock_point_id > 0) {
						$newPurchaseOrder->stock_point_id = $stock_point_id;
					}
					$newPurchaseOrder->blanket_order_id = $blanketOrder->id;
					$newPurchaseOrder->nav_key = '';
					$newPurchaseOrder->company_id = $company->id;
					$newPurchaseOrder->status = $purchaseOrder->status;
					$newPurchaseOrder->shipping_method = $params['shipping_method'];
					$newPurchaseOrder->special_request = $params['special_request'];
					$newPurchaseOrder->save();

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
					$newPurchaseOrder->addresses()->save($address);

					$originalParamLines = collect($params['purchase_lines']);
					$totalAmount = 0;
					foreach ($purchaseOrder->purchase_lines as $temp_purchase_line) {
						$tempLine = new PurchaseLine;
						$tempLine->nav_line_no = $temp_purchase_line->nav_line_no;
						$tempLine->product_id = $temp_purchase_line->product_id;
						$tempLine->purchase_order_id = $newPurchaseOrder->id;
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


						/*				$purchaseOrderLine->net_amount = $purchaseOrderLine->tax_amount + $purchaseOrderLine->amount;
			
$purchaseOrderLine->tax_amount = ((float) $purchaseLine->getLine_Amount()) * $purchaseOrderLine->tax_percent /100;

$tempLine->tax_amount = ((float) $tempLine->amount * $tempLine->tax_percent /100;




						$prod = Product::where('nav_id', '=', $purchaseLine->getNo())->get()->first();
			$tax = $company->region->taxes()->where('category_code','=',$product->category->code)->first();
			if($tax)
			{
				$purchaseOrderLine->tax_type_code = $tax->tax_type_code;
				$purchaseOrderLine->tax_percent = $tax->tax_percent;
				$purchaseOrderLine->tax_amount = ((float) $purchaseLine->getLine_Amount()) * $purchaseOrderLine->tax_percent /100;
			}
			else
			{
				$purchaseOrderLine->tax_percent = 0;
				$purchaseOrderLine->tax_amount = 0;

			}
*/


						$newPurchaseOrder->purchase_lines()->save($tempLine);
						$totalAmount += (float)$tempLine->amount;
					}
					$newPurchaseOrder->Amount = $totalAmount;
					$newPurchaseOrder->save();


					$distributorBlanketOrder->related_po_id = $newPurchaseOrder->id;
					$distributorBlanketOrder->save();
					$customer->orders()->save($newPurchaseOrder);
				}
				if (count($invalidLines)) {
					$allInvalidLines = $allInvalidLines->concat($invalidLines);
				}
				if ($purchaseOrder === null) {
					$orderNotPlacedCnt++;
				}
				$companyStockPointCnt++;
			}
		}
		if ($orderNotPlacedCnt == $stockPointCnt) {
			$blanketOrder->forceDelete();
			if ($distributorBlanketOrder)
				$distributorBlanketOrder->forceDelete();
			return [null, $allInvalidLines, $message];
		}
		if ($allInvalidLines->count() == count($params['purchase_lines'])) {
			$blanketOrder->forceDelete();
			if ($distributorBlanketOrder)
				$distributorBlanketOrder->forceDelete();
			return [null, $allInvalidLines, $message];
		}
		$customer->blanketOrders()->save($blanketOrder);
		if ($transaction) {
			$transaction->blanketOrder()->associate($blanketOrder);
			$transaction->save();
		}
		if ($customerType == 'distributors') {
			$query = User::where('distributor_id', '=', $customer->id);
			$query->whereHas('roles', function ($q) {
				$q->where('name', '=', 'distributorAdmin');
			});
			$users = $query->get();

			foreach ($users as $user) {
				if ($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
			}
		} //add Customer Admin Email Ids
		if ($customerType == 'super_stockists') {
			$query = User::where('super_stockist_id', '=', $customer->id);
			$query->whereHas('roles', function ($q) {
				$q->where('name', '=', 'superStockistAdmin');
			});
			$users = $query->get();

			foreach ($users as $user) {
				if ($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
			}
		} //add Customer Admin Email Ids
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
			event(new Notifications($notification));
		}
		$queuePrefix = config('brand.queue_prefix');
		$queue = $queuePrefix . '.emails';
		$data = [];
		if ($online) {
			if ($transaction) {
				$data['payment_ref_no'] = $transaction->getSettingsForType('order')->get('payment.ref');
				$data['amount_paid'] = $transaction->getSettingsForType('order')->get('amount');
			}
			if ($customerType == 'dealers' && $customer->distributor_id != null) {
				$Qmessage = (new PurchaseOrderNewDudOnline($blanketOrder, $data))->onQueue($queue);
			} else {
				$Qmessage = (new PurchaseOrderNewOnline($blanketOrder, $data))->onQueue($queue);
			}
		} else {
			if ($customerType == 'dealers' && $customer->distributor_id != null) {
				$Qmessage = (new PurchaseOrderNewDud($blanketOrder))->onQueue($queue);
			} else {
				$Qmessage = (new PurchaseOrderNew($blanketOrder))->onQueue($queue);
			}
		}
		/*if($customerType == 'dealers' && $customer->distributor_id != null)
			$Qmessage = (new PurchaseOrderNewDud($blanketOrder))->onQueue($queue);
		else	
			$Qmessage = (new PurchaseOrderNew($blanketOrder))->onQueue($queue);*/
		try {
			// if (!empty($toEmailIds))
			// 	Mail::to($toEmailIds)->queue($Qmessage);
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

		foreach ($mainDistributorRecipients as $recipient) {
			$notification = new Notification;
			$notification->title = 'Created new Dealer Order';
			$notification->text = 'New Dealer Order ' . $blanketOrder->order_number . ' has been created';
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
		$Qmessage = (new dealerSalesOrderNew($blanketOrder))->onQueue($queue);
		try {
			// if (!empty($toMainDistributorEmailIds))
			// 	Mail::to($toMainDistributorEmailIds)->queue($Qmessage);
		} catch (ConnectionException $e) {
			throw new RedisException;
		}
		return [$blanketOrder, $allInvalidLines, $message];
	}

	public function createCoDistributorOrder(Company $company, $distributor, $coDistributor, User $user, $lines, $params, $blanketOrder)
	{
		$message = null;
		$soAmount = 0;
		$recipients = [];
		$purchaseOrder = new PurchaseOrder;
		$purchaseOrder->shipping_method = $params['shipping_method'];
		$purchaseOrder->special_request = $params['special_request'];
		$UUId = Uuid::uuid4();
		$purchaseOrder->UUID = $UUId;
		$purchaseOrder->nav_key = $UUId;
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
			$product = Product::find($line['product_id']);
			$discount = $this->coDistributorDiscountService->getDiscount($coDistributor, $product, $distributor);
			$unitPrice = (float)$line['unit_price'];
			$unitPrice = round($unitPrice - ($unitPrice * $discount / 100), 2);
			$lineAmount = $unitPrice * $line['quantity'];
			$soAmount = $soAmount + $lineAmount;
		}
		$paramsUpdates = [
			"shipping_method" => $params['shipping_method'],
			"special_request" => $params['special_request'],
			"shipping_address" => $params['shipping_address'],
			"order_from" => $params['order_from'],
			"purchase_lines" => $validLines,
		];
		$purchaseOrder->status = 'Open';
		$purchaseOrder->amount = $soAmount;
		$purchaseOrder->company_id = $company->id;
		$purchaseOrder->distributor_id = $coDistributor->id;
		$purchaseOrder->navision_order_number = '';
		$purchaseOrder->blanket_order_id = $blanketOrder->id;
		$distributor->orders()->save($purchaseOrder);
		foreach ($validLines as $purchaseLine) {
			$product = Product::find($line['product_id']);
			$discount = $this->coDistributorDiscountService->getDiscount($coDistributor, $product, $distributor);
			$priceObj = $product->prices()->where('company_id', '=', $company->id)->first();
			$price = 0;
			if ($priceObj) {
				$price = (float)$priceObj->price;
			}
			if ($product->category->allow_cl && ($purchaseLine['rate_type'] == 'CL')) {
				$clPercent = $this->clRateService->getRate($distributor->company, $product, 'DISTRIBUTO', $distributor->id);
				$price = $price + ($price * $clPercent / 100);
			}
			$purchaseOrderLine = new PurchaseLine;
			$purchaseOrderLine->product_id = $purchaseLine['product_id'];
			$purchaseOrderLine->nav_line_no = $purchaseLine['line_no'];
			$purchaseOrderLine->discount = $discount;
			$purchaseOrderLine->unit_price = $price;
			$purchaseOrderLine->quantity = $purchaseLine['quantity'];
			$purchaseOrderLine->rate_type = $purchaseLine['rate_type'];

			$purchaseOrderLine->amount = ($price - ($price * ($discount / 100))) * round($purchaseLine['quantity'], 2);
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

		foreach ($lines as $line) {
			if ($params['order_from'] == "cart") {
				$item = CartItem::find($line['item_id']);
				($item) ? $item->delete() : '';
			} else if ($params['order_from'] == "blocked") {
				$item = BlockedItem::find($line['item_id']);
				($item) ? $item->delete() : '';
			}
			$currentStock = $coDistributor->stocks->where('product_id', '=', $line['product_id'])
				->where('batch_no', '=', $line['batch_no'])->first();
			$newStock = $currentStock->quantity - $line['quantity'];
			$currentStock->quantity = $newStock;
			$currentStock->save();
			//adjust Distributor Stock
		}

		$q = User::where('distributor_id', '=', $distributor->id);
		$q->whereHas('roles', function ($q1) {
			$q1->where('name', '=', 'distributorAdmin');
		});
		$sender = $q->first();
		$mainDistributorUsers = $q->get();
		foreach ($mainDistributorUsers as $user) {
			if ($user->communication_email)
				$toMainDistributorEmailIds[] = $user->communication_email;
			$mainDistributorRecipients[] = $user;
		}
		$query = User::where('distributor_id', '=', $coDistributor->id);
		$query->whereHas('roles', function ($q) {
			$q->where('name', '=', 'distributorAdmin');
		});
		$users = $query->get();

		foreach ($users as $user) {
			if ($user->communication_email)
				$toCoDistributorEmailIds[] = $user->communication_email;
			$coDistributorRecipients[] = $user;
		}
		//Co Distributor
		foreach ($coDistributorRecipients as $recipient) {
			$notification = new Notification;
			$notification->title = 'Created new Sales Order';
			$notification->text = 'New Sales Order ' . $blanketOrder->order_number . ' has been created for Co-Distributor';
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
		$Qmessage = (new salesOrderNew($blanketOrder))->onQueue($queue);
		try {
			// if (!empty($toCoDistributorEmailIds))
			// 	Mail::to($toCoDistributorEmailIds)->queue($Qmessage);
		} catch (ConnectionException $e) {
			throw new RedisException;
		}

		foreach ($users as $user) {
			if ($user->communication_email)
				$toCoDistributorEmailIds[] = $user->communication_email;
			$coDistributorRecipients[] = $user;
		}
		//Main Distributor
		foreach ($mainDistributorRecipients as $recipient) {
			$notification = new Notification;
			$notification->title = 'Created new Purchase Order';
			$notification->text = 'New Purchase Order ' . $blanketOrder->order_number . ' has been placed with Co-Distributor';
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
		$Qmessage = (new PurchaseOrderNewDistributor($blanketOrder))->onQueue($queue);
		try {
			// if (!empty($toMainDistributorEmailIds))
			// 	Mail::to($toMainDistributorEmailIds)->queue($Qmessage);
		} catch (ConnectionException $e) {
			throw new RedisException;
		}

		return [$purchaseOrder, [], $message];
	}
	public function createDudOrder(Company $company, $customer, array $params, User $user, $distributor_lines, $blanketOrder, $stockPointId)
	{
		if (!empty($customer) && (get_class($customer) == Distributor::class)) {
			$distributor = $customer;
		} else {
			$distributor = Distributor::find($customer->distributor_id);
		}

		$message = null;
		$soAmount = 0;
		$recipients = [];
		$sender = $user;
		$purchaseOrder = new PurchaseOrder;
		//$purchaseOrder->order_number = "PO-".substr(strrev(time()),0,8);
		$purchaseOrder->shipping_method = $params['shipping_method'];
		$purchaseOrder->special_request = $params['special_request'];
		$UUId = Uuid::uuid4();
		$purchaseOrder->UUID = $UUId;
		$purchaseOrder->nav_key = $UUId;
		$validated = $this->salesOrderService->validatePurchaseLines($company, $distributor_lines->all(), $customer, $params['order_from'], $stockPointId);

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
			"special_request" => $params['special_request'],
			"shipping_address" => $params['shipping_address'],
			"order_from" => $params['order_from'],
			"purchase_lines" => $validLines,
		];
		$validLinesCollection = collect($validLines);
		$creditLimit = $customer->credit_limit;
		$balanceDue = $customer->overdue_balance;
		if ($soAmount > ($creditLimit - $balanceDue)) {
			$message = "Your order cannot be processed at this time due to negative credit limit. Please contact your Relationship Manager for further actions.";
			return [null, $validated['invalid'], $message];
		}
		if ($soAmount <= $creditLimit && $balanceDue > 0) {
			$message = "Your order cannot be processed at this time due to overdue payments. Please contact your Relationship Manager for further actions.";
			return [null, $validated['invalid'], $message];
		}
		$purchaseOrder->status = 'Approved';
		$purchaseOrder->amount = $soAmount;
		$purchaseOrder->company_id = $company->id;
		$purchaseOrder->distributor_id = $customer->distributor_id;
		$purchaseOrder->navision_order_number = '';
		if (array_has($params, 'end_customer_id'))
			$purchaseOrder->end_customer_id = $params['end_customer_id'];
		if (array_has($params, 'installer_id'))
			$purchaseOrder->engineer_id = $params['installer_id'];
		if (!empty($distributor->id)) {
			$purchaseOrder->distributor_id = $distributor->id;
		}
		if (!empty($stockPointId)) {
			$purchaseOrder->dist_stock_point_id = $stockPointId;
			$purchaseOrder->distributor_id = $stockPointId;
		}
		//	$purchaseOrder->stock_point_id = $stockPointId;
		$purchaseOrder->blanket_order_id = $blanketOrder->id;
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


		//dd($distributor, $stockPointId);
		/** Create Distributor order for other Distributor stockpoint */
		if ((get_class($customer) == Dealer::class) && ((!empty($distributor)) && ($distributor->id != $stockPointId)) && $stockPointId != '') {
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
				//$customer->distributor->orders()->save($distToDistPurchaseOrder)
				(!empty($customer->distributor)) ? $customer->distributor->orders()->save($distToDistPurchaseOrder) : '';
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
					$distToDistPurchaseOrderLine->tax_amount = $distToDistPurchaseOrderLine->amount * $distToDistPurchaseOrderLine->tax_percent / 100;
					$distToDistPurchaseOrderLine->net_amount = $distToDistPurchaseOrderLine->tax_amount + $distToDistPurchaseOrderLine->amount;
					$distToDistPurchaseOrderLine->batch_no = $purchaseLine['batch_no'];
					//$distToDistPurchaseOrder->purchase_lines()->save($distToDistPurchaseOrderLine);
					(!empty($distToDistPurchaseOrder)) ? $distToDistPurchaseOrder->purchase_lines()->save($distToDistPurchaseOrderLine) : '';
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
				$distToDistBlanketOrder->related_po_id = $purchaseOrder->id; //DUD PO Id on Dist BO
				$distToDistBlanketOrder->save();
				//$customer->distributor->blanketOrders()->save($distToDistBlanketOrder)
				(!empty($customer->distributor)) ? $customer->distributor->blanketOrders()->save($distToDistBlanketOrder) : '';
				//return [$purchaseOrder, $validated['invalid'], $message];
			}
		} else if (((get_class($customer) == Dealer::class) &&  (empty($distributor) && $stockPointId != ''))) {	
				$blanketOrder->distributor_id = $stockPointId;
				$blanketOrder->save();
				$purchaseOrder->dist_stock_point_id = $stockPointId;
				$purchaseOrder->distributor_id = $stockPointId;
				$purchaseOrder->save();
		}

		//$purchase_lines = $validated['valid'];
		foreach ($lines as $line) {
			if ($params['order_from'] == "cart") {
				$item = CartItem::find($line['item_id']);
				($item) ? $item->delete() : '';
			} else if ($params['order_from'] == "blocked") {
				$item = BlockedItem::find($line['item_id']);
				($item) ? $item->delete() : '';
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
			//adjust Distributor Stock
		}
		return [$purchaseOrder, $validated['invalid'], $message];
	}
	public function update(Company $company, $customer, $orderId)
	{
		$new_approach = config('brand.new_approach'); //#635
		$purchaseOrder = PurchaseOrder::find($orderId);
		$purchaseLines = $purchaseOrder->purchase_lines()->get();

		$navPurchaseOrder = null;
		
		if($purchaseOrder->navision_order_number){
			$navPurchaseOrder = $this->salesOrderService->refresh($company, $purchaseOrder->navision_order_number);
		}
			
		$blanketOrder = $purchaseOrder->blanket_order;
		$relatedPO = $blanketOrder->relatedOrder;
		if ($relatedPO)
			$relatedPOLines = $relatedPO->purchase_lines()->get();
		$alert = false;
		if (!$navPurchaseOrder && !empty($purchaseOrder->navision_order_number)) //complete shipment scenario
		{
			
			$psiPresent = $this->psiService->getSO($company, $purchaseOrder->navision_order_number);
			if ($psiPresent) {
				foreach ($purchaseLines as $purchaseLine) {
					$productId = $purchaseLine->product_id;
					$quantity = $purchaseLine->quantity;
					$shippedQuantity = $purchaseLine->shipped_quantity;
					if ($shippedQuantity <> $quantity) {
						$purchaseLine->shipped_quantity = $quantity;
						if ($relatedPO)
							$purchaseLine->received_quantity = $quantity; //#510
						$purchaseLine->save();
					}
				}
				$purchaseOrder->status = 'Complete';
				$psi = $this->psiService->getSI($company, $purchaseOrder->navision_order_number); //#635
				$purchaseOrder->nav_invoice_number = $psi->getNo(); //#635
				$purchaseOrder->nav_invoice_date = strtotime($psi->getPosting_Date()); //#666
				$purchaseOrder->save();

				if ($relatedPO) {
					$relatedPO->status = 'Complete';
					$relatedPO->nav_invoice_number = $purchaseOrder->nav_invoice_number; //#635
					$relatedPO->nav_invoice_date = $purchaseOrder->nav_invoice_date; //#862 
					$relatedPO->save();
					foreach ($relatedPOLines as $relatedPurchaseLine) {
						$relatedPurchaseLine->shipped_quantity = $relatedPurchaseLine->quantity;
						$relatedPurchaseLine->save();
					}
					$blanketOrder->shipped = true;
					$blanketOrder->order_status = 'Complete'; //#635
					$blanketOrder->save();
				}
			} else {
				if ($purchaseOrder->status <> 'Cancelled') {
					$purchaseOrder->status = 'Cancelled';
					$purchaseOrder->cancelation_reason = 'Cancelled by Company Admin';
					$purchaseOrder->save();
					if ($relatedPO) {
						$relatedPO->status = 'Cancelled';
						$relatedPO->cancelation_reason = 'Cancelled by Company Admin';
						$relatedPO->save();
						$blanketOrder->cancelation_reason = 'Cancelled by Company Admin';
						//$blanketOrder->canceled_by = $loggedInUser->id;
						$blanketOrder->save();
					}
				}
			}
			
		} else if ($navPurchaseOrder && $navPurchaseOrder->getStatus() == 'Released') //partial shipment scenario
		{
			$navPurchaseLines = $navPurchaseOrder->getSalesLines()->getSales_Order_Line();
			if ($navPurchaseLines) {
				if (!is_array($navPurchaseLines)) {
					$navPurchaseLines = [$navPurchaseLines];
				}
				$shipped = false;
				foreach ($navPurchaseLines as $navPurchaseLine) {
					$line = $purchaseOrder->purchase_lines()->where('nav_line_no', '=', $navPurchaseLine->getLine_No())->first();
					if ($relatedPO)
						$relatedPOLine = $relatedPO->purchase_lines()->where('nav_line_no', '=', $navPurchaseLine->getLine_No())->first();

					if ($navPurchaseLine->getQuantity_Shipped()) {
						$shipped = true;
						if ($navPurchaseLine->getQuantity_Shipped() != $line->shipped_quantity) {
							$diffShipped = $navPurchaseLine->getQuantity_Shipped() - $line->shipped_quantity;
							if ($diffShipped) {
								$line->shipped_quantity = $navPurchaseLine->getQuantity_Shipped();
								if ($relatedPO) {
									$relatedPOLine->shipped_quantity = $navPurchaseLine->getQuantity_Shipped();
									$line->received_quantity = $navPurchaseLine->getQuantity_Shipped(); //#510
								}
							}
							$line->save();
							if ($relatedPO)
								$relatedPOLine->save();
						}
					}
				}
				if ($shipped) {
					$totalLineCnt = $purchaseLines->count();
					$completelyShippedCnt = DB::table('purchase_lines')->where('purchase_order_id', '=', $purchaseOrder->id)->whereRaw('shipped_quantity = quantity')->count();
					if ($totalLineCnt == $completelyShippedCnt) {
						$purchaseOrder->status = 'Shipped'; //#449 
						if ($relatedPO) {
							$relatedPO->status = 'Shipped';
							$blanketOrder->shipped = true;
							$blanketOrder->order_status = 'Shipped';
							/*	if($new_approach == true)
							{
								$relatedPO->blanket_order->shipped = true;
								$relatedPO->blanket_order->order_status = 'Shipped';
							}*/
						}
						$alert = true; //Manual SO Closure scenario #248;
					} else {
						if ($purchaseOrder->blanket_order->shipped == false) {
							$purchaseOrder->status = 'Partially Shipped';
							$purchaseOrder->blanket_order->order_status = 'Partially Shipped'; //#635
							if ($relatedPO)
								$relatedPO->status = 'Partially Shipped';
						}
					}
				} else {
					$purchaseOrder->status = 'Approved';
					if ($relatedPO)
						$relatedPO->status = 'Approved';
				}
				//	$purchaseOrder->Amount = $navPurchaseOrder->getAmount();
				$purchaseOrder->Amount = $purchaseOrder->purchase_lines->sum('net_amount'); //#741
				$purchaseOrder->save();
				if ($relatedPO) {
					//	$relatedPO->Amount = $navPurchaseOrder->getAmount();
					$relatedPO->save();
					$blanketOrder->save();
					//	$relatedPO->blanket_order->save();//#635
				}
			}
		} else //status update in Nav 
		{
			if(!empty($navPurchaseOrder)){
				$purchaseOrder->status = $navPurchaseOrder->getStatus();
				//	$purchaseOrder->Amount = $navPurchaseOrder->getAmount();
				$purchaseOrder->Amount = $purchaseOrder->purchase_lines->sum('net_amount'); //#741
				$purchaseOrder->save();
				if ($relatedPO) {
					$relatedPO->status = $navPurchaseOrder->getStatus();
					//	$relatedPO->Amount = $navPurchaseOrder->getAmount();
					$relatedPO->save();
				}
			}			
		}
		if ($relatedPO) //#635
		{
			if ($relatedPO->status == 'Complete') {
				$distributor = $relatedPO->blanket_order->distributor;
				$superStockist = $relatedPO->blanket_order->superStockist;

				if (!empty($superStockist)) {
					$superStockist_invoice_series = $superStockist->invoice_series + 1;
					$superStockist_code = substr($superStockist->nav_id, 5);
					$temp = str_pad($superStockist_invoice_series, 5, '0', STR_PAD_LEFT);

					if (date('m') <= 3)
						$year = date('Y');
					else
						$year = date('Y') + 1;
					$year1 = $year - 1;
					$fiscalyear = $year1 . '-' . $year;
					$ros_inv_num = 'PI' . '/' . $fiscalyear . '/' . $superStockist_code . '/' . $temp;
					if ($relatedPO->blanket_order->ros_invoice_number == null) {
						$relatedPO->blanket_order->ros_invoice_number = $ros_inv_num;
						$relatedPO->blanket_order->save();
						$superStockist->invoice_series = $superStockist_invoice_series;
						$superStockist->save();
					}
				} else if (!empty($distributor)) {
					$distributor = $relatedPO->blanket_order->distributor;
					$distributor_invoice_series = $distributor->invoice_series + 1;
					$distributor_code = substr($distributor->nav_id, 5);
					$temp = str_pad($distributor_invoice_series, 5, '0', STR_PAD_LEFT);

					if (date('m') <= 3)
						$year = date('Y');
					else
						$year = date('Y') + 1;
					$year1 = $year - 1;
					$fiscalyear = $year1 . '-' . $year;
					$ros_inv_num = 'PI' . '/' . $fiscalyear . '/' . $distributor_code . '/' . $temp;
					if ($relatedPO->blanket_order->ros_invoice_number == null) {
						$relatedPO->blanket_order->ros_invoice_number = $ros_inv_num;
						$relatedPO->blanket_order->save();
						$distributor->invoice_series = $distributor_invoice_series;
						$distributor->save();
					}
				}
			}
		}
		return [$purchaseOrder, $alert];
		//return $purchaseOrder;
	}
	public function print_dc($orderId)
	{

		$blanketOrder = BlanketOrder::find($orderId);
		$company = $blanketOrder->orderable->company;
		$purchaseOrder = $blanketOrder->purchase_orders()->first();
		$shippingAddress = $purchaseOrder->addresses()->first();
		$lines = collect([]);
		$purchaseOrders = $blanketOrder->purchase_orders;
		foreach ($purchaseOrders as $po) {
			$purchase_lines = $po->purchase_lines()->where('dc_printed', '=', 0)->where('shipped_quantity', '>', 0)->get();
			foreach ($purchase_lines as $pl) {
				$lines->push($pl);
			}
		}
		if ($lines->count() > 0)
			$out =  (new DeliveryChallan)->generate($blanketOrder, $company, $shippingAddress, $lines);
		else
			return null;
		return $out;
	}
	public function refresh($UUId)
	{
		$purchaseOrder = PurchaseOrder::where('UUID', '=', $UUId)->get()->first();
		$customer = $purchaseOrder->orderable()->get()->first();
		$company = Company::find($customer->company_id);
		$navPurchaseOrder = $this->salesOrderService->refresh($company, $purchaseOrder->navision_order_number);
		if (!$navPurchaseOrder) //order is completely shipped
		{
			$psiPresent = $this->psiService->getSO($company, $purchaseOrder->navision_order_number);
			if ($psiPresent) {
				$purchaseOrder->status = 'Shipped';
			} else {
				$purchaseOrder->status = 'Cancelled';
			}
			$purchaseOrder->save();
		}
		/*	if($navPurchaseOrder->getStatus() == 'Released')
		{
			$this->update($company, $customer, $purchaseOrder->id);
		}*/ else {
			$purchaseOrder->status = $navPurchaseOrder->getStatus();
			$purchaseOrder->save();
		}
	}
	public function all($company, $customer, array $params = [])
	{
		if (array_has($params, 'page')) {
			$purchaseOrders = $customer->orders()->when($params, function ($query) use ($params) {
				if (array_has($params, 'status')) {
					$query->where('status', '=', $params['status']);
				}
				if (array_has($params, 'start_date')) {
					$query->where('created_at', '>=', $params['start_date']);
				}
				if (array_has($params, 'end_date')) {
					$query->where('created_at', '<=', $params['end_date']);
				}
				if (array_has($params, 'so')) {
					$query->where('navision_order_number', 'like', "%{$params['so']}%");
				}
				if (array_has($params, 'order_number')) {
					$query->where('order_number', 'like', "%{$params['order_number']}%");
				}
			})->orderBy('created_at', 'desc')->paginate($params['per_page']);
		} else {
			$purchaseOrders = $customer->orders()->when($params, function ($query) use ($params) {
				if (array_has($params, 'status')) {
					$query->where('status', '=', $params['status']);
				}
				if (array_has($params, 'start_date')) {
					$query->where('created_at', '>=', $params['start_date']);
				}
				if (array_has($params, 'end_date')) {
					$query->where('created_at', '<=', $params['end_date']);
				}
				if (array_has($params, 'so')) {
					$query->where('navision_order_number', 'like', "%{$params['so']}%");
				}
				if (array_has($params, 'order_number')) {
					$query->where('order_number', 'like', "%{$params['order_number']}%");
				}
			})->orderBy('created_at', 'desc')->get();
		}
		return $purchaseOrders;
	}
	public function listCompanyPOs($companyId, array $params = [])
	{
		$company = Company::find($companyId);
		$customer = null;
		if (array_has($params, 'selectedCustomerType') && array_has($params, 'selectedCustomerId')) {
			if ($params['selectedCustomerType'] == "Distributor")
				$customer = Distributor::find($params['selectedCustomerId']);
			elseif ($params['selectedCustomerType'] == "Dealer")
				$customer = Dealer::find($params['selectedCustomerId']);
			elseif ($params['selectedCustomerType'] == "SuperStockist")
				$customer = SuperStockist::find($params['selectedCustomerId']);
		}
		if ($customer)
			$queryMain = $customer->orders()->whereNull('distributor_id');
		else
			$queryMain = $company->purchaseOrders()->whereNull('distributor_id');
		if (array_has($params, 'page')) {
			$purchaseOrders = $queryMain->when($params, function ($query) use ($params) {
				if (array_has($params, 'status')) {
					$query->where('status', '=', $params['status']);
				}
				if (array_has($params, 'so')) {
					$query->where('navision_order_number', '=', $params['so']);
				}
			})->orderBy('created_at', 'desc')->paginate($params['per_page']);
		} else {
			$purchaseOrders = $queryMain->when($params, function ($query) use ($params) {
				if (array_has($params, 'status')) {
					$query->where('status', '=', $params['status']);
				}
				if (array_has($params, 'so')) {
					$query->where('navision_order_number', '=', $params['so']);
				}
			})->orderBy('created_at', 'desc')->get();
		}
		return $purchaseOrders;
	}
	public function delete($company, $orderId, $reason, User $loggedInUser, $customerId, $customerType)
	{
		$toEmailIds = [];
		$recipients = [];
		$blanketOrder = BlanketOrder::find($orderId); //Comp Order
		$customer = $blanketOrder->orderable; //main distributor who placed order with company
		$blanketOrderNo = $blanketOrder->order_number;
		$orderAmount = $blanketOrder->amount;
		$purchaseOrders = $blanketOrder->purchase_orders;
		foreach ($purchaseOrders as $purchaseOrder) {
			$purchaseLines = $purchaseOrder->purchase_lines()->get();
			$purchaseLineNo = $purchaseLines[0]->nav_line_no;
			$status = $this->salesOrderService->delete($company, $purchaseOrder->navision_order_number, $purchaseOrder->status, $purchaseLineNo);

			if ($status) {
				$purchaseOrder->status = 'Cancelled';
				$purchaseOrder->cancelation_reason = $reason;
				$purchaseOrder->canceled_by = $loggedInUser->id;
				$purchaseOrder->save();
			}
		}

		$blanketOrder->cancelation_reason = $reason;
		$blanketOrder->canceled_by = $loggedInUser->id;
		$blanketOrder->save();
		if ($blanketOrder->related_po_id) //DUD order
		{
			$relatedPO = PurchaseOrder::find($blanketOrder->related_po_id);
			$notification = new Notification;
			if ($customerType == 'distributors') //order via Distributor
			{
				$cancellationReason = 'Please contact Distributor for cancellation reason.';
				$dudCustomer = $relatedPO->blanket_order->orderable;
				$dudsDistributor = $customer;
			} else //order via SS
			{
				$cancellationReason = 'Please contact Super Stockist for cancellation reason.';
				$superRelatedPO = $relatedPO->blanket_order->relatedOrder;

				if ($superRelatedPO) // 3 level order (SS) 
				{
					$dudCustomer = $superRelatedPO->blanket_order->orderable;
					$dudsDistributor = $dudCustomer->distributor;
				}
			}
			$q = User::where('distributor_id', '=', $dudsDistributor->id);
			$q->whereHas('roles', function ($q1) {
				$q1->where('name', '=', 'distributorAdmin');
			});
			$sender = $q->first();
			$relatedPO->status = 'Cancelled';
			$relatedPO->cancelation_reason = $cancellationReason;
			$relatedPO->canceled_by = $sender->id;
			$relatedPO->save();
			$relatedPOLine = $relatedPO->purchase_lines()->first();
			$relatedPOLine->quantity = 0;
			$relatedPOLine->save();

			$relatedPOsBlanketOrder = BlanketOrder::find($relatedPO->blanket_order_id);
			$notification->text = 'Your order has been partially/completely cancelled ' . $relatedPOsBlanketOrder->order_number . ' . ' . $cancellationReason;
			if ($relatedPOsBlanketOrder->purchase_orders->count() == 1) {
				//	$relatedPOsBlanketOrder->status = 'Cancelled';
				$relatedPOsBlanketOrder->cancelation_reason = $cancellationReason;
				$relatedPOsBlanketOrder->canceled_by = $sender->id;
				$relatedPOsBlanketOrder->save();
			} else {
				//DUD under Dist under Cmp has more than one orders corresponding differrent stock points 
				$all_pos_shipped = true;
				$all_pos_cancelled = true;
				foreach ($relatedPOsBlanketOrder->purchase_orders as $purchase_order) {
					if ($purchase_order->status != 'Shipped' && $purchase_order->status != 'Cancelled') {
						$all_pos_shipped = false;
					}
					if ($purchase_order->status != 'Cancelled') {
						$all_pos_cancelled = false;
					}
				}
				if ($all_pos_cancelled == true) {
					$relatedPOsBlanketOrder->cancelation_reason = $cancellationReason;
					$relatedPOsBlanketOrder->canceled_by = $sender->id;
					$relatedPOsBlanketOrder->save();
				}
			}

			$superRelatedPO = $relatedPO->blanket_order->relatedOrder;
			if ($superRelatedPO) // 3 level order (SS) 
			{
				$superRelatedPO->status = 'Cancelled';
				$superRelatedPO->cancelation_reason = $cancellationReason;
				$superRelatedPO->canceled_by = $sender->id;
				$superRelatedPO->save();
				$superRelatedPOLine = $superRelatedPO->purchase_lines()->first();
				$superRelatedPOLine->quantity = 0;
				$superRelatedPOLine->save();

				$superRelatedBO = BlanketOrder::find($superRelatedPO->blanket_order_id);
				$notification->text = 'Your order has been partially/completely cancelled ' . $superRelatedBO->order_number . ' . ' . $cancellationReason;

				if ($superRelatedBO->purchase_orders->count() == 1) {
					//	$superRelatedBO->status = 'Cancelled';
					$superRelatedBO->cancelation_reason = $cancellationReason;
					$superRelatedBO->canceled_by = $sender->id;
					$superRelatedBO->save();
				} else {
					//DUD under Dist under SS under Cmp has more than one orders corresponding differrent stock points 
					$all_pos_shipped = true;
					$all_pos_cancelled = true;
					foreach ($superRelatedBO->purchase_orders as $purchase_order) {
						if ($purchase_order->status != 'Shipped' && $purchase_order->status != 'Cancelled') {
							$all_pos_shipped = false;
						}
						if ($purchase_order->status != 'Cancelled') {
							$all_pos_cancelled = false;
						}
					}
					if ($all_pos_cancelled == true) {
						$superRelatedBO->cancelation_reason = $cancellationReason;
						$superRelatedBO->canceled_by = $sender->id;
						$superRelatedBO->save();
					}
				}
			}

			//	$dealer = Dealer::find($relatedPOsBlanketOrder->orderable_id);
			$query = User::where('dealer_id', '=', $dudCustomer->id);
			$query->whereHas('roles', function ($q) {
				$q->where('name', '=', 'dealerAdmin');
			});
			$users = $query->get();
			$dud_user = $users->first();
			/*	$notifiable = (object)[
	            "order_number" => $relatedPOsBlanketOrder->order_number,
	            "number" => $dud_user->phone,
	            "customer" => $dealer->name
	        ];
	        SMSNotification::send($notifiable,new PurchaseOrderCancelledNotification($company));//#744 DUD*/
			foreach ($users as $user) {
				if ($user->communication_email)
					$toDealerEmailIds[] = $user->communication_email;
				$dealerRecipients[] = $user;
			} //add end Customer Admin Email Ids

			$queuePrefix = config('brand.queue_prefix');
			$queue = $queuePrefix . '.emails';
			$Qmessage = (new PurchaseOrderDeleteDistributor($relatedPOsBlanketOrder))->onQueue($queue);
			try {
				// if (!empty($toDealerEmailIds))
				// 	Mail::to($toDealerEmailIds)->queue($Qmessage);
			} catch (ConnectionException $e) {
				throw new RedisException;
			}
			foreach ($dealerRecipients as $recipient) {
				//$notification = new Notification;
				$notification->title = 'Purchase Order partially/completely Cancelled!';
				//	$notification->text = 'Distributor has partially/completely cancelled ' .$relatedPOsBlanketOrder->order_number. ' . '. $cancellationReason;
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
		if ($customerType == 'distributors') {
			$query = User::where('distributor_id', '=', $customerId);
			$query->whereHas('roles', function ($q) {
				$q->where('name', '=', 'distributorAdmin');
			});
			$users = $query->get();
			$distributor_user = $users->first();
			/*	$notifiable = (object)[
	            "order_number" => $blanketOrder->order_number,
	            "number" => $distributor_user->phone,
	            "customer" => $customer->name
	        ];
	        SMSNotification::send($notifiable,new PurchaseOrderCancelledNotification($company));//#744 DUC*/
			foreach ($users as $user) {
				if ($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
			}
		} //add Customer Admin Email Ids
		if ($customerType == 'super_stockists') {
			$query = User::where('super_stockist_id', '=', $customerId);
			$query->whereHas('roles', function ($q) {
				$q->where('name', '=', 'superStockistAdmin');
			});
			$users = $query->get();
			$super_stockist_user = $users->first();
			/*	$notifiable = (object)[
	            "order_number" => $blanketOrder->order_number,
	            "number" => $distributor_user->phone,
	            "customer" => $customer->name
	        ];
	        SMSNotification::send($notifiable,new PurchaseOrderCancelledNotification($company));//#744 DUC*/
			foreach ($users as $user) {
				if ($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
			}
		} //add Customer Admin Email Ids
		if ($customerType == 'dealers' && $customer->distributor_id == null) {
			$query = User::where('dealer_id', '=', $customerId);
			$query->whereHas('roles', function ($q) {
				$q->where('name', '=', 'dealerAdmin');
			});
			$users = $query->get();
			$dealer_user = $users->first();
			/*		$notifiable = (object)[
	            "order_number" => $blanketOrder->order_number,
	            "number" => $dealer_user->phone,
	            "customer" => $customer->name
	        ];
	        SMSNotification::send($notifiable,new PurchaseOrderCancelledNotification($company));//#744 DUC*/
			foreach ($users as $user) {
				if ($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
			}
		} //add Customer Admin Email Ids
		$queuePrefix = config('brand.queue_prefix');
		$queue = $queuePrefix . '.emails';
		$Qmessage = (new PurchaseOrderDelete($blanketOrder))->onQueue($queue);
		try {
			// if (!empty($toEmailIds))
			// 	Mail::to($toEmailIds)->queue($Qmessage);
		} catch (ConnectionException $e) {
			throw new RedisException;
		}
		foreach ($recipients as $recipient) {
			$notification = new Notification;
			$notification->title = 'Cancelled Purchase Order';
			$notification->text = 'Administrator Cancelled ' . $blanketOrderNo . '. Reason: ' . $reason;
			$notification->sender_id = $sender->id;
			$notification->recipient_id = $recipient->id;
			$notification->author()->associate($sender);
			$notification->sender()->associate($sender);
			$notification->recipient()->associate($recipient);
			$notification->save();
			event(new Notifications($notification));
		}
		return $blanketOrder;
	}
	public function find($company, $customer, $orderId)
	{
		return $customer->orders()->find($orderId);
	}
	public function findOrder(User $loggedInUser, $orderId)
	{
		$alert = '';
		$blanketOrder = BlanketOrder::find($orderId);
		$orderCompanyId = $blanketOrder->orderable->company->id;
		$orderCustomerId = $blanketOrder->orderable->id;
		$purchaseOrders = $blanketOrder->purchase_orders;
		$orderDistributorId = $blanketOrder->distributor_id;


		foreach ($purchaseOrders as $purchaseOrder) {
			if ($purchaseOrder->distributor_id == null && $purchaseOrder->super_stockist_id == null) {
				list($purchaseOrder, $alert) = $this->update($purchaseOrder->orderable->company, $purchaseOrder->orderable, $purchaseOrder->id);
			} else if ($purchaseOrder->ss_stock_point_id != null || $purchaseOrder->dist_stock_point_id != null) {
				if (strpos(request()->path(), 'ship_invoice') !== false) {
					list($purchaseOrder, $alert) = $this->shipAndInvoice($purchaseOrder->orderable->company, $purchaseOrder->orderable, $purchaseOrder->id);
				}else{
					list($purchaseOrder, $alert) = $this->update($purchaseOrder->orderable->company, $purchaseOrder->orderable, $purchaseOrder->id);
				}				
			} else {
				$relatedBO = BlanketOrder::where('related_po_id', '=', $purchaseOrder->id)->first();
				if ($relatedBO) {
					$relatedPO = $relatedBO->purchase_orders()->first();
					if (!empty($relatedPO->navision_order_number)) {
						list($relatedPO, $alert) = $this->update($relatedPO->orderable->company, $relatedPO->orderable, $relatedPO->id);
					}
				}
				$alert = ''; //how do we handle this at blanket order level check #248
			}
		}
		$blanketOrder = BlanketOrder::find($orderId);

		//$order = $this->update($order->orderable->company, $order->orderable, $orderId);
		/*if($order->distributor_id == null)
		{
			list($purchaseOrder,$alert) = $this->update($order->orderable->company, $order->orderable, $orderId);
		}
		else
		{
			$purchaseOrder = $order;
			$alert = '';
		}*/ #433
		if ($loggedInUser->hasRole('superadmin')) {
			return [$blanketOrder, $alert];
		} else if ($loggedInUser->hasRole('depotManager')) {
			return [$blanketOrder, $alert];
		} else if ($loggedInUser->hasRole('apiUser')) {
			return [$blanketOrder, $alert];
		} else if ($loggedInUser->hasRole('companyAdmin')) {
			$companyId = $loggedInUser->company_id;
			if ($orderCompanyId == $companyId) {
				return [$blanketOrder, $alert];
			}
		} else if ($loggedInUser->hasRole('distributorAdmin')) {
			$distributorId = $loggedInUser->distributor_id;
			if ($orderCustomerId == $distributorId) {
				return [$blanketOrder, $alert];
			}
			if ($orderDistributorId == $distributorId) {
				return [$blanketOrder, $alert];
			}

			if ($blanketOrder->orderable_type === Distributor::class) {
				return [$blanketOrder, $alert];
			}
			if ($blanketOrder->orderable_type === Dealer::class) {
				return [$blanketOrder, $alert];
			}

			if ($blanketOrder->orderable_type === SuperStockist::class) {
				//if($blanketOrder->orderable->id == $superStockistId){
				return [$blanketOrder, $alert];
				//}
			}
		} else if ($loggedInUser->hasRole('superStockistAdmin')) {
			$superStockistId = $loggedInUser->super_stockist_id;
			if ($blanketOrder->orderable->super_stockist_id == $superStockistId) {
				return [$blanketOrder, $alert];
			}

			if ($blanketOrder->orderable_type === Distributor::class) {
				return [$blanketOrder, $alert];
			}
			if ($blanketOrder->orderable_type === Dealer::class) {
				return [$blanketOrder, $alert];
			}

			if ($blanketOrder->orderable_type === SuperStockist::class) {
				//if($blanketOrder->orderable->id == $superStockistId){
				return [$blanketOrder, $alert];
				//}
			}
		} else if ($loggedInUser->hasRole('dealerAdmin')) {
			$dealerId = $loggedInUser->dealer_id;
			if ($orderCustomerId == $dealerId)
				return [$blanketOrder, $alert];
		}
		throw new ViewOrderPermissionDeniedException;
	}
	public function updateShipment($company, $customer, $orderId, array $params)
	{
		$order = $customer->blanketOrders()->find($orderId);
		foreach ($params as $param) {
			$line = $order->purchase_lines()->find($param['id']);
			$line->received_quantity = $line->received_quantity + $param['quantity'];
			$line->save();
			$stock = $customer->stocks()
				->where('product_id', '=', $line->product_id)
				->where('batch_no', '=', $line->batch_no)
				->first();
			if (!$stock) {
				$stock = new Stock;
				$stock->product_id = $line->product_id;
				$stock->batch_no = $line->batch_no;
				$stock->quantity = 0;
				$customer->stocks()->save($stock);
			}
			$stock->quantity = $stock->quantity + $param['quantity'];
			$stock->save();
		}
		$order->load('purchase_lines');
		return $order;
	}
	public function deleteLine($company, $customer_type, $customer, $purchaseOrder, $purchaseLine, User $user)
	{
		$sendEmail = false;
		$toEmailIds = [];
		$recipients = [];
		$sender = $user;
		$data = [];
		$blanketOrder = $purchaseOrder->blanket_order;
		$data['orderNumber'] = $blanketOrder->order_number;
		$data['orderId'] = $blanketOrder->id;

		//$data['productName'] = $purchaseLine->product->name;
		//$data['category'] = $purchaseLine->product->category->code;
		//$data['collection'] = $purchaseLine->product->collection->code;
		//$data['colour'] = $purchaseLine->product->colour->code;
		$data['batch'] = $purchaseLine->batch_no;
		$product = $purchaseLine->product->name;
		$batch_no = $purchaseLine->batch_no;

		$log = new Log;
		$log->action = 'Deleted - Purchase Line';
		$log->author()->associate(Auth::user());
		$log->ip_address = \Request::ip();

		$product_display_name = $this->getProductDisplayName($company, $customer, $purchaseLine->product->category->code, $purchaseLine->product->collection->code, $purchaseLine->product->colour->code);

		$data['product_display_name'] = $product_display_name;


		if (count($purchaseOrder->purchase_lines) == 1) {
			$status = $this->salesOrderService->delete($company, $purchaseOrder->navision_order_number, $purchaseOrder->status, $purchaseLine->nav_line_no);
			if ($status) {
				$purchaseLine->delete();
				$purchaseOrder->delete();
				$sendEmail = true;
				$log->comment = 'Deleted Purchase Order Line for Product: ' . $product_display_name . ' & Batch: ' . $batch_no;
			} else
				throw new DeleteLineException;
		} else {
			$navPurchaseOrder = $this->salesOrderService->deleteSalesLine($company, $purchaseOrder->navision_order_number, $purchaseLine->nav_line_no, $purchaseLine->batch_no);
			if ($navPurchaseOrder) {
				$purchaseLine->delete();
				$purchaseOrder->load('purchase_lines');
				//$purchaseOrder->fresh();
				$purchaseOrder->Amount = $purchaseOrder->purchase_lines->sum('net_amount'); //#741
				$purchaseOrder->save();
				$sendEmail = true;
				$log->comment = 'Deleted Purchase Order Line for Product: ' . $product_display_name . ' & Batch: ' . $batch_no;
				list($purchaseOrder, $alert) = $this->update($company, $customer, $purchaseOrder->id);
			} else
				throw new DeleteLineException;
		}
		//$purchaseOrder = $this->update($company, $customer, $purchaseOrder->id);
		$blanketOrder->logs()->save($log);
		if ($customer_type == 'distributors') {
			$query = User::where('distributor_id', '=', $customer->id);
			$query->whereHas('roles', function ($q) {
				$q->where('name', '=', 'distributorAdmin');
			});
			$users = $query->get();
			$customer_user = $users->first();
			foreach ($users as $user) {
				if ($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
			}
		} //add Customer Admin Email Ids
		if ($customer_type == 'super_stockists') {
			$query = User::where('super_stockist_id', '=', $customer->id);
			$query->whereHas('roles', function ($q) {
				$q->where('name', '=', 'superStockistAdmin');
			});
			$users = $query->get();
			$customer_user = $users->first();
			foreach ($users as $user) {
				if ($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
			}
		} //add Customer Admin Email Ids
		if ($customer_type == 'dealers') {
			$query = User::where('dealer_id', '=', $customer->id);
			$query->whereHas('roles', function ($q) {
				$q->where('name', '=', 'dealerAdmin');
			});
			$users = $query->get();
			$customer_user = $users->first();
			foreach ($users as $user) {
				if ($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
			}
		} //add Customer Admin Email Ids
		$notifiable = (object)[
			"order_number" => $blanketOrder->order_number,
			"number" => $customer_user->phone,
			"customer" => $customer->name
		];
		SMSNotification::send($notifiable, new PurchaseOrderUpdatedNotification($company)); //#595 Notify Customer

		if ($sendEmail) {
			$queuePrefix = config('brand.queue_prefix');
			$queue = $queuePrefix . '.emails';
			$Qmessage = (new PurchaseLineDelete($data))->onQueue($queue);
			try {
				// if (!empty($toEmailIds))
				// 	Mail::to($toEmailIds)->queue($Qmessage);
			} catch (ConnectionException $e) {
				throw new RedisException;
			}
			foreach ($recipients as $recipient) {
				$notification = new Notification;
				$notification->title = 'Item from ' . $blanketOrder->order_number . ' deleted';
				$notification->text = 'Deleted Item : ' . $product_display_name . ' against Batch : ' . $batch_no;
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
	public function updateLine($company, $customer_type, $customer,  $purchaseOrder, $purchaseLine, array $params,  User $user)
	{
		$blanketOrder = $purchaseOrder->blanket_order;
		$sendUnitPriceEmail = false;
		$sendQuantityEmail = false;
		$sendUnitPriceDiscountEmail = false;
		$currentDiscount = $purchaseLine->discount;
		$currentUnitPrice = $purchaseLine->unit_price;
		$current_discounted_unit_price = round($currentUnitPrice - ($currentUnitPrice * $currentDiscount / 100), 2);
		$currentQuantity = $purchaseLine->quantity;
		$newDiscountedUnitPrice = $current_discounted_unit_price;
		$log = new Log;
		$log->action = 'Updated - Purchase Line';
		$log->author()->associate($user);
		$log->ip_address = \Request::ip();
		$newDiscount = $params['discount'];
		$newQuantity = $params['quantity'];
		if ($params['discount'] != $currentDiscount && $params['quantity'] != $currentQuantity) {
			$newDiscountedUnitPrice = round($currentUnitPrice - ($currentUnitPrice * $newDiscount / 100), 2);
			$newQuantity = $params['quantity'];
			$navPurchaseOrder = $this->salesOrderService->updateSalesLine($company, $purchaseOrder->nav_key, $purchaseOrder->navision_order_number, $purchaseLine->nav_line_no, $newQuantity, $newDiscountedUnitPrice, $purchaseLine->batch_no);
			if ($navPurchaseOrder) {
				$purchaseLine->discount = $newDiscount;
				$purchaseLine->amount = $newDiscountedUnitPrice * $newQuantity;
				$purchaseLine->quantity = $newQuantity;
				$purchaseLine->tax_amount = $purchaseLine->amount * $purchaseLine->tax_percent / 100; //#741
				$purchaseLine->net_amount = $purchaseLine->amount + $purchaseLine->tax_amount; //#741
				$purchaseLine->save();
				$sendUnitPriceDiscountEmail = true;
				$log->comment = 'Changed Discount from ' . $currentDiscount . ' to ' . $newDiscount . ' & Quantity from ' . $currentQuantity . ' to ' . $newQuantity;

				if ($blanketOrder->related_po_id) {
					$relatedPO = $blanketOrder->relatedOrder;
					if ($relatedPO) {
						$relatedPOLine = $relatedPO->purchase_lines()->where('nav_line_no', '=', $purchaseLine->nav_line_no)->first();
						$relatedPOLine_discounted_unit_price = round($relatedPOLine->unit_price - ($relatedPOLine->unit_price * $relatedPOLine->discount / 100), 2);
						$relatedPOLine->quantity = $newQuantity;
						$relatedPOLine->amount = $relatedPOLine_discounted_unit_price * $newQuantity;
						$relatedPOLine->tax_amount = $relatedPOLine->amount * $relatedPOLine->tax_percent / 100; //#741
						$relatedPOLine->net_amount = $relatedPOLine->amount + $relatedPOLine->tax_amount; //#741
						$relatedPOLine->save();
						$relatedPO->Amount = $relatedPO->purchase_lines->sum('net_amount'); //#741
						$relatedPO->save();
						if ($customer_type == 'distributors') {
							$dudCustomer = $relatedPO->blanket_order->orderable;
							$dudsDistributor = $customer;
						}

						$superRelatedPO = $relatedPO->blanket_order->relatedOrder;
						if ($superRelatedPO) // 3 level order (SS) 
						{
							$superRelatedPOLine = $superRelatedPO->purchase_lines()->where('nav_line_no', '=', $purchaseLine->nav_line_no)->first();
							$superRelatedPOLine_discounted_unit_price = round($superRelatedPOLine->unit_price - ($superRelatedPOLine->unit_price * $superRelatedPOLine->discount / 100), 2);
							$superRelatedPOLine->quantity = $newQuantity;
							$superRelatedPOLine->amount = $superRelatedPOLine_discounted_unit_price * $newQuantity;
							$superRelatedPOLine->tax_amount = $superRelatedPOLine->amount * $superRelatedPOLine->tax_percent / 100; //#741
							$superRelatedPOLine->net_amount = $superRelatedPOLine->amount + $superRelatedPOLine->tax_amount; //#741
							$superRelatedPOLine->save();
							$superRelatedPO->Amount = $superRelatedPO->purchase_lines->sum('net_amount'); //#741
							$superRelatedPO->save();
							$dudCustomer = $superRelatedPO->blanket_order->orderable;
							$dudsDistributor = $dudCustomer->distributor;
						}

						$query = User::where('dealer_id', '=', $dudCustomer->id);
						$query->whereHas('roles', function ($q) {
							$q->where('name', '=', 'dealerAdmin');
						});
						$users = $query->get();
						$dud_user = $users->first();
						foreach ($users as $user) {
							if ($user->communication_email)
								$toDealerEmailIds[] = $user->communication_email;
							$dealerRecipients[] = $user;
						}
						$query = User::where('distributor_id', '=', $dudsDistributor->id);
						$query->whereHas('roles', function ($q) {
							$q->where('name', '=', 'distributorAdmin');
						});
						/*	$notifiable = (object)[
				            "order_number" => $relatedPO->blanket_order->order_number,
				            "number" => $dud_user->phone,
				            "customer" => $dudCustomer->name
				        ];
				        SMSNotification::send($notifiable,new PurchaseOrderUpdatedNotification($company));//#595 Notify DUD Customer when B2B order is updated by Super/Company Admin*/

						$product_display_name = $this->getProductDisplayName($company, $dudCustomer, $relatedPOLine->product->category->code, $relatedPOLine->product->collection->code, $relatedPOLine->product->colour->code);
						$sender = $query->first();
						$data = [];
						$data['orderNumber'] = $relatedPO->blanket_order->order_number;
						$data['quantity'] = $currentQuantity;
						$data['current_discounted_unit_price'] = $relatedPOLine_discounted_unit_price;
						$data['new_discounted_unit_price'] = $relatedPOLine_discounted_unit_price;
						$data['product_display_name'] = $product_display_name;
						$queuePrefix = config('brand.queue_prefix');
						$queue = $queuePrefix . '.emails';
						$Qmessage = (new PurchaseLineUpdateDud($relatedPOLine, $data))->onQueue($queue);
						try {
							if (!empty($toDealerEmailIds))
								// Mail::to($toDealerEmailIds)->queue($Qmessage);
								foreach ($dealerRecipients as $recipient) {
									$notification = new Notification;
									$notification->sender_id = $sender->id;
									$notification->recipient_id = $recipient->id;
									$notification->author()->associate($sender);
									$notification->sender()->associate($sender);
									$notification->recipient()->associate($recipient);
									$notification->title = 'Updated Quantity for ' . $relatedPO->blanket_order->order_number . ' item';
									$notification->text = 'Updated Quantity from ' . $currentQuantity . ' to ' . $newQuantity . ' for Item : ' . $product_display_name . ', against Batch : ' . $relatedPOLine->batch_no;
									$notification->save();
									event(new Notifications($notification));
								}
						} catch (ConnectionException $e) {
							throw new RedisException;
						}
					}
				}
			}
		} else if ($params['discount'] != $currentDiscount) {
			$newDiscountedUnitPrice = round($currentUnitPrice - ($currentUnitPrice * $newDiscount / 100), 2);
			$navPurchaseOrder = $this->salesOrderService->updateSalesLine($company, $purchaseOrder->nav_key, $purchaseOrder->navision_order_number, $purchaseLine->nav_line_no, $params['quantity'], $newDiscountedUnitPrice, $purchaseLine->batch_no);
			if ($navPurchaseOrder) {
				$purchaseLine->discount = $newDiscount;
				$purchaseLine->amount = $newDiscountedUnitPrice * $params['quantity'];
				$purchaseLine->tax_amount = $purchaseLine->amount * $purchaseLine->tax_percent / 100; //#741
				$purchaseLine->net_amount = $purchaseLine->amount + $purchaseLine->tax_amount; //#741
				$purchaseLine->save();
				$sendUnitPriceEmail = true;
				$log->comment = 'Changed Discount from ' . $currentDiscount . ' to ' . $newDiscount;
			}
		} else if ($params['quantity'] != $currentQuantity) {
			$navPurchaseOrder = $this->salesOrderService->updateSalesLine($company, $purchaseOrder->nav_key, $purchaseOrder->navision_order_number, $purchaseLine->nav_line_no, $newQuantity, $current_discounted_unit_price, $purchaseLine->batch_no);
			if ($navPurchaseOrder) {
				$purchaseLine->quantity = $newQuantity;
				$purchaseLine->amount = $current_discounted_unit_price * $newQuantity;
				$purchaseLine->tax_amount = $purchaseLine->amount * $purchaseLine->tax_percent / 100; //#741
				$purchaseLine->net_amount = $purchaseLine->amount + $purchaseLine->tax_amount; //#741
				$purchaseLine->save();
				$sendQuantityEmail = true;
				$log->comment = 'Changed Quantity from ' . $currentQuantity . ' to ' . $newQuantity;
				$blanketOrder = $purchaseOrder->blanket_order;
				if ($blanketOrder->related_po_id) {
					$relatedPO = $blanketOrder->relatedOrder;
					if ($relatedPO) // 2 level order
					{
						$relatedPOLine = $relatedPO->purchase_lines()->where('nav_line_no', '=', $purchaseLine->nav_line_no)->first();
						$relatedPOLine_discounted_unit_price = round($relatedPOLine->unit_price - ($relatedPOLine->unit_price * $relatedPOLine->discount / 100), 2);
						$relatedPOLine->quantity = $newQuantity;
						$relatedPOLine->amount = $relatedPOLine_discounted_unit_price * $newQuantity;
						$relatedPOLine->tax_amount = $relatedPOLine->amount * $relatedPOLine->tax_percent / 100; //#741
						$relatedPOLine->net_amount = $relatedPOLine->amount + $relatedPOLine->tax_amount; //#741
						$relatedPOLine->save();
						$relatedPO->Amount = $relatedPO->purchase_lines->sum('net_amount'); //#741
						$relatedPO->save();
						if ($customer_type == 'distributors') {
							$dudCustomer = $relatedPO->blanket_order->orderable;
							$dudsDistributor = $customer;
						}
						$superRelatedPO = $relatedPO->blanket_order->relatedOrder;
						if ($superRelatedPO) // 3 level order (SS) 
						{
							$superRelatedPOLine = $superRelatedPO->purchase_lines()->where('nav_line_no', '=', $purchaseLine->nav_line_no)->first();
							$superRelatedPOLine_discounted_unit_price = round($superRelatedPOLine->unit_price - ($superRelatedPOLine->unit_price * $superRelatedPOLine->discount / 100), 2);
							$superRelatedPOLine->quantity = $newQuantity;
							$superRelatedPOLine->amount = $superRelatedPOLine_discounted_unit_price * $newQuantity;
							$superRelatedPOLine->tax_amount = $superRelatedPOLine->amount * $superRelatedPOLine->tax_percent / 100; //#741
							$superRelatedPOLine->net_amount = $superRelatedPOLine->amount + $superRelatedPOLine->tax_amount; //#741
							$superRelatedPOLine->save();
							$superRelatedPO->Amount = $superRelatedPO->purchase_lines->sum('net_amount'); //#741
							$superRelatedPO->save();
							$dudCustomer = $superRelatedPO->blanket_order->orderable;
							$dudsDistributor = $dudCustomer->distributor;
						}
						$query = User::where('dealer_id', '=', $dudCustomer->id);
						$query->whereHas('roles', function ($q) {
							$q->where('name', '=', 'dealerAdmin');
						});
						$users = $query->get();
						$dud_user = $users->first();
						foreach ($users as $user) {
							if ($user->communication_email)
								$toDealerEmailIds[] = $user->communication_email;
							$dealerRecipients[] = $user;
						}
						$query = User::where('distributor_id', '=', $dudsDistributor->id);
						$query->whereHas('roles', function ($q) {
							$q->where('name', '=', 'distributorAdmin');
						});
						/*$notifiable = (object)[
				            "order_number" => $relatedPO->blanket_order->order_number,
				            "number" => $dud_user->phone,
				            "customer" => $dudCustomer->name
				        ];
			        	SMSNotification::send($notifiable,new PurchaseOrderUpdatedNotification($company));//#595 Notify DUD Customer when B2B order is updated by Super/Company Admin*/

						$product_display_name = $this->getProductDisplayName($company, $dudCustomer, $relatedPOLine->product->category->code, $relatedPOLine->product->collection->code, $relatedPOLine->product->colour->code);
						$sender = $query->first();
						$data = [];
						$data['orderNumber'] = $relatedPO->blanket_order->order_number;
						$data['quantity'] = $currentQuantity;
						$data['current_discounted_unit_price'] = $relatedPOLine_discounted_unit_price;
						$data['new_discounted_unit_price'] = $relatedPOLine_discounted_unit_price;
						$data['product_display_name'] = $product_display_name;
						$queuePrefix = config('brand.queue_prefix');
						$queue = $queuePrefix . '.emails';
						$Qmessage = (new PurchaseLineUpdateDud($relatedPOLine, $data))->onQueue($queue);
						try {
							if (!empty($toDealerEmailIds))
								// Mail::to($toDealerEmailIds)->queue($Qmessage);
								foreach ($dealerRecipients as $recipient) {
									$notification = new Notification;
									$notification->sender_id = $sender->id;
									$notification->recipient_id = $recipient->id;
									$notification->author()->associate($sender);
									$notification->sender()->associate($sender);
									$notification->recipient()->associate($recipient);
									$notification->title = 'Updated Quantity for ' . $relatedPO->blanket_order->order_number . ' item';
									$notification->text = 'Updated Quantity from ' . $currentQuantity . ' to ' . $newQuantity . ' for Item : ' . $product_display_name . ', against Batch : ' . $relatedPOLine->batch_no;
									$notification->save();
									event(new Notifications($notification));
								}
						} catch (ConnectionException $e) {
							throw new RedisException;
						}
					}
				}
			}
		}
		$purchaseOrder->Amount = $purchaseOrder->purchase_lines->sum('net_amount'); //#741
		$purchaseOrder->save(); //#741
		list($purchaseOrder, $alert) = $this->update($company, $customer, $purchaseOrder->id);
		$purchaseLine->logs()->save($log);
		$toEmailIds = [];
		$recipients = [];
		$sender = $user;
		//Direct Comp Customer Notifications
		if ($customer_type == 'distributors') {
			$query = User::where('distributor_id', '=', $customer->id);
			$query->whereHas('roles', function ($q) {
				$q->where('name', '=', 'distributorAdmin');
			});
			$users = $query->get();
			$customer_user = $users->first();
			foreach ($users as $user) {
				if ($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
			}
		} //add Customer Admin Email Ids
		if ($customer_type == 'super_stockists') {
			$query = User::where('super_stockist_id', '=', $customer->id);
			$query->whereHas('roles', function ($q) {
				$q->where('name', '=', 'superStockistAdmin');
			});
			$users = $query->get();
			$customer_user = $users->first();
			foreach ($users as $user) {
				if ($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
			}
		} //add Customer Admin Email Ids
		if ($customer_type == 'dealers') {
			$query = User::where('dealer_id', '=', $customer->id);
			$query->whereHas('roles', function ($q) {
				$q->where('name', '=', 'dealerAdmin');
			});
			$users = $query->get();
			$customer_user = $users->first();
			foreach ($users as $user) {
				if ($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
			}
		} //add Customer Admin Email Ids
		/*	$notifiable = (object)[
            "order_number" => $blanketOrder->order_number,
            "number" => $customer_user->phone,
            "customer" => $customer->name
        ];
        SMSNotification::send($notifiable,new PurchaseOrderUpdatedNotification($company));//#595 Notify Customer*/
		$product_display_name = $this->getProductDisplayName($company, $customer, $purchaseLine->product->category->code, $purchaseLine->product->collection->code, $purchaseLine->product->colour->code);
		if ($sendUnitPriceDiscountEmail || $sendQuantityEmail || $sendUnitPriceEmail) {
			$data = [];
			$data['orderNumber'] = $blanketOrder->order_number;
			$data['quantity'] = $currentQuantity;
			$data['current_discounted_unit_price'] = $current_discounted_unit_price;
			$data['new_discounted_unit_price'] = $newDiscountedUnitPrice;
			$data['product_display_name'] = $product_display_name;
			$queuePrefix = config('brand.queue_prefix');
			$queue = $queuePrefix . '.emails';
			$Qmessage = (new PurchaseLineUpdate($purchaseLine, $data))->onQueue($queue);
			try {
				// if (!empty($toEmailIds))
				// 	Mail::to($toEmailIds)->queue($Qmessage);
				foreach ($recipients as $recipient) {
					$notification = new Notification;
					if ($sendUnitPriceDiscountEmail) {
						$notification->title = 'Updated Discount and Quantity for ' . $blanketOrder->order_number . ' item';
						$notification->text = 'Updated Discount from ' . $currentDiscount . '% to ' . $newDiscount . '% and Quantity from ' . $currentQuantity . ' to ' . $newQuantity . ' for Item : ' . $product_display_name . ', against Batch : ' . $purchaseLine->batch_no;
					}
					if ($sendQuantityEmail) {
						$notification->title = 'Updated Quantity for ' . $blanketOrder->order_number . ' item';
						$notification->text = 'Updated Quantity from ' . $currentQuantity . ' to ' . $newQuantity . ' for Item : ' . $product_display_name . ', against Batch : ' . $purchaseLine->batch_no;
					}
					if ($sendUnitPriceEmail) {
						$notification->title = 'Updated Discount for ' . $blanketOrder->order_number . ' item';
						$notification->text = 'Updated Discount from ' . $currentDiscount . '% to ' . $newDiscount . '% for Item : ' . $product_display_name . ', against Batch : ' . $purchaseLine->batch_no;
					}
					$notification->sender_id = $sender->id;
					$notification->recipient_id = $recipient->id;
					$notification->author()->associate($sender);
					$notification->sender()->associate($sender);
					$notification->recipient()->associate($recipient);
					$notification->save();
					event(new Notifications($notification));
				}
			} catch (ConnectionException $e) {
				throw new RedisException;
			}
		}
		//return $purchaseOrder;
		return [$purchaseOrder, $alert];
	}
	public function getProductDisplayName($company, $customer, $category_code, $collection_code = null, $colour_code = null)
	{
		$activeCategory = $customer->activeProductCategories()->where('category_code', '=', $category_code)->first();
		if ($collection_code != null) {
			$activeCollection = $customer->activeProductCollections()->where('collection_code', '=', $collection_code)->first();
		}
		if ($colour_code != null) {
			$activeColour = $customer->activeProductColours()->where('colour_code', '=', $colour_code)->first();
			if (!$activeColour)
				$activeColour = $company->activeProductColours()->where('colour_code', '=', $colour_code)->first();
		}
		$product_display_name = $activeCategory->category_display . ' >> ' . $activeCollection->collection_display . ' >> ' . $activeColour->colour_display;
		return $product_display_name;
	}

	protected function calculateOrderAmount(Company $company, array $purchase_lines, bool $online = false)
	{
		$amount = 0;
		$taxes = [];
		foreach ($purchase_lines as $purchase_line) {
			$tax = 0;
			if (!array_has($taxes, strval($purchase_line['product_id']))) {
				$product = Product::find($purchase_line['product_id']);
				$taxRecord = $company->region->taxes()->where('category_code', '=', $product->category->code)->where('collection_code', '=', $product->collection->code)->first();
				if (!$taxRecord) {
					$taxRecord = $company->region->taxes()->where('category_code', '=', $product->category->code)->first();
				}
				if ($taxRecord) {
					$tax = $taxRecord->tax_percent;
				}
			} else {
				$tax = array_get($taxes, strval($purchase_line['product_id']));
			}
			$discount = $purchase_line['discount'];
			if ($online) {
				$discount += $purchase_line['onlineDiscount'];
			}
			$unitPrice = $purchase_line['unit_price'];
			$quantity = $purchase_line['quantity'];
			$discountedPrice = $unitPrice - ($unitPrice * $discount / 100);
			$tax = $discountedPrice * $tax / 100;

			$amount += ($discountedPrice + $tax);
		}
		return $amount;
	}

	public function startOnlinePaymentOrder(User $user, Company $company, $customer, array $params)
	{
		$collection = collect($params['purchase_lines']);
		$groupedByStockPoint = $collection->groupBy('stock_point_id');
		/**
		 * 1. create transaction object
		 */
		$transaction = new Transaction;
		$transaction->UUID = Uuid::uuid4();
		$transaction->status = 'pending';
		$transaction->company()->associate($company);
		$transaction->author()->associate($user);
		$customer->transactions()->save($transaction);
		//$transaction->save();
		/**
		 * 2. move items to blocked and set transactionid
		 */
		$purchase_lines = array_get($params, 'purchase_lines');
		$blockedItems = collect([]);
		if (array_get($params, 'order_from') == 'cart') {
			foreach ($purchase_lines as $purchase_line) {
				$UUId = Uuid::uuid4();
				$item = new BlockedItem();
				$cartItem = $customer->cartItems()->find($purchase_line['item_id']);
				$item = new BlockedItem;
				$item->product_id = $cartItem->product_id; //['product_id'];
				$item->batch_no = $cartItem->batch_no;
				$item->order_quantity = $cartItem->order_quantity;
				$item->rate_type = $cartItem->rate_type;
				$item->unit_price = $cartItem->unit_price;
				$item->discount = $cartItem->discount;
				$item->onlineDiscount = $cartItem->onlineDiscount;
				$item->stock_point_id = $cartItem->stock_point_id;
				$item->UUId = $UUId;
				if (array_has($cartItem, 'distributor_id') && $cartItem['distributor_id'] != null)
					$item->distributor_id = $cartItem->distributor_id;
				$item->expires_at = Carbon::now()->addSeconds(config('rosetta.blocked_duration'));
				$item->cool_down_start_time = Carbon::now()->addSeconds(config('rosetta.cool_down_start_time')); //#820
				$item->cool_down_end_time = Carbon::now()->addSeconds(config('rosetta.cool_down_end_time')); //#820
				$item->company()->associate($company);
				$item->transaction()->associate($transaction);
				$customer->blockedItems()->save($item);
				$blockedItems->push($item);
				$cartItem->delete();

				/*#672 #639*/
				if ($item->distributor_id == null) //if co-distributor stock is blocked no need to block on other Tenants cause Co-distributors will not be on different Tenants
				{
					$current_tenant = $customer->company->getSettingsForType('current_tenant')->get('tenant_code');
					$location_code = StockPoint::where('id', '=', $item->stock_point_id)->first()->code;
					$product = Product::find($item->product_id);
					$depotItem = [];
					$depotItem['batch_no'] = $item->batch_no;
					$depotItem['order_quantity'] = $item->order_quantity;
					$depotItem['rate_type'] = $item->rate_type;
					$depotItem['unit_price'] = $item->unit_price;
					$depotItem['discount'] = $item->discount;
					$depotItem['expires_at'] = $item->expires_at;
					$depotItem['cool_down_start_time'] = $item->cool_down_start_time; //#820
					$depotItem['cool_down_end_time'] = $item->cool_down_end_time; //#820
					$depotItem['product_nav_id'] = $product->nav_id; //product_id (row_id) may be different on different Tenants based on availability of Products on each Tenant so pass nav_id
					$depotItem['stock_point_code'] = $location_code;
					$depotItem['company_name'] = $customer->company->name;
					$depotItem['comment'] = 'Blocked on ' . $current_tenant . ' by Customer : ' . $customer->name . ' (' . $customer->nav_id . ')';
					$depotItem['distributor_id'] = null; //Disributor on one Tenant will/might not be present on other Tenants
					$comp = $customer->company;
					$depotItem['UUId'] = $item->UUId;
					$tenants = $customer->company->getSettingsForType('tenants')->get('tenant_codes'); //
					if ($tenants != null)
						$this->depotService->block($depotItem, $comp);
				}
			}
		} else {
			/**
			 * get all blocked items and update transaction id
			 */
			foreach ($purchase_lines as $purchase_line) {
				$item = BlockedItem::find($purchase_line['item_id']);
				$item->transaction()->associate($transaction);
				$item->save();
				$blockedItems->push($item);
			}
		}
		if (array_has($params, 'onlineAmount')) {
			$amount = (float)array_get($params, 'onlineAmount');
		} else {
			$amount = $this->calculateOrderAmount($company, $params['purchase_lines'], array_get(
				$params,
				'online',
				false
			));
		}
		$amount = round($amount, 2);
		$createResponse = $this->paymentGatewayService->initiate($company, $customer, $amount, $transaction->UUID);
		$transaction->gateway = $createResponse->get('gateway');
		$transaction->external_reference = $createResponse->get('ref');
		$transaction->save();


		$settingsArray = [];

		$settingsArray[] =  new Setting([
			'key' => 'shipping_method',
			'value' => $params['shipping_method'],
			'type' => 'shipping_method'
		]);
		if (array_has($params, 'special_request') && $params['special_request']) {
			$settingsArray[] =  new Setting([
				'key' => 'special_request',
				'value' => $params['special_request'],
				'type' => 'special_request'
			]);
		}

		$settingsArray[] =  new Setting([
			'key' => 'shipping_address.code',
			'value' => $params['shipping_address']['code'],
			'type' => 'shipping_address'
		]);

		$settingsArray[] =  new Setting([
			'key' => 'shipping_address.address',
			'value' => $params['shipping_address']['address'],
			'type' => 'shipping_address'
		]);

		$settingsArray[] =  new Setting([
			'key' => 'shipping_address.address_2',
			'value' => $params['shipping_address']['address_2'],
			'type' => 'shipping_address'
		]);

		$settingsArray[] =  new Setting([
			'key' => 'shipping_address.post_code',
			'value' => $params['shipping_address']['post_code'],
			'type' => 'shipping_address'
		]);

		$settingsArray[] =  new Setting([
			'key' => 'shipping_address.city',
			'value' => $params['shipping_address']['city'],
			'type' => 'shipping_address'
		]);

		$settingsArray[] =  new Setting([
			'key' => 'shipping_address.country_region_code',
			'value' => $params['shipping_address']['country_region_code'],
			'type' => 'shipping_address'
		]);

		$settingsArray[] =  new Setting([
			'key' => 'shipping_address.phone_no',
			'value' => $params['shipping_address']['phone_no'],
			'type' => 'shipping_address'
		]);

		$settingsArray[] =  new Setting([
			'key' => 'shipping_address.name',
			'value' => $params['shipping_address']['name'],
			'type' => 'shipping_address'
		]);

		$settingsArray[] =  new Setting([
			'key' => 'shipping_address.contact',
			'value' => $params['shipping_address']['contact'] ?? '',
			'type' => 'shipping_address'
		]);

		$settingsArray[] =  new Setting([
			'key' => 'shipping_address.state',
			'value' => $params['shipping_address']['state'],
			'type' => 'shipping_address'
		]);
		/*	$settingsArray[] =  new Setting([
            'key' => 'amount',
            'value' => $amount,
            'type' => 'order'
		]);
		*/



		$transaction->settings()->saveMany($settingsArray);


		return $createResponse;
	}

	public function calculatePayment($customer, float $amount)
	{
		$customerCard = $this->customerService->get($customer->company, $customer->nav_id);
		$balance = (float)$customerCard->getBalance_LCY();
		if ($balance > 0) //ROS doesn't owe customer so take entire order amount from customer
		{
			return [
				'paymentMode' => 'online',
				'orderAmount' => $amount,
				'online' => [
					'amount' => $amount
				],
			];
		}

		$balance = $balance * -1;
		if ($balance < $amount) //ROS owes customer but less tan order oamount so deduct the amount and take only remaining amount from Customer
		{
			return [
				'paymentMode' => 'online',
				'rosettaBalance' => $balance,
				'orderAmount' => $amount,
				'balance' => [
					'amount'  => (float)$balance,
				],
				'online' => [
					'amount' => (float)($amount - $balance)
				]
			];
		}
		return [
			'paymentMode' => 'offline',
			'orderAmount' => $amount,
			'rosettaBalance' => $balance,
			'balance' => [
				'amount' => (float)$amount
			]
		]; //ROS owes customer more tann order amount so don't take payment from custoomer
	}

	public function createSSOrder(Company $company, $customer, array $params, User $user, $distributor_lines, $blanketOrder, $stockPointId)
	{
		$stockPoint = SuperStockist::find($stockPointId);
		$message = null;
		$soAmount = 0;
		$recipients = [];
		$sender = $user;
		$purchaseOrder = new PurchaseOrder;
		//$purchaseOrder->order_number = "PO-".substr(strrev(time()),0,8);
		$purchaseOrder->shipping_method = $params['shipping_method'];
		$purchaseOrder->special_request = $params['special_request'];
		$UUId = Uuid::uuid4();
		$purchaseOrder->UUID = $UUId;
		$purchaseOrder->nav_key = $UUId;
		$validated = $this->salesOrderService->validatePurchaseLines($company, $distributor_lines->all(), $stockPoint, $params['order_from']);
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
			"special_request" => $params['special_request'],
			"shipping_address" => $params['shipping_address'],
			"order_from" => $params['order_from'],
			"purchase_lines" => $validLines,
		];
		$validLinesCollection = collect($validLines);
		$creditLimit = $customer->credit_limit;
		$balanceDue = $customer->overdue_balance;

		if ($soAmount > ($creditLimit - $balanceDue)) {
			$message = "Your order cannot be processed at this time due to negative credit limit. Please contact your Relationship Manager for further actions.";
			//return [null, $validated['invalid'], $message];
		}
		if ($soAmount <= $creditLimit && $balanceDue > 0) {
			$message = "Your order cannot be processed at this time due to overdue payments. Please contact your Relationship Manager for further actions.";
			//return [null, $validated['invalid'], $message];
		}
		$message = '';
		$purchaseOrder->status = 'Approved';
		$purchaseOrder->amount = $soAmount;
		$purchaseOrder->company_id = $company->id;
		if ($stockPointId) {
			$purchaseOrder->super_stockist_id = $stockPointId;
			$purchaseOrder->ss_stock_point_id = $stockPointId;
		}
		$purchaseOrder->navision_order_number = '';
		if (array_has($params, 'end_customer_id'))
			$purchaseOrder->end_customer_id = $params['end_customer_id'];
		if (array_has($params, 'installer_id'))
			$purchaseOrder->engineer_id = $params['installer_id'];

		$purchaseOrder->blanket_order_id = $blanketOrder->id;
		$customer->orders()->save($purchaseOrder);
		$blanketOrder->super_stockist_id = $stockPointId;
		$blanketOrder->save();
		foreach ($validLines as $purchaseLine) {
			$purchaseOrderLine = new PurchaseLine;
			$purchaseOrderLine->product_id = $purchaseLine['product_id'];
			$purchaseOrderLine->nav_line_no = $purchaseLine['line_no'];
			$purchaseOrderLine->discount = $purchaseLine['discount'];
			$purchaseOrderLine->unit_price = $purchaseLine['unit_price'];
			$purchaseOrderLine->quantity = $purchaseLine['quantity'];
			$purchaseOrderLine->rate_type = $purchaseLine['rate_type'];
			$purchaseOrderLine->amount = ($purchaseLine['unit_price'] - ($purchaseLine['unit_price'] * ($purchaseLine['discount'] / 100))) * round($purchaseLine['quantity'], 2);
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


		if (!empty($customer->distributor)) {
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

			$purchaseOrder->blanket_order_id = $distBlanketOrder->id; //SS BO Id on Distributor to SS's PO
			$purchaseOrder->save();
			$customer->distributor->blanketOrders()->save($distBlanketOrder);
			if ($distBlanketOrder) {
				$distPurchaseOrder = new PurchaseOrder;
				$distPurchaseOrder->shipping_method = $params['shipping_method'];
				$distPurchaseOrder->special_request = $params['special_request'];
				$UUId = Uuid::uuid4();
				$distPurchaseOrder->UUID = $UUId;
				$distPurchaseOrder->nav_key = $UUId;
				$distPurchaseOrder->status = 'Approved';
				$distPurchaseOrder->amount = $soAmount;
				$distPurchaseOrder->company_id = $company->id;
				$distPurchaseOrder->distributor_id = $customer->distributor_id;
				$distPurchaseOrder->navision_order_number = '';
				if (array_has($params, 'end_customer_id'))
					$distPurchaseOrder->end_customer_id = $params['end_customer_id'];
				if (array_has($params, 'installer_id'))
					$distPurchaseOrder->engineer_id = $params['installer_id'];
				$distPurchaseOrder->blanket_order_id = $blanketOrder->id;
				if ($stockPointId) {
					$distPurchaseOrder->ss_stock_point_id = $stockPointId;
				}
				$customer->distributor->orders()->save($distPurchaseOrder);
				foreach ($validLines as $purchaseLine) {
					$purchaseOrderLine = new PurchaseLine;
					$purchaseOrderLine->product_id = $purchaseLine['product_id'];
					$purchaseOrderLine->nav_line_no = $purchaseLine['line_no'];
					$purchaseOrderLine->discount = $purchaseLine['discount'];
					$purchaseOrderLine->unit_price = $purchaseLine['unit_price'];
					$purchaseOrderLine->quantity = $purchaseLine['quantity'];
					$purchaseOrderLine->rate_type = $purchaseLine['rate_type'];
					$purchaseOrderLine->amount = ($purchaseLine['unit_price'] - ($purchaseLine['unit_price'] * ($purchaseLine['discount'] / 100))) * round($purchaseLine['quantity'], 2);
					//$purchaseOrderLine->tax_type_code = $purchaseLine['tax_type_code'];
					//$purchaseOrderLine->tax_percent = $purchaseLine['tax_percent'];
					$purchaseOrderLine->tax_amount = $purchaseOrderLine->amount * $purchaseOrderLine->tax_percent / 100;
					$purchaseOrderLine->net_amount = $purchaseOrderLine->tax_amount + $purchaseOrderLine->amount;
					$purchaseOrderLine->batch_no = $purchaseLine['batch_no'];
					$distPurchaseOrder->purchase_lines()->save($purchaseOrderLine);
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

				//$purchase_lines = $validated['valid'];
				foreach ($lines as $line) {
					if ($params['order_from'] == "cart") {
						$item = CartItem::find($line['item_id']);
						$item->delete();
					} else if ($params['order_from'] == "blocked") {
						$item = BlockedItem::find($line['item_id']);
						$item->delete();
					}
				}
				$distBlanketOrder->related_po_id = $distPurchaseOrder->id; //DUD PO Id on Dist BO
				$distBlanketOrder->save();
				//return [$purchaseOrder, $validated['invalid'], $message];
			}
		}
		$customer->orders()->save($purchaseOrder);
		//$purchase_lines = $validated['valid'];
		foreach ($lines as $line) {
			if ($params['order_from'] == "cart") {
				$item = CartItem::find($line['item_id']);
				($item) ? $item->delete() : '';
			} else if ($params['order_from'] == "blocked") {
				$item = BlockedItem::find($line['item_id']);
				($item) ? $item->delete() : '';
			}
			$currentStock = $stockPoint->stocks->where('product_id', '=', $line['product_id'])
				->where('batch_no', '=', $line['batch_no'])->first();
			$newStock = $currentStock->quantity - $line['quantity'];
			$currentStock->quantity = $newStock;
			$currentStock->save();
			//adjust Distributor Stock
		}
		return [$purchaseOrder, $validated['invalid'], $message];
	}

	public function createDealerOrder(Company $company, $customer, array $params, User $user, $distributor_lines, $blanketOrder, $stockPointId)
	{

		$distributor = Distributor::find($stockPointId);
		$message = null;
		$soAmount = 0;
		$recipients = [];
		$sender = $user;
		$purchaseOrder = new PurchaseOrder;
		//$purchaseOrder->order_number = "PO-".substr(strrev(time()),0,8);
		$purchaseOrder->shipping_method = $params['shipping_method'];
		$purchaseOrder->special_request = $params['special_request'];
		$UUId = Uuid::uuid4();
		$purchaseOrder->UUID = $UUId;
		$purchaseOrder->nav_key = $UUId;
		$validated = $this->salesOrderService->validatePurchaseLines($company, $distributor_lines->all(), $customer, $params['order_from'], $stockPointId);

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
			"special_request" => $params['special_request'],
			"shipping_address" => $params['shipping_address'],
			"order_from" => $params['order_from'],
			"purchase_lines" => $validLines,
		];
		$validLinesCollection = collect($validLines);
		$creditLimit = $customer->credit_limit;
		$balanceDue = $customer->overdue_balance;
		if ($soAmount > ($creditLimit - $balanceDue)) {
			$message = "Your order cannot be processed at this time due to negative credit limit. Please contact your Relationship Manager for further actions.";
			return [null, $validated['invalid'], $message];
		}
		if ($soAmount <= $creditLimit && $balanceDue > 0) {
			$message = "Your order cannot be processed at this time due to overdue payments. Please contact your Relationship Manager for further actions.";
			return [null, $validated['invalid'], $message];
		}
		$purchaseOrder->status = 'Approved';
		$purchaseOrder->amount = $soAmount;
		$purchaseOrder->company_id = $company->id;
		$purchaseOrder->distributor_id = $customer->distributor_id;
		$purchaseOrder->navision_order_number = '';
		if (array_has($params, 'end_customer_id'))
			$purchaseOrder->end_customer_id = $params['end_customer_id'];
		if (array_has($params, 'installer_id'))
			$purchaseOrder->engineer_id = $params['installer_id'];
		//	$purchaseOrder->stock_point_id = $stockPointId;
		$purchaseOrder->blanket_order_id = $blanketOrder->id;

		if ($stockPointId > 0) {
			$blanketOrder->distributor_id = $stockPointId;
			$blanketOrder->save();

			/* add distributor if stockpoint is distributor for dealer order */
			$purchaseOrder->distributor_id = $stockPointId;
			$purchaseOrder->dist_stock_point_id = $stockPointId;
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
				($item) ? $item->delete() : '';
			} else if ($params['order_from'] == "blocked") {
				$item = BlockedItem::find($line['item_id']);
				($item) ? $item->delete() : '';
			}
			$currentStock = $distributor->stocks->where('product_id', '=', $line['product_id'])
				->where('batch_no', '=', $line['batch_no'])->first();
			$newStock = $currentStock->quantity - $line['quantity'];
			$currentStock->quantity = $newStock;
			$currentStock->save();
			//adjust Distributor Stock
		}
		return [$purchaseOrder, $validated['invalid'], $message];
	}

	public function shipAndInvoice(Company $company, $customer, $orderId)
	{
		$purchaseOrder = PurchaseOrder::find($orderId);
		$purchaseLines = $purchaseOrder->purchase_lines()->get();

		//check for SS and Distributor StockPoint
		$groupedArrayKeysForSsStockPoint = $purchaseOrder->ss_stock_point_id != 0
			? $purchaseOrder->ss_stock_point_id
			: null;

		$groupedArrayKeysForDistStockPoint = $purchaseOrder->dist_stock_point_id != 0
			? $purchaseOrder->dist_stock_point_id
			: null;

		$blanketOrder = $purchaseOrder->blanket_order;
		$relatedPO = $blanketOrder->relatedOrder;

		if ($relatedPO)
			$relatedPOLines = $relatedPO->purchase_lines()->get();
		$alert = false;

		if (!empty($blanketOrder) && !empty($purchaseOrder)) //complete shipment scenario
		{
			foreach ($purchaseLines as $purchaseLine) {
				$productId = $purchaseLine->product_id;
				$quantity = $purchaseLine->quantity;
				// $shippedQuantity = $quantity;//$purchaseLine->shipped_quantity;
				$purchaseLine->shipped_quantity = $quantity;
				$purchaseLine->save();				
				/*if ($shippedQuantity <> $quantity) {
					$purchaseLine->shipped_quantity = $quantity;
					if ($relatedPO)
						// $purchaseLine->received_quantity = $quantity; //#510
					$purchaseLine->save();
				}*/
			}
			$purchaseOrder->status = 'Complete';
			$purchaseOrder->save();

			if ($relatedPO) {
				$relatedPO->status = 'Complete';
				$relatedPO->nav_invoice_number = $purchaseOrder->nav_invoice_number; //#635
				$relatedPO->nav_invoice_date = $purchaseOrder->nav_invoice_date; //#862 
				$relatedPO->save();
				foreach ($relatedPOLines as $relatedPurchaseLine) {
					$relatedPurchaseLine->shipped_quantity = $relatedPurchaseLine->quantity;
					$relatedPurchaseLine->save();
				}
				$blanketOrder->shipped = true;
				$blanketOrder->order_status = 'Complete'; //#635
				$blanketOrder->save();
			}elseif (!empty($groupedArrayKeysForSsStockPoint) || !empty($groupedArrayKeysForDistStockPoint)) {
				$blanketOrder->shipped = true;
				$blanketOrder->order_status = 'Complete'; //#635
				$blanketOrder->save();
			}


		} else //status update in Nav 
		{
			if ($purchaseOrder->status <> 'Cancelled') {
				$purchaseOrder->status = 'Cancelled';
				$purchaseOrder->cancelation_reason = 'Cancelled by Company Admin';
				$purchaseOrder->save();
				if ($relatedPO) {
					$relatedPO->status = 'Cancelled';
					$relatedPO->cancelation_reason = 'Cancelled by Company Admin';
					$relatedPO->save();
					$blanketOrder->cancelation_reason = 'Cancelled by Company Admin';
					//$blanketOrder->canceled_by = $loggedInUser->id;
					$blanketOrder->save();
				}
			}
		}

		if ($relatedPO) //#635
		{
			if ($relatedPO->status == 'Complete') {
				$distributor = $relatedPO->blanket_order->distributor;
				$superStockist = $relatedPO->blanket_order->superStockist;

				if (!empty($superStockist)) {
					$superStockist_invoice_series = $superStockist->invoice_series + 1;
					$superStockist_code = substr($superStockist->nav_id, 5);
					$temp = str_pad($superStockist_invoice_series, 5, '0', STR_PAD_LEFT);

					if (date('m') <= 3)
						$year = date('Y');
					else
						$year = date('Y') + 1;
					$year1 = $year - 1;
					$fiscalyear = $year1 . '-' . $year;
					$ros_inv_num = 'PI' . '/' . $fiscalyear . '/' . $superStockist_code . '/' . $temp;
					if ($relatedPO->blanket_order->ros_invoice_number == null) {
						$relatedPO->blanket_order->ros_invoice_number = $ros_inv_num;
						$relatedPO->blanket_order->save();
						$superStockist->invoice_series = $superStockist_invoice_series;
						$superStockist->save();
					}
				} else if (!empty($distributor)) {
					$distributor = $relatedPO->blanket_order->distributor;
					$distributor_invoice_series = $distributor->invoice_series + 1;
					$distributor_code = substr($distributor->nav_id, 5);
					$temp = str_pad($distributor_invoice_series, 5, '0', STR_PAD_LEFT);

					if (date('m') <= 3)
						$year = date('Y');
					else
						$year = date('Y') + 1;
					$year1 = $year - 1;
					$fiscalyear = $year1 . '-' . $year;
					$ros_inv_num = 'PI' . '/' . $fiscalyear . '/' . $distributor_code . '/' . $temp;
					if ($relatedPO->blanket_order->ros_invoice_number == null) {
						$relatedPO->blanket_order->ros_invoice_number = $ros_inv_num;
						$relatedPO->blanket_order->save();
						$distributor->invoice_series = $distributor_invoice_series;
						$distributor->save();
					}
				}
			}
		}
		return [$purchaseOrder, $alert];
		//return $purchaseOrder;
	}	
}
