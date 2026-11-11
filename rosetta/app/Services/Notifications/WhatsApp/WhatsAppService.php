<?php

namespace Rosetta\Services\Notifications\WhatsApp;

use Rosetta\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Rosetta\Models\BlanketOrder;
use Rosetta\Models\Company\StockPoint;
use Rosetta\Models\Distributor;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Models\WhatsAppLog;

class WhatsAppService implements WhatsAppServiceInterface
{
    protected $whatsAppApiUrl, $whatsAppApiToken, $whatsAppDefaultNumber, $whatsAppAllowTest;
    public function __construct()
    {
        $this->whatsAppApiUrl = config('whatsapp.whatsapp_api_url');
        $this->whatsAppApiToken = config('whatsapp.whatsapp_api_token');
        $this->whatsAppDefaultNumber = config('whatsapp.default_whatsapp_number');
        $this->whatsAppAllowTest = config('whatsapp.allow_test_whatsapp');
    }
    public function placeOrderWhatsAppNotification($request)
    {  
        $whatsAppLog = new WhatsAppLog();
        $whatsAppLog->request = $request;
        $response = Http::withOptions([
            'verify' => false
        ])->withHeaders([
            'Content-Type' => 'application/json',
            'x-api-key' => $this->whatsAppApiToken,
        ])->post($this->whatsAppApiUrl . 'send_rosetta_order_messages', $whatsAppLog->request);

        $whatsAppLog->response = $response->body();
        $whatsAppLog->status_code = $response->status();
        $whatsAppLog->save();

        // Optionally check or log the response
        if ($response->successful()) {
            // Success
            //Log::info('Message sent successfully.', $response->json());
            return response($response->body(), $response->status());
        } else {
            // Handle error
            // Log::error('Failed to send message', [
            //     'status' => $response->status(),
            //     'body' => $response->body()
            // ]);
            return response($response->body(), $response->status());
        }
    }



    public function orderPlacedCheck($orderNumber, BlanketOrder $blanketOrder)
    {
        if (empty($blanketOrder)) {
            return;
        }
        try {
            $allRelatedPurchaseOrderIds =  $this->getAllRelatedBlanketOrder($blanketOrder->purchase_orders);
            if (!empty($allRelatedPurchaseOrderIds)) {
                foreach ($allRelatedPurchaseOrderIds as $purchaseOrder) {
                    $blanketOrder = $purchaseOrder->blanket_order;

                    if (!empty($purchaseOrder->ss_stock_point_id)) {
                        $stockPointId = $purchaseOrder->ss_stock_point_id;
                        $stockPoint = SuperStockist::find($stockPointId);
                    } elseif (!empty($purchaseOrder->dist_stock_point_id)) {
                        $stockPointId = $purchaseOrder->dist_stock_point_id;
                        $stockPoint = Distributor::find($stockPointId);
                    }elseif(!empty($purchaseOrder->stock_point_id)){
                        $stockPointId = $purchaseOrder->stock_point_id;
                        $stockPoint = StockPoint::find($stockPointId);

                    }else{
                       continue; 
                    }

                    // dd($stockPoint);

                    $phoneNumber = $this->whatsAppAllowTest == 1 ? $this->whatsAppDefaultNumber : $stockPoint->phone??'';

                    if (empty($phoneNumber)) {
                        // continue;
                    }

                    $stockPointCity = $stockPoint->city_code ?? '-';
                    $stockPointName = $stockPoint->display_name ?? '-';
                    $displayStockPoint = $stockPointName ?: $stockPointCity ?: '-';

                    $productNames = $purchaseOrder->purchase_lines->pluck('product.name')->unique()->implode(', ');
                    $totalAmount = $purchaseOrder->purchase_lines()->sum('quantity');//->sum('net_amount');
                    $orderableType = $blanketOrder->orderable_type;


                    if (!empty($blanketOrder)) {
                        $requestData = [
                            'orderId' => $blanketOrder->order_number,
                            'productName' => $productNames,
                            'orderTotal' => $totalAmount,
                            'stockPoint' => $displayStockPoint,
                            'orderDate' => $blanketOrder->created_at->format('jS F Y'),
                            'whatsappNumber' => $phoneNumber,
                        ];

                        //send notification to superstockist
                        if ($orderableType == 'Rosetta\Models\SuperStockist\SuperStockist') {
                            // $requestData['distributorName'] = $blanketOrder->orderable->name ?? '';
                            // $requestData['dealerRegion'] = $cityCode;
                            // $requestData['templateName'] = 'stockist_order_placed3';

                            $requestData['fromName'] = $blanketOrder->orderable->name ?? '';
                            $requestData['receiverName'] = $stockPoint->name ?? '-';
                            $requestData['templateName'] = 'generic_order_message';
                            $requestData['type'] = 'generic_order_message-1';
                            $this->placeOrder($requestData);
                        } elseif ($orderableType == 'Rosetta\Models\Dealer') {
                            // Send notification to super stockist and distributor
                            if (!empty($blanketOrder->orderable->distributor->superStockist)) {
                                $finalNumber = $this->whatsAppAllowTest == 1 ? $this->whatsAppDefaultNumber : $blanketOrder->orderable->distributor->superStockist->phone;
                                $requestData['distributorName'] = $blanketOrder->orderable->distributor->name ?? '-';
                                $requestData['dealerRegion'] = $blanketOrder->orderable->city_code ?? '-';
                                $requestData['templateName'] = "stockist_order_placed";
                                $requestDataSsNumber['whatsappNumber'] = $finalNumber;
                                $requestDataSsNumber['type'] = 'generic_order_message-2';
                                $newRequestedData = array_merge($requestData, $requestDataSsNumber);
                                $this->placeOrder($newRequestedData);
                            }
                            // Send notification to distributor under super stockist
                            if (!empty($blanketOrder->orderable->distributor)) {
                                $finalDistNumber = $this->whatsAppAllowTest == 1 ? $this->whatsAppDefaultNumber : $blanketOrder->orderable->distributor->phone;

                                $requestData['distributorName'] = $blanketOrder->orderable->distributor->name ?? '-';
                                $requestData['dealerName'] = $blanketOrder->orderable->name ?? '-';
                                $requestData['stockistName'] = $stockPoint->name ?? '-';
                                $requestData['templateName'] = "distributor_order_placed";
                                $requestDataDistNumber['whatsappNumber'] = $finalDistNumber ?? '';
                                $requestDataDistNumber['type'] = 'generic_order_message-3';
                                $newDistributorRequestedData = array_merge($requestData, $requestDataDistNumber);
                                $this->placeOrder($newDistributorRequestedData);
                            }

                            //Send notification to dealer
                            $finalDealerNumber = $this->whatsAppAllowTest == 1 ? $this->whatsAppDefaultNumber : $blanketOrder->orderable->phone;
                            $requestDataDealerNumber['whatsappNumber'] = $finalDealerNumber ?? '';
                            $requestData['dealerName'] = $blanketOrder->orderable->name ?? '-';
                            $requestData['stockistName'] = $stockPoint->name ?? '-';
                            $requestData['templateName'] = "dealer_order_placed";                            
                            $requestData['type'] = 'generic_order_message-4';
                            $newDealerRequestedData = array_merge($requestData, $requestDataDealerNumber);                                
                            $this->placeOrder($newDealerRequestedData);
                        } elseif ($orderableType == 'Rosetta\Models\Distributor') {
                            // Send notification to distributor under super stockist
                            if (!empty($blanketOrder->orderable->superStockist)/* && ($stockPoint->id != $blanketOrder->orderable->superStockist->id)*/) {
                                $requestData['fromName'] = $blanketOrder->orderable->name ?? '';
                                $requestData['receiverName'] = $blanketOrder->orderable->superStockist->name ?? '-';
                                $requestData['templateName'] = 'generic_order_message';
                                $requestDatadistsNumber['whatsappNumber'] = $blanketOrder->orderable->superStockist->phone ?? '';
                                $requestDatadistsNumber['type'] = 'generic_order_message-5';
                                $newDistsRequestedData = array_merge($requestData, $requestDatadistsNumber);
                                $this->placeOrder($newDistsRequestedData);

                                // Send notification to distributor
                                /*$requestData['receiverName'] = $blanketOrder->orderable->name ?? '';
                                $requestData['fromName'] = $stockPoint->name ?? '-';
                                $requestData['templateName'] = 'generic_order_message';
                                $requestData['type'] = 'generic_order_message';
                                $requestDataSsNumber['whatsappNumber'] = $blanketOrder->orderable->phone ?? '';
                                $requestDataSsNumber['type'] = 'generic_order_message-7';
                                $newSsRequestedData = array_merge($requestData, $requestDataSsNumber);
                                $this->placeOrder($newSsRequestedData);*/

                                //Send notification to dealer
                                $requestDataDealerNumber['whatsappNumber'] = $blanketOrder->orderable->phone ?? '';
                                $requestData['dealerName'] = $blanketOrder->orderable->name ?? '-';
                                $requestData['stockistName'] = $stockPoint->name ?? '-';
                                $requestData['templateName'] = "dealer_order_placed";                            
                                $requestData['type'] = 'generic_order_message-4';
                                $newDealerRequestedData = array_merge($requestData, $requestDataDealerNumber);                                
                                $this->placeOrder($newDealerRequestedData);
                            } else {
                                // Send notification to distributor
                                $requestData['fromName'] = $blanketOrder->orderable->name ?? '';
                                $requestData['receiverName'] = $stockPoint->name ?? '-';
                                $requestData['templateName'] = 'generic_order_message';
                                $requestData['type'] = 'generic_order_message';
                                $requestData['type'] = 'generic_order_message-6';
                                $this->placeOrder($requestData);
                            }
                        } else {
                            $requestData['fromName'] = $blanketOrder->orderable->name ?? '';
                            $requestData['receiverName'] = $stockPoint->name ?? '-';
                            $requestData['templateName'] = 'generic_order_message';
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Error in orderPlacedCheck', ['error' => $e->getMessage()]);
        }
    }


    private function getAllRelatedBlanketOrder($purchaseOrder, &$visited = [], $isRoot = false)
    {
        $ids = [];

        if (!is_array($purchaseOrder) && !($purchaseOrder instanceof \Illuminate\Support\Collection)) {
            $purchaseOrder = [$purchaseOrder];
        }

        foreach ($purchaseOrder as $po) {
            if (!$po || in_array($po->id, $visited)) {
                continue;
            }
            $visited[] = $po->id;

            // Only add the ID if not the root, or if you want to always include root, keep as before
            if (!$isRoot) {
                $ids[] = $po;
            }

            $blanketOrder = \Rosetta\Models\BlanketOrder::where('related_po_id', $po->id)->first();
            if (!empty($blanketOrder->purchase_orders) && $blanketOrder->purchase_orders && $blanketOrder->purchase_orders->count()) {
                $ids = array_merge($ids, $this->getAllRelatedBlanketOrder($blanketOrder->purchase_orders, $visited, false));
            }
        }

        // If root, and you want to include it, add here:
        if ($isRoot && isset($po) && $po) {
            array_unshift($ids, $po->id);
        }

        return array_unique($ids);
    }


    public function placeOrder($requestData)
    {        
        // return '';
        $distributorName = array_key_exists('distributorName', $requestData) ? $requestData['distributorName'] : '';
        $dealerRegion = array_key_exists('dealerRegion', $requestData) ? $requestData['dealerRegion'] : '';
        $productName = array_key_exists('productName', $requestData) ? $requestData['productName'] : '';
        $orderTotal = array_key_exists('orderTotal', $requestData) ? $requestData['orderTotal'] : '';
        $orderDate = array_key_exists('orderDate', $requestData) ? $requestData['orderDate'] : '';
        $templateName = array_key_exists('templateName', $requestData) ? $requestData['templateName'] : '';
        $whatsappNumber = array_key_exists('whatsappNumber', $requestData) ? $requestData['whatsappNumber'] : '';

        if (empty($whatsappNumber)) {
            Log::error('WhatsApp number is empty for order: ' . $requestData['orderId']);
            return;
        }

        $requestData['distributorName'] = $distributorName;
        $requestData['dealerRegion'] = $dealerRegion;
        $requestData['productName'] = $productName;
        $requestData['orderTotal'] = $orderTotal;
        $requestData['orderDate'] = $orderDate;
        $requestData['templateName'] = $templateName;
        $requestData['whatsappNumber'] = $whatsappNumber;
        if($this->whatsAppAllowTest == 1){
            trace($requestData);
        }else{
            return $this->placeOrderWhatsAppNotification($requestData);
        }        
    }

    /*public function orderPlacedCheck_bck($orderNumber, BlanketOrder $blanketOrder)
    {
        if (empty($blanketOrder)) {
            return;
        }
        try {
            $ordersByStockPoint = $blanketOrder->purchase_orders->groupBy('ss_stock_point_id');
            if ($ordersByStockPoint->isEmpty()) {
                return;
            }

            $cityCode = $blanketOrder->orderable->city_code ?? '-';
            $productNames = $blanketOrder->purchase_orders->flatMap(function ($purchaseOrder) {
                return $purchaseOrder->purchase_lines->pluck('product.name')->unique();
            })->unique()->implode(', ');

            $orderableType = $blanketOrder->orderable_type;

            foreach ($ordersByStockPoint as $stockPointId => $orders) {
                $stockPoint = SuperStockist::find($stockPointId);
                if ($stockPoint) {
                    $stockPointCity = $stockPoint->city_code ?? '';
                    $stockPointName = $stockPoint->display_name ?? '';
                    $displayStockPoint = $stockPointName ?: $stockPointCity ?: '-';
                    $phoneNumber = $this->whatsAppAllowTest == 1 ? $this->whatsAppDefaultNumber : $stockPoint->phone;

                    if (empty($phoneNumber)) {
                        continue;
                    }

                    $totalAmount = $orders->pluck('purchase_lines')->flatten()->sum('net_amount');
                    $distributorName = $blanketOrder->orderable->distributor->name ?? '';

                    $requestData = [
                        'orderId' => $orderNumber,
                        'productName' => $productNames,
                        'orderTotal' => $totalAmount,
                        'stockPoint' => $displayStockPoint,
                        'orderDate' => $blanketOrder->created_at->format('jS F Y'),
                        'whatsappNumber' => $phoneNumber,
                    ];

                    if ($orderableType == 'Rosetta\Models\Distributor') {
                        $requestData['distributorName'] = $blanketOrder->orderable->name ?? '';
                        $requestData['dealerRegion'] = $cityCode;
                        $requestData['templateName'] = 'stockist_order_placed';
                    } else {
                        $requestData['fromName'] = $blanketOrder->orderable->name ?? '';
                        $requestData['receiverName'] = $stockPoint->name ?? '-';
                        $requestData['templateName'] = 'generic_order_message';
                    }

                    $this->placeOrder($requestData);

                    if (!empty($orders)) {

                        $allRelatedPurchaseOrderIds =  $this->getAllRelatedPurchaseOrderIds($orders);
                        $getAllBlanketOrders = BlanketOrder::whereIn('related_po_id', $allRelatedPurchaseOrderIds)->get();

                        if (!empty($getAllBlanketOrders)) {
                            foreach ($getAllBlanketOrders as $blanketOrder) {
                                $totalAmount = $blanketOrder->purchase_orders->pluck('purchase_lines')->flatten()->sum('net_amount');
                                $phoneNumber = $this->whatsAppAllowTest == 1 ? $this->whatsAppDefaultNumber : $blanketOrder->orderable->phone;

                                if (empty($phoneNumber)) {
                                    continue;
                                }

                                $requestData = [
                                    'orderId' => $blanketOrder->order_number,
                                    'productName' => $productNames,
                                    'orderTotal' => $totalAmount,
                                    'stockPoint' => $displayStockPoint,
                                    'orderDate' => $blanketOrder->created_at->format('jS F Y'),
                                    'whatsappNumber' => $phoneNumber,
                                ];

                                if ($blanketOrder->orderable_type == 'RRosetta\Models\SuperStockist\SuperStockist') {
                                    $requestData['distributorName'] = $distributorName ?: '-';
                                    $requestData['dealerName'] = $blanketOrder->orderable->name ?? '-';
                                    $requestData['stockistName'] = $stockPoint->name ?? '-';
                                    $requestData['templateName'] = 'stockist_order_placed';
                                } elseif ($blanketOrder->orderable_type == 'Rosetta\Models\Dealer') {
                                    $requestData['distributorName'] = $distributorName ?: '-';
                                    $requestData['dealerName'] = $blanketOrder->orderable->name ?? '-';
                                    $requestData['stockistName'] = $stockPoint->name ?? '-';
                                    $requestData['templateName'] = 'distributor_order_placed';
                                } elseif ($blanketOrder->orderable_type == 'Rosetta\Models\Distributor') {
                                    $requestData['distributorName'] = $blanketOrder->orderable->name ?? '';
                                    $requestData['dealerRegion'] = $cityCode;
                                    $requestData['templateName'] = 'stockist_order_placed';
                                } else {
                                    $requestData['fromName'] = $blanketOrder->orderable->name ?? '';
                                    $requestData['receiverName'] = $stockPoint->name ?? '-';
                                    $requestData['templateName'] = 'generic_order_message';
                                }
                            }
                            $this->placeOrder($requestData);
                            dd('======================', $requestData);
                        }
                    }
                } else {
                    $ordersByDistributorPoint = $orders->groupBy('dist_stock_point_id');
                    foreach ($ordersByDistributorPoint as $distPointId => $distOrders) {
                        $distPoint = Distributor::find($distPointId);
                        if ($distPoint) {
                            $phoneNumber = $this->whatsAppAllowTest == 1 ? $this->whatsAppDefaultNumber : $distPoint->phone;
                            $totalAmount = $distOrders->pluck('purchase_lines')->flatten()->sum('net_amount');
                            $distributorName = $blanketOrder->orderable->distributor->name ?? ($orderableType == 'Rosetta\Models\Distributor' ? $blanketOrder->orderable->name : '');

                            $stockPointCity = $distPoint->city_code ?? '';
                            $stockPointName = $distPoint->display_name ?? '';
                            $displayStockPoint = $stockPointName ?: $stockPointCity ?: '-';

                            /*$requestData = [
                                'distributorName' => $distributorName ?: '-',
                                'dealerName' => $blanketOrder->orderable->name ?? '-',
                                'orderId' => $orderNumber,
                                'productName' => $productNames,
                                'orderTotal' => $totalAmount,
                                'stockistName' => $distPoint->name ?? '-',
                                'stockPoint' => $distPoint->city_code ?? '-',
                                'orderDate' => $blanketOrder->created_at->format('jS F Y'),
                                'templateName' => 'distributor_order_placed',
                                'whatsappNumber' => $phoneNumber,
                            ];*

                            $requestData = [
                                'orderId' => $orderNumber,
                                'productName' => $productNames,
                                'orderTotal' => $totalAmount,
                                'stockPoint' => $displayStockPoint,
                                'orderDate' => $blanketOrder->created_at->format('jS F Y'),
                                'whatsappNumber' => $phoneNumber,
                            ];

                            if ($orderableType == 'Rosetta\Models\Dealer') {
                                $requestData['distributorName'] = $distributorName ?: '-';
                                $requestData['dealerName'] = $blanketOrder->orderable->name ?? '-';
                                $requestData['stockistName'] = $distPoint->name ?? '-';
                                $requestData['templateName'] = 'distributor_order_placed';
                            } elseif ($orderableType == 'Rosetta\Models\Distributor') {
                                $requestData['distributorName'] = $blanketOrder->orderable->name ?? '';
                                $requestData['dealerRegion'] = $cityCode;
                                $requestData['templateName'] = 'stockist_order_placed';

                                $requestData['fromName'] = $blanketOrder->orderable->name ?? '';
                                $requestData['receiverName'] = $distPoint->name ?? '-';
                                $requestData['templateName'] = 'generic_order_message';
                            } else {
                                $requestData['fromName'] = $blanketOrder->orderable->name ?? '';
                                $requestData['receiverName'] = $distPoint->name ?? '-';
                                $requestData['templateName'] = 'generic_order_message';
                            }

                            $this->placeOrder($requestData);
                            dd($requestData);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            dd($e);
            Log::error('Error in orderPlacedCheck', ['error' => $e->getMessage()]);
        }
    }


    private function getRelatedOrders($orders, &$visited = [])
    {
        $relatedOrderIds = [];
        trace($orders);
        // Ensure $orders is iterable
        if (!is_array($orders) && !($orders instanceof \Illuminate\Support\Collection)) {
            $orders = [$orders];
        }

        foreach ($orders as $order) {
            if (!$order) {
                continue;
            }
            if (in_array($order->id, $visited)) {
                // Already visited, skip to prevent infinite loop
                continue;
            }

            $visited[] = $order->id;

            // Check for relatedOrder relation
            if (!empty($order->relatedOrder)) {
                $relatedOrderIds[] = $order->relatedOrder->id;
                // Recursively collect from related order
                // dd($order->relatedOrder->relatedOrder);
                $relatedOrderIds = array_merge(
                    $relatedOrderIds,
                    $this->getRelatedOrders($order->relatedOrder->relatedOrder, $visited)
                );
            }
        }

        return $relatedOrderIds;
    }
        
    private function getAllRelatedPurchaseOrderIds($purchaseOrder, &$visited = [])
    {
        $ids = [];
        if (!is_array($purchaseOrder) && !($purchaseOrder instanceof \Illuminate\Support\Collection)) {
            $purchaseOrder = [$purchaseOrder];
        }

        foreach ($purchaseOrder as $po) {
            if (!$po || in_array($po->id, $visited)) {
                continue;
            }
            $visited[] = $po->id;

            $blanketOrder = \Rosetta\Models\BlanketOrder::where('related_po_id', $po->id)->first();
            if (!empty($blanketOrder->purchase_orders) && $blanketOrder->purchase_orders && $blanketOrder->purchase_orders->count()) {
                // Only add the ID if not the root, or if you want to always include root, keep as before
                $ids[] = $po->id;
                // Recursively get all related purchase order IDs
                $ids = array_merge($ids, $this->getAllRelatedPurchaseOrderIds($blanketOrder->purchase_orders, $visited, false));
            }
        }
        return array_unique($ids);
    }*/
}
