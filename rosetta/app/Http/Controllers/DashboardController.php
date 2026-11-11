<?php

namespace Rosetta\Http\Controllers;

use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Rosetta\Models\BlanketOrder;
use Rosetta\Models\TempDealer;
use Rosetta\Services\Dashboard\DashboardServiceInterface;
use Rosetta\Transformers\Dealers\DealerTransformer;
use Rosetta\Transformers\Distributors\DistributorTransformer;
use Rosetta\Services\BlanketOrders\BlanketOrdersServiceInterface;

class DashboardController extends Controller
{
    protected $dashboardService, $blanketOrdersService;
    public function __construct(DashboardServiceInterface $dashboardService, BlanketOrdersServiceInterface $blanketOrdersService)
    {
        $this->dashboardService = $dashboardService;
        $this->blanketOrdersService = $blanketOrdersService;
    }
    public function index(Request $request)
    {
        $tiles = [];
        $monthly = [];
        $balance;
        $credit_limit;
        $loggedInUser = Auth::user();

        //Check for Superstockist
        if ($loggedInUser->super_stockist_id != null) {
            $superStockist = $loggedInUser->superStockist;
            $balance = $superStockist->nav->getBalance_LCY();
            $credit_limit = $superStockist->nav->getCredit_Limit_LCY();
            $name = $superStockist->name;
            $company = $superStockist->company()->get()->first();
            $availableCredit = $this->dashboardService->getAvailableCredit($company, $superStockist);

            $completePurchaseOrders = $superStockist->orders()->where('status', '=', 'Complete')->where('created_at', '>=', Carbon::now()->startOfMonth()->format('U'))->get();

            $completeAdvanceOrders = $superStockist->advanceOrders()->where('status', '=', 'Complete')->where('created_at', '>=', Carbon::now()->startOfMonth()->format('U'))->get();
            $completePurchaseOrdersTotal = 0;
            $completeAdvanceOrdersTotal = 0;
            foreach ($completePurchaseOrders as $completePurchaseOrder) {
                $completePurchaseOrdersTotal = $completePurchaseOrdersTotal + $completePurchaseOrder->Amount;
            }
            foreach ($completeAdvanceOrders as $completeAdvanceOrder) {
                $completeAdvanceOrdersTotal = $completeAdvanceOrdersTotal + $completeAdvanceOrder->amount;
            }
            $completePurchaseOrdersCnt = count($completePurchaseOrders);
            $completeAdvanceOrdersCnt = count($completeAdvanceOrders);
            $completePurchaseOrdersTotal = round($completePurchaseOrdersTotal, 2);
            $completeAdvanceOrdersTotal = round($completeAdvanceOrdersTotal, 2);
            //$completeOrdersCnt = $completePurchaseOrdersCnt + $completeAdvanceOrdersCnt;
            //$completeOrdersTotal = round($completeOrdersTotal, 2);

            if (($superStockist->payment_type & 1 == 1) || ($superStockist->payment_type & 3 == 3)) {
                $tiles[] = ["title" => "Credit Limit", "value" => $credit_limit, "type" => "distributor_credit_limit", "icon" => "fas fa-credit-card", "colour" => "bg-aqua"];
                $tiles[] = ["title" => "Available Credit", "value" => $availableCredit['creditLimit'], "type" => "distributor_available_credit", "icon" => "fas fa-credit-card", "colour" => "bg-green"];
                $tiles[] = ["title" => "Balance", "value" => $balance, "type" => "distributor_balance", "icon" => "fas fa-briefcase", "colour" => "bg-aqua"];
                $tiles[] = ["title" => "Balance Overdue", "value" => $availableCredit['balanceDue'], "type" => "distributor_balance_overdue", "icon" => "fas fa-briefcase", "colour" => "bg-red"];
            } else {
                $tiles[] = ["title" => "Credit Limit", "value" => 0, "type" => "distributor_credit_limit", "icon" => "fas fa-credit-card", "colour" => "bg-aqua"];
                $tiles[] = ["title" => "Available Credit", "value" => 0, "type" => "distributor_available_credit", "icon" => "fas fa-credit-card", "colour" => "bg-green"];
                $tiles[] = ["title" => "Balance", "value" => $balance, "type" => "distributor_balance", "icon" => "fas fa-briefcase", "colour" => "bg-aqua"];
                $tiles[] = ["title" => "Balance Overdue", "value" => 0, "type" => "distributor_balance_overdue", "icon" => "fas fa-briefcase", "colour" => "bg-red"];
            }

            $monthly = [
                ["title" => "Purchase Orders", "value" => $completePurchaseOrdersCnt, "type" => "distributor_purchase_orders_cnt", "icon" => "fas fa-shopping-cart", "colour" => "bg-aqua"],
                ["title" => "Purchase Orders Amount", "value" => $completePurchaseOrdersTotal, "type" => "distributor_purchase_orders_amt", "icon" => "fas fa-money-bill", "colour" => "bg-aqua"],
                ["title" => "Advance Orders", "value" => $completeAdvanceOrdersCnt, "type" => "distributor_advance_orders_cnt", "icon" => "fas fa-shopping-cart", "colour" => "bg-aqua"],
                ["title" => "Advance Orders Amount", "value" => $completeAdvanceOrdersTotal, "type" => "distributor_advance_orders_amt", "icon" => "fas fa-money-bill", "colour" => "bg-aqua"]
            ];
        } else
            //Check for Distributor
            if ($loggedInUser->distributor_id != null) {
                $distributor = $loggedInUser->distributor;
                $balance = $distributor->nav->getBalance_LCY();
                $credit_limit = $distributor->nav->getCredit_Limit_LCY();
                $name = $distributor->name;
                $company = $distributor->company()->get()->first();
                $availableCredit = $this->dashboardService->getAvailableCredit($company, $distributor);

                $completePurchaseOrders = $distributor->orders()->where('status', '=', 'Complete')->where('created_at', '>=', Carbon::now()->startOfMonth()->format('U'))->get();

                $completeAdvanceOrders = $distributor->advanceOrders()->where('status', '=', 'Complete')->where('created_at', '>=', Carbon::now()->startOfMonth()->format('U'))->get();
                $completePurchaseOrdersTotal = 0;
                $completeAdvanceOrdersTotal = 0;
                foreach ($completePurchaseOrders as $completePurchaseOrder) {
                    $completePurchaseOrdersTotal = $completePurchaseOrdersTotal + $completePurchaseOrder->Amount;
                }
                foreach ($completeAdvanceOrders as $completeAdvanceOrder) {
                    $completeAdvanceOrdersTotal = $completeAdvanceOrdersTotal + $completeAdvanceOrder->amount;
                }
                $completePurchaseOrdersCnt = count($completePurchaseOrders);
                $completeAdvanceOrdersCnt = count($completeAdvanceOrders);
                $completePurchaseOrdersTotal = round($completePurchaseOrdersTotal, 2);
                $completeAdvanceOrdersTotal = round($completeAdvanceOrdersTotal, 2);

                //if distributor is under superstockist
                if ($distributor->super_stockist_id != null) {
                    //Check total orders placed by distributor
                    $completePurchaseOrdersTotalTillDate = 0;
                    $completeAdvanceOrdersTotalTillDate = 0;

                    if (config('custom.show_stats') == true) {
                        $completePurchaseOrdersTillDate = $distributor->orders()->where('status', '=', 'Complete')->where('created_at', '<=', Carbon::now()->format('U'))->get();

                        $completeAdvanceOrdersTillDate = $distributor->advanceOrders()->where('status', '=', 'Complete')->where('created_at', '<=', Carbon::now()->format('U'))->get();

                        foreach ($completePurchaseOrdersTillDate as $completePurchaseOrderTillDate) {
                            $completePurchaseOrdersTotalTillDate = $completePurchaseOrdersTotalTillDate + $completePurchaseOrderTillDate->Amount;
                        }

                        foreach ($completeAdvanceOrdersTillDate as $completeAdvanceOrderTillDate) {
                            $completeAdvanceOrdersTotalTillDate = $completeAdvanceOrdersTotalTillDate + $completeAdvanceOrderTillDate->amount;
                        }

                        $completePurchaseOrdersTotalTillDate = round($completePurchaseOrdersTotalTillDate, 2);
                        $completeAdvanceOrdersTotalTillDate = round($completeAdvanceOrdersTotalTillDate, 2);
                    }



                    // dd($completePurchaseOrdersTotalTillDate, $completeAdvanceOrdersTotalTillDate);
                    $totalOrdersValue = ($completePurchaseOrdersTotalTillDate + $completeAdvanceOrdersTotalTillDate);
                    $availableCreditLimit = round($credit_limit - $totalOrdersValue, 2);
                    $balance_overdue = $availableCredit['balanceDue'];
                    if (empty($balance_overdue)) {
                        $balance_overdue = $totalOrdersValue;
                    }
                    if (empty($balance)) {
                        $balance = $totalOrdersValue;
                    }
                    if (($distributor->payment_type & 1 == 1) || ($distributor->payment_type & 3 == 3)) {
                        $tiles[] = ["title" => "Credit Limit", "value" => $credit_limit, "type" => "distributor_credit_limit", "icon" => "fas fa-credit-card", "colour" => "bg-aqua"];
                        $tiles[] = ["title" => "Available Credit", "value" => $availableCreditLimit, "type" => "distributor_available_credit", "icon" => "fas fa-credit-card", "colour" => "bg-green"];
                        $tiles[] = ["title" => "Balance", "value" => $balance, "type" => "distributor_balance", "icon" => "fas fa-briefcase", "colour" => "bg-aqua"];
                        $tiles[] = ["title" => "Balance Overdue", "value" => $balance_overdue, "type" => "distributor_balance_overdue", "icon" => "fas fa-briefcase", "colour" => "bg-red"];
                    } else {
                        $tiles[] = ["title" => "Credit Limit", "value" => 0, "type" => "distributor_credit_limit", "icon" => "fas fa-credit-card", "colour" => "bg-aqua"];
                        $tiles[] = ["title" => "Available Credit", "value" => 0, "type" => "distributor_available_credit", "icon" => "fas fa-credit-card", "colour" => "bg-green"];
                        $tiles[] = ["title" => "Balance", "value" => $balance, "type" => "distributor_balance", "icon" => "fas fa-briefcase", "colour" => "bg-aqua"];
                        $tiles[] = ["title" => "Balance Overdue", "value" => 0, "type" => "distributor_balance_overdue", "icon" => "fas fa-briefcase", "colour" => "bg-red"];
                    }

                    $monthly = [
                        ["title" => "Purchase Orders", "value" => $completePurchaseOrdersCnt, "type" => "distributor_purchase_orders_cnt", "icon" => "fas fa-shopping-cart", "colour" => "bg-aqua"],
                        ["title" => "Purchase Orders Amount", "value" => $completePurchaseOrdersTotal, "type" => "distributor_purchase_orders_amt", "icon" => "fas fa-money-bill", "colour" => "bg-aqua"],
                        ["title" => "Advance Orders", "value" => $completeAdvanceOrdersCnt, "type" => "distributor_advance_orders_cnt", "icon" => "fas fa-shopping-cart", "colour" => "bg-aqua"],
                        ["title" => "Advance Orders Amount", "value" => $completeAdvanceOrdersTotal, "type" => "distributor_advance_orders_amt", "icon" => "fas fa-money-bill", "colour" => "bg-aqua"]
                    ];
                } else {

                    if (($distributor->payment_type & 1 == 1) || ($distributor->payment_type & 3 == 3)) {
                        $tiles[] = ["title" => "Credit Limit", "value" => $credit_limit, "type" => "distributor_credit_limit", "icon" => "fas fa-credit-card", "colour" => "bg-aqua"];
                        $tiles[] = ["title" => "Available Credit", "value" => $availableCredit['creditLimit'], "type" => "distributor_available_credit", "icon" => "fas fa-credit-card", "colour" => "bg-green"];
                        $tiles[] = ["title" => "Balance", "value" => $balance, "type" => "distributor_balance", "icon" => "fas fa-briefcase", "colour" => "bg-aqua"];
                        $tiles[] = ["title" => "Balance Overdue", "value" => $availableCredit['balanceDue'], "type" => "distributor_balance_overdue", "icon" => "fas fa-briefcase", "colour" => "bg-red"];
                    } else {
                        $tiles[] = ["title" => "Credit Limit", "value" => 0, "type" => "distributor_credit_limit", "icon" => "fas fa-credit-card", "colour" => "bg-aqua"];
                        $tiles[] = ["title" => "Available Credit", "value" => 0, "type" => "distributor_available_credit", "icon" => "fas fa-credit-card", "colour" => "bg-green"];
                        $tiles[] = ["title" => "Balance", "value" => $balance, "type" => "distributor_balance", "icon" => "fas fa-briefcase", "colour" => "bg-aqua"];
                        $tiles[] = ["title" => "Balance Overdue", "value" => 0, "type" => "distributor_balance_overdue", "icon" => "fas fa-briefcase", "colour" => "bg-red"];
                    }

                    $monthly = [
                        ["title" => "Purchase Orders", "value" => $completePurchaseOrdersCnt, "type" => "distributor_purchase_orders_cnt", "icon" => "fas fa-shopping-cart", "colour" => "bg-aqua"],
                        ["title" => "Purchase Orders Amount", "value" => $completePurchaseOrdersTotal, "type" => "distributor_purchase_orders_amt", "icon" => "fas fa-money-bill", "colour" => "bg-aqua"],
                        ["title" => "Advance Orders", "value" => $completeAdvanceOrdersCnt, "type" => "distributor_advance_orders_cnt", "icon" => "fas fa-shopping-cart", "colour" => "bg-aqua"],
                        ["title" => "Advance Orders Amount", "value" => $completeAdvanceOrdersTotal, "type" => "distributor_advance_orders_amt", "icon" => "fas fa-money-bill", "colour" => "bg-aqua"]
                    ];
                }
            } // Add condition for Dealer under Company
            elseif ($loggedInUser->dealer_id != null && $loggedInUser->dealer->distributor_id == null) {
                $dealer = $loggedInUser->dealer;
                $balance = $dealer->nav->getBalance_LCY();
                $credit_limit = $dealer->nav->getCredit_Limit_LCY();
                $name = $dealer->name;
                $company = $dealer->company()->get()->first();
                $availableCredit = $this->dashboardService->getAvailableCredit($company, $dealer);

                $completePurchaseOrders = $dealer->orders()->where('status', '=', 'Complete')->where('created_at', '>=', Carbon::now()->startOfMonth()->format('U'))->get();
                $completeAdvanceOrders = $dealer->advanceOrders()->where('status', '=', 'Complete')->where('created_at', '>=', Carbon::now()->startOfMonth()->format('U'))->get();

                $completePurchaseOrdersTotal = 0;
                $completeAdvanceOrdersTotal = 0;
                foreach ($completePurchaseOrders as $completePurchaseOrder) {
                    $completePurchaseOrdersTotal = $completePurchaseOrdersTotal + $completePurchaseOrder->Amount;
                }
                foreach ($completeAdvanceOrders as $completeAdvanceOrder) {
                    $completeAdvanceOrdersTotal = $completeAdvanceOrdersTotal + $completeAdvanceOrder->amount;
                }
                $completePurchaseOrdersCnt = count($completePurchaseOrders);
                $completeAdvanceOrdersCnt = count($completeAdvanceOrders);
                $completePurchaseOrdersTotal = round($completePurchaseOrdersTotal, 2);
                $completeAdvanceOrdersTotal = round($completeAdvanceOrdersTotal, 2);
                //  $completeOrdersCnt = $completePurchaseOrdersCnt + $completeAdvanceOrdersCnt;
                //$completeOrdersTotal = round($completeOrdersTotal, 2);

                if (($dealer->payment_type & 1 == 1) || ($dealer->payment_type & 3 == 3)) {
                    $tiles[] = ["title" => "Credit Limit", "value" => $credit_limit, "type" => "dealer_credit_limit", "icon" => "fas fa-credit-card", "colour" => "bg-aqua"];
                    $tiles[] = ["title" => "Available Credit", "value" => $availableCredit['creditLimit'], "type" => "dealer_available_credit", "icon" => "fas fa-credit-card", "colour" => "bg-green"];
                    $tiles[] = ["title" => "Balance", "value" => $balance, "type" => "dealer_balance", "icon" => "fas fa-briefcase", "colour" => "bg-aqua"];
                    $tiles[] = ["title" => "Balance Overdue", "value" => $availableCredit['balanceDue'], "type" => "dealer_balance_overdue", "icon" => "fas fa-briefcase", "colour" => "bg-red"];
                } else {
                    $tiles[] = ["title" => "Credit Limit", "value" => 0, "type" => "dealer_credit_limit", "icon" => "fas fa-credit-card", "colour" => "bg-aqua"];
                    $tiles[] = ["title" => "Available Credit", "value" => 0, "type" => "dealer_available_credit", "icon" => "fas fa-credit-card", "colour" => "bg-green"];
                    $tiles[] = ["title" => "Balance", "value" => $balance, "type" => "dealer_balance", "icon" => "fas fa-briefcase", "colour" => "bg-aqua"];
                    $tiles[] = ["title" => "Balance Overdue", "value" => 0, "type" => "dealer_balance_overdue", "icon" => "fas fa-briefcase", "colour" => "bg-red"];
                }
                $monthly = [
                    ["title" => "Purchase Orders", "value" => $completePurchaseOrdersCnt, "type" => "dealer_purchase_orders_cnt", "icon" => "fas fa-shopping-cart", "colour" => "bg-aqua"],
                    ["title" => "Purchase Orders Amount", "value" => $completePurchaseOrdersTotal, "type" => "dealer_purchase_orders_amt", "icon" => "fas fa-money-bill", "colour" => "bg-aqua"],
                    ["title" => "Advance Orders", "value" => $completeAdvanceOrdersCnt, "type" => "dealer_advance_orders_cnt", "icon" => "fas fa-shopping-cart", "colour" => "bg-aqua"],
                    ["title" => "Advance Orders Amount", "value" => $completeAdvanceOrdersTotal, "type" => "dealer_advance_orders_amt", "icon" => "fas fa-money-bill", "colour" => "bg-aqua"]
                ];
            } elseif ($loggedInUser->dealer_id == null && $loggedInUser->distributor_id == null && $loggedInUser->company_id == null) {
                $cntTempDealers = TempDealer::where('status', '=', 'Pending_Approval')->count();
                if ($cntTempDealers > 0) {
                    $tiles[] = ["title" => "Dealers - Pending Approval", "value" => $cntTempDealers, "type" => "dealers_pending_approval", "icon" => "far fa-thumbs-up", "colour" => "bg-yellow"];
                }
            } elseif ($loggedInUser->dealer_id == null && $loggedInUser->distributor_id == null && $loggedInUser->company_id != null) {
                $cntTempDealers = TempDealer::where('status', '=', 'Pending_Approval')
                    ->where('company_id', '=', $loggedInUser->company_id)->count();
                if ($cntTempDealers > 0) {
                    $tiles[] = ["title" => "Dealers - Pending Approval", "value" => $cntTempDealers, "type" => "dealers_pending_approval", "icon" => "far fa-thumbs-up", "colour" => "bg-yellow"];
                }
            } elseif ($loggedInUser->dealer_id != null) {
                if ($loggedInUser->dealer->distributor_id != null) {
                    $dealer = $loggedInUser->dealer;
                    $balance_overdue = $dealer->overdue_balance;
                    $credit_limit = $dealer->credit_limit;
                    $completePurchaseOrders = $dealer->orders()/*->where('status', '=', 'Shipped')*/->whereIn('status', ['Shipped', 'Open', 'Complete', 'Approved'])->where('created_at', '>=', Carbon::now()->startOfMonth()->format('U'))->get();

                    $allCompletePurchaseOrders = $dealer->orders()/*->where('status', '=', 'Shipped')*/->whereIn('status', ['Shipped', 'Open', 'Complete', 'Approved'])->where('created_at', '<=', Carbon::now()->format('U'))->get();

                    $allCompletePurchaseOrdersTotal = 0;
                    foreach ($allCompletePurchaseOrders as $completePurchaseOrder) {
                        $allCompletePurchaseOrdersTotal = $allCompletePurchaseOrdersTotal + $completePurchaseOrder->Amount;
                    }
                    $allCompletePurchaseOrdersTotal = round($allCompletePurchaseOrdersTotal, 2);

                    $completePurchaseOrdersTotal = 0;
                    foreach ($completePurchaseOrders as $allCompletePurchaseOrder) {
                        $completePurchaseOrdersTotal = $completePurchaseOrdersTotal + $allCompletePurchaseOrder->Amount;
                    }
                    $completePurchaseOrdersCnt = count($completePurchaseOrders);
                    $completePurchaseOrdersTotal = round($completePurchaseOrdersTotal, 2);

                    if (empty($balance_overdue)) {
                        $balance_overdue = $allCompletePurchaseOrdersTotal;
                    }

                    if (($dealer->payment_type & 1 == 1) || ($dealer->payment_type & 3 == 3)) {
                        $avilableCredit = round($credit_limit - $allCompletePurchaseOrdersTotal, 2);
                        $tiles[] = ["title" => "Credit Limit", "value" => $credit_limit, "type" => "dealer_credit_limit", "icon" => "fas fa-credit-card", "colour" => "bg-aqua"];
                        $tiles[] = ["title" => "Available Credit", "value" => $avilableCredit, "type" => "dealer_available_credit", "icon" => "fas fa-credit-card", "colour" => "bg-green"];
                        $tiles[] = ["title" => "Balance", "value" => $allCompletePurchaseOrdersTotal, "type" => "dealer_balance", "icon" => "fas fa-briefcase", "colour" => "bg-aqua"];
                        $tiles[] = ["title" => "Balance Overdue", "value" => $balance_overdue, "type" => "dealer_balance_overdue", "icon" => "fas fa-briefcase", "colour" => "bg-red"];
                    }
                    $monthly = [
                        ["title" => "Purchase Orders", "value" => $completePurchaseOrdersCnt, "type" => "dealer_purchase_orders_cnt", "icon" => "fas fa-shopping-cart", "colour" => "bg-aqua"],
                        ["title" => "Purchase Orders Amount", "value" => $completePurchaseOrdersTotal, "type" => "dealer_purchase_orders_amt", "icon" => "fas fa-money-bill", "colour" => "bg-aqua"],
                    ];
                }
            }

        $totalNewOrdersCount = 0;
        $totalNewIssuedOrdersCount = 0;
        if (!empty($loggedInUser->distributor_id)) {
            $distributor_id = $loggedInUser->distributor_id;
            $totalNewOrdersCount = BlanketOrder::whereHas('purchase_orders', function ($q) use ($distributor_id) {
                $q->where('distributor_id', '=', $distributor_id);
            })->where('is_read', 0)->count();

            $customer = $loggedInUser->distributor;
            $company = $customer->company()->get()->first();

            $totalNewIssuedOrdersCount = $this->blanketOrdersService->all($company, $customer, $request->all())->where('is_read', 0)->count();
        }
        if (!empty($loggedInUser->super_stockist_id)) {
            $super_stockist_id = $loggedInUser->super_stockist_id;
            $totalNewOrdersCount = BlanketOrder::whereHas('purchase_orders', function ($q) use ($super_stockist_id) {
                $q->where('super_stockist_id', '=', $super_stockist_id);
            })->where('is_read', 0)->count();

            $customer = $loggedInUser->superStockist;
            $company = $customer->company()->get()->first();
            $totalNewIssuedOrdersCount = $this->blanketOrdersService->all($company, $customer, $request->all())->where('is_read', 0)->count();
        }
        return $out = [
            "tiles" => $tiles,
            "monthly" => $monthly,
            'new_order_count' => $totalNewOrdersCount,
            'new_issued_order_count' => $totalNewIssuedOrdersCount
        ];
    }
}

//fas fa-briefcase
//fas fa-credit-card