<?php

namespace Rosetta\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use PDF;
use Predis\Connection\ConnectionException;
use Rosetta\Events\Notifications;
use Rosetta\Exceptions\RedisException;
use Rosetta\Mail\MissingWarranty;
use Rosetta\Models\Customer;
use Rosetta\Models\Notification;
use Rosetta\Models\Product\Product;
use Rosetta\Models\PurchaseOrder\PurchaseOrder;
use Rosetta\Models\User;
use Rosetta\Models\WarrantyTerms;
use Rosetta\Services\CMS\CMSServiceInterface;
use Tymon\JWTAuth\Providers\config;

class WarrantyController extends Controller
{
	protected $cmsService;
	public function __construct(CMSServiceInterface $cmsService)
	{
		$this->cmsService = $cmsService;
	}
    public function warranty(Request $request)
    {
    	//return view('warranty',[]);
    //	$order = PurchaseOrder::where('order_number', '=', $request['order_no'])->first();
        $recipients = [];
        $toEmailIds = [];
        $sender = '';
        $brand = config('brand.brand_short');
        $brandName = config('brand.brand');


    	$customer = Customer::where('phone_no', '=', $request['phone_no'])->first();
    	if(!$customer)
    		return response()->json("Invalid details entered",404);
    	$order = PurchaseOrder::where('invoice_id', '=', $request['invoice_no'])
    							->where('end_customer_id', '=', $customer->id)->first();
    	if(!$order)
    		return response()->json("Invalid details entered",404);
        $authorizedDate = $order->created_at->format('Y/m/d');
        $invoice_number = $request['invoice_no'];
        $dealerName = $order->orderable->name;
    	$productIds = $order->purchase_lines()->distinct()->get(['product_id'])->pluck('product_id')->toArray();
    	$techspecs = [];
    	$data = [];
    	foreach ($productIds as $productId)
    	{
    		$product = Product::find($productId);
    		$techspecs = $this->cmsService->getTechSpecs($product->category_code,$product->collection_code);
            $warranty_duration = WarrantyTerms::where('category_code', '=', $product->category->code)
                                    ->where('collection_code', '=', $product->collection->code)->first();
            $warranty_terms = WarrantyTerms::where('category_code', '=', $product->category->code)->first();
            if($warranty_duration)
            {
                $duration = $warranty_duration->duration;
            }
            else
            {
            //    $sender = User::where('dealer_id', '=', $order->orderable->id)->first();//check this

                $senderQuery = User::where('dealer_id','=',$order->orderable->id);
                $senderQuery->whereHas('roles',function($q) {
                        $q->where('name','=','dealerAdmin');
                    });
                $sender = $senderQuery->first();

                $superAdminEmailQuery = User::where('distributor_id','=',null)
                        ->where('dealer_id','=',null)
                        ->where('super_stockist_id','=',null)
                        ->where('company_id','=',null);
                $superAdminEmailQuery->whereHas('roles', function($q){
                        $q->where('name', '=', 'superadmin');
                    });
                $superAdminEmailIds = $superAdminEmailQuery->get();
                foreach ($superAdminEmailIds as $user)
                {
                    $recipients[] = $user;
                    if($user->communication_email)
                        $toEmailIds[] = $user->communication_email;
                }

                foreach ($recipients as $recipient) 
                {
                    $notification = new Notification;
                    $notification->title = 'Warranty Terms Missing!';
                    $notification->text = 'Warranty terms missing for '. $product->category->code . ' >> ' . $product->collection->code . '. Please add necessary data as Customers are requesting for Certificate.';
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
                $Qmessage = (new MissingWarranty($product))->onQueue($queue);

                if(!empty($toEmailIds))
                    Mail::to($toEmailIds)->queue($Qmessage);

            /*    try
                {
                    if(!empty($toEmailIds))
                        Mail::to($toEmailIds)->queue($Qmessage);
                }
                catch(ConnectionException $e)
                {
                    throw new RedisException;
                }
*/
                $responseString = "Please contact ". $brandName . " support to avail of the certificate";
                return response()->json($responseString,404);
            }
            if($warranty_terms)
            {
                $term = $warranty_terms->term;
                $description = $warranty_terms->description;
            }
            $data[] = [
                "product" => $product,
                "techspecs" => $techspecs,
                "authorizedDate" => $authorizedDate,
                "invoiceNumber" => $invoice_number,
                "dealerName" => $dealerName,
                "duration" => $duration,
                "term" => $term,
                "description" => $description
            ];  	
    	}
    //	return view('warranty',["products" => $data]);

    	PDF::setOptions(['dpi' => 300,]);
    	$pdf = PDF::loadView('warranty', ["products" => $data]);
    	return $pdf->download('warranty.pdf');
    	
    	return [
    		"pdf" => base64_encode($pdf->output()),
    	];
    }
}
