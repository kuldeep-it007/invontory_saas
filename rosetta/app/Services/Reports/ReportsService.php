<?php
namespace Rosetta\Services\Reports;

use Rosetta\Models\Distributor;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
use Rosetta\Services\Reports\ReportsServiceInterface;


class ReportsService implements ReportsServiceInterface
{
   public function getDealersWithOrders($distributor, $params)//order filter removed because in ActiveInactiveDUDs reports all Dealers are required in initial query irrespective whether they placed order in last month or not.
   {
      $dealersQuery = $distributor->dealers()->whereNotNull('type')->whereNull('deleted_at');
      $dealers = $dealersQuery->get();
      //   $dealers->sortBy('Amount');
      return $dealers;
   }
   public function getDealersWithOrdersold($distributor, $params)
   {
      $dealersQuery = $distributor->dealers()->with('orders');
      $dealersQuery->whereHas('orders', function($query) use($params){
         /* if(array_has($params, 'start_date'))
         {
         $query->where('nav_invoice_date', '>=', $params['start_date']);
         }
         if(array_has($params, 'end_date'))
         {
         $query->where('nav_invoice_date', '<=', $params['end_date']);
         }*/
         $query->where('status','=','Complete');
      });
      //   dd($dealersQuery->toSql());
      //   $dealers->sortBy('Amount');
      $dealers = $dealersQuery->get();
      $dealers->sortBy('Amount');
      return $dealers;
   }
   public function getDealerOrdersAmount($dealer, $start_date, $end_date)
   {
      $dealerOrders = $dealer->orders()->where('status','=','Complete')->where('nav_invoice_date', '>=', $start_date)->where('nav_invoice_date', '<=', $end_date)->get();
      //	$dealerOrders = $dealer->orders()->where('status','=','Complete')->where('nav_invoice_date', '>=', $start_date)->where('nav_invoice_date', '<=', $end_date);
      //dd($dealerOrders->toSql());
      $amount = $dealerOrders->sum('Amount');//In case of DUD orders Amount at PO level is without tax, so no need to calculate line total amount unlike Distributor/Dealer to Company Orders
      $count = $dealerOrders->count();
      return [$amount, $count];
   }
   public function getOnBehalfCnt($dealer, $start_date, $end_date)
   {
      $query = $dealer->orders()->where('status','=','Complete')->where('nav_invoice_date', '>=', $start_date)->where('nav_invoice_date', '<=', $end_date);
      $query->whereHas('blanket_order',function($q) {
         $q->where('on_behalf','=','Dealer');
      });
      $bos = $query->get();
      $cnt = $bos->count();
      return $cnt;
   }
   public function getSelfCnt($dealer, $start_date, $end_date)
   {
      $query = $dealer->orders()->where('status','=','Complete')->where('nav_invoice_date', '>=', $start_date)->where('nav_invoice_date', '<=', $end_date);
      $query->whereHas('blanket_order',function($q) {
         $q->where('on_behalf','!=','Dealer');
      });
      $bos = $query->get();
      $cnt = $bos->count();
      return $cnt;
   }
   public function getAppOrdersCnt($dealer, $start_date, $end_date)
   {
      $query = $dealer->orders()->where('status','=','Complete')->where('nav_invoice_date', '>=', $start_date)->where('nav_invoice_date', '<=', $end_date);
      $query->whereHas('blanket_order',function($q) {
         $q->where('source','=','APP');
      });
      $bos = $query->get();
      $cnt = $bos->count();
      return $cnt;
   }
   public function getCustomerWiseOrderDetails($dealer, $start_date, $end_date)
   {
      $query = $dealer->orders()->where('status','=','Complete')->where('nav_invoice_date', '>=', $start_date)->where('nav_invoice_date', '<=', $end_date);
  //    dd($query->toSql());
      $dealerOrders = $query->get();
      return $dealerOrders;
   }
}