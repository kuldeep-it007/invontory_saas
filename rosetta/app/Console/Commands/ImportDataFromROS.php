<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Rosetta\Models\Company;
use Rosetta\Models\Product\Product;
use Rosetta\Services\CartItems\CartItemsServiceInterface;

class ImportDataFromROS extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:old:ros {companyID} {custID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $cartService;

    public function __construct(CartItemsServiceInterface $cartService)
    {
        parent::__construct();
        $this->cartService = $cartService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $companyID = $this->argument('companyID');   
        $custID = $this->argument('custID');   
        $company = Company::find($companyID);
        $distributor = $company->distributors()->where('nav_id','=',$custID)->first();
        $rosUser = DB::connection('old_ros')
                ->table('dbo.AspNetUsers')
                ->select('id')
                ->where('NAVId',$distributor->nav_id)
                ->first();
        $rosCartItems = DB::connection('old_ros')
                ->table('dbo.Sales_Line')
                ->select('Item No_','Quantity','Date','LotNo','Rate_Type','BlockingId')
                ->where('UserId',$rosUser->id)
                ->get();
        $discountService = resolve('Rosetta\Services\Discount\DiscountsServiceInterface');
        $cart = [];
        $blocked = [];
        foreach($rosCartItems as $rosCartItem)
        {
            $product = Product::where('nav_id','=',$rosCartItem->{'Item No_'})->first();
            if(!$product)
            {
                continue;
            }
            $product_unit_price = $product->prices()->where('company_id','=',$company->id)->first();
            if(!$product_unit_price)
            {
                continue;
            }
            $discount = $discountService->getDiscount('DISTRIBUTO',$distributor,$product);

            //$unit_price = $product_unit_price->price - ($product_unit_price->price * $discount / 100);
            //$unit_price = $unit_price + ($unit_price * 10/100);
            $unit_price = 0;

            if($product_unit_price)
            {
                $unit_price = $product_unit_price->price;
            }
            if($rosCartItem->Rate_Type == 'CL')
            {
                if($product->category->allow_cl)
                {
                    $unit_price = $unit_price + ($unit_price * $product->category->cl_percent/100);
                }
            }
            
            echo "{$product->nav_id} {$product->id},U:$unit_price,D:$discount, U1: $unit_price\n";
            if($rosCartItem->BlockingId)
            {
                $blocked[] = [
                    'product_id' => $product->id,
                    'batch_no' => $rosCartItem->LotNo,
                    'order_quantity' => $rosCartItem->Quantity,
                    'rate_type' => $rosCartItem->Rate_Type,
                    'unit_price' => $unit_price,
                    'discount' => $discount,
                ];
            }
            else
            {
                $cart[] = [
                    'product_id' => $product->id,
                    'batch_no' => $rosCartItem->LotNo,
                    'order_quantity' => $rosCartItem->Quantity,
                    'rate_type' => $rosCartItem->Rate_Type,
                    'unit_price' => $unit_price,
                    'discount' => $discount,
                ];
            }
        }
        if(count($cart))
        {
            $this->cartService->create(['cart_items' => $cart],$distributor);
        }
        if(count($blocked))
        {
            $this->cartService->block(['block_items' => $blocked],$distributor);
        }
    }
}
