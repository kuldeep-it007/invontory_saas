<?php

namespace Rosetta\Http\Controllers;

use Exception;
use GrahamCampbell\ResultType\Success;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\Product\ActiveProductAttributes;
use Rosetta\Models\User;
use Spatie\Permission\Traits\HasRoles;

class MigrationController extends Controller
{
    use HasRoles;
    protected $env;
    public function __construct(){
        $this->env = 'pd';
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '-1');
    }

    public function index()
    {
        //qle();
        // Connect to the Production Database (PD) to fetch the distributors
        $superStokistId = 1;
        $pdDistributors = \DB::connection($this->env)->table('distributors')->whereIn('id',[2, 98, 116, 121, 136, 137])->orderBy('created_at', 'asc')->limit(100)->get();
       // $getDistributorsIds = $pdDistributors->pluck('id');
        

        $finalData = $this->process($superStokistId, $pdDistributors);        

        dd($finalData);
    }
    public function process($superStokistId, $pdDistributors)
    {
                        
        // Initialize arrays to store errors and successes
        $error['distributor'] = [];
        $success['distributor'] = [];

        // Loop through each distributor from the PD database
        foreach ($pdDistributors as $pdDistributor) {
            try {
                if($pdDistributor->id!=2){
                // Check if a distributor with the same nav_id already exists in the local database
                $oldRosettaDistributor = Distributor::where('nav_id', $pdDistributor->nav_id)->first();
                ($oldRosettaDistributor)?$oldRosettaDistributor->update(['is_moved_from_pd' => 2, 'deleted_at' => now()]):''; // Delete the distributor if it exists()
                //$distributorUserID = $oldRosettaDistributor->id ?? null;
            }

               // if(!$oldRosettaDistributor){
                if($pdDistributor->id==2){
                    $distributorID = 137;
                }else{
                    // If not, migrate the distributor
                    $newDistributor = $this->migrateDistributor($pdDistributor, $superStokistId);
                    $distributorID = $newDistributor->id??'';                    
                }
                    // Store the success in an array
                    $success['distributor'][] = [$pdDistributor->id, $pdDistributor->nav_id, $pdDistributor->name];


                    if($distributorID){
                        $pdAllDistributorUsers = \DB::connection($this->env)->table('users')->where('distributor_id', $pdDistributor->id)->orderBy('created_at', 'asc')->get();
                        
                        $distributorUserID = $this->migrateDistributorUser($pdAllDistributorUsers, $distributorID);    

                        /** Get the Dealers from the PD database */
                        $pdAllDistributorDealers = \DB::connection($this->env)->table('dealers')->where('distributor_id', $pdDistributor->id)->orderBy('created_at', 'asc')->get();
                        $distributorDealerID = $this->migrateDistributorDealers($pdAllDistributorDealers, $distributorID);
                    }
               // }                
            } catch (Exception $e) {
                // If an error occurs, store it in an array
                $error['distributor'][] = array_merge((array)$pdDistributor, ['error' => $e->getMessage()]);
            }
        }
        // Return the errors and successes
        return ['error' => $error, 'success' => $success];
    }


    /**
     * Migrate a distributor from the Production Database (PD) to the local database
     *
     * @param object $distributor The distributor from the PD database
     * @param int $superStokistId The ID of the super stockist this distributor belongs to
     * @return void
     */
    public function migrateDistributor($distributor, $superStokistId)
    {
        // First, check if a distributor with the same nav_id already exists in the local database
        // If so, don't create a new one
        $newDistributor = Distributor::create([
            // The ID of the distributor in the PD database
            //'id' => $distributor->id,
            // The Navision ID of the distributor
            'nav_id' => $distributor->nav_id,
            // The name of the distributor
            'name' => $distributor->name,
            // The contact person of the distributor
            'contact' => $distributor->contact,
            // The phone number of the distributor
            'phone' => $distributor->phone,
            // The email address of the distributor
            'email' => $distributor->email,
            // The GSTIN number of the distributor
            'gstin' => $distributor->gstin,
            // The ID of the company this distributor belongs to
            'company_id' => $distributor->company_id,
            // The ID of the super stockist this distributor belongs to
            'super_stockist_id' => $superStokistId,
            // The email address to send invoices to
            'to_email' => $distributor->to_email,
            // The email address to CC on invoices
            'cc_email' => $distributor->cc_email,
            // The payment type of the distributor
            'payment_type' => $distributor->payment_type,
            // The invoice series of the distributor
            'invoice_series' => $distributor->invoice_series,
            // The overdue balance of the distributor
            'overdue_balance' => $distributor->overdue_balance,
            // The number of days to hold the distributor's payments when they are overdue
            //'overdue_hold_days' => $distributor->overdue_hold_days??0,
            // The credit limit of the distributor
            'credit_limit' => $distributor->credit_limit,
            // The ID of the user who created the distributor
            'created_by' => $distributor->created_by,
            // The timestamp when the distributor was created
            'created_at' => $distributor->created_at,
            // The timestamp when the distributor was last updated
            'updated_at' => $distributor->updated_at,
            // The timestamp when the distributor was deleted (if it was)
            //'deleted_at' => $distributor->deleted_at??null,
            // The address of the distributor
            'address' => $distributor->address,
            // The second address of the distributor
            'address_2' => $distributor->address_2,
            // The city code of the distributor
            'city_code' => $distributor->city_code,
            // The state code of the distributor
            'state_code' => $distributor->state_code,
            // The post code of the distributor
            'post_code' => $distributor->post_code,
            'is_moved_from_pd' => 1
        ]);

        return $newDistributor;
    }    

    /**
     * Migrate a distributor's user from the Production Database (PD) to the local database
     *
     * @param int $distributorId The ID of the distributor in the local database
     * @return void
     */
    public function migrateDistributorUser($pdAllDistributorUsers, $distributorId, $type='distributor')
    {   
      foreach ($pdAllDistributorUsers as $pdDistributorUser) {
        $isUserAlreadyExists = ($pdDistributorUser->email)?User::where('email', $pdDistributorUser->email)->where('is_moved_from_pd', 1)->first():'';
        if(!$isUserAlreadyExists){
            $insertedData = [
                'name' => $pdDistributorUser->name,
                'email' => $pdDistributorUser->email,
                'password' => $pdDistributorUser->password,
                //'remember_token' => Str::random(10),                
                'super_stockist_id' => $pdDistributorUser->super_stockist_id,
                'company_id' => $pdDistributorUser->company_id,
                'communication_email' => $pdDistributorUser->communication_email,
                'created_at' => $pdDistributorUser->created_at,
                'updated_at' => $pdDistributorUser->updated_at,
                'deleted_at' => $pdDistributorUser->deleted_at,
                'active' => $pdDistributorUser->active,
                'change_password' => $pdDistributorUser->change_password,
                'phone' => $pdDistributorUser->phone,
                'is_moved_from_pd' => 1
            ];

            if($type == 'dealer'){
                $insertedData['distributor_id'] = $pdDistributorUser->distributor_id;
                $insertedData['dealer_id'] = $distributorId;
            }else{
                $insertedData['dealer_id'] = $pdDistributorUser->dealer_id;
                $insertedData['distributor_id'] = $distributorId;
            }

            $newDistributor = User::create($insertedData);
            if($type == 'dealer'){
                $newDistributor->assignRole('dealerAdmin');
            }else{
                $newDistributor->assignRole('distributorAdmin');
            }
            

        }else{
            if($type == 'dealer'){
                $updateddData['distributor_id'] = $pdDistributorUser->distributor_id;
                $updateddData['dealer_id'] = $distributorId;
            }else{
                $updateddData['dealer_id'] = $pdDistributorUser->dealer_id;
                $updateddData['distributor_id'] = $distributorId;
            }
            $isUserAlreadyExists->update($updateddData);
        }
        
      }
    }



    public function migrateDistributorDealers($pdAllDistributorDealers, $distributorId)
    {
        //trace($pdAllDistributorDealers);
        foreach ($pdAllDistributorDealers as $pdAllDistributorDealer) {
            $oldRosettaDistributorDealers = Dealer::where('nav_id', $pdAllDistributorDealer->nav_id)->first();
            ($oldRosettaDistributorDealers)?$oldRosettaDistributorDealers->update(['is_moved_from_pd' => 2, 'deleted_at' => now()]):''; // Delete the dealer if it exists()
            
            /** Insert PD dealer into local database */
            $newDealersData = Dealer::create([
                'nav_id' => $pdAllDistributorDealer->nav_id,
                'name' => $pdAllDistributorDealer->name,
                'contact' => $pdAllDistributorDealer->contact,
                'phone' => $pdAllDistributorDealer->phone,
                'email' => $pdAllDistributorDealer->email,
                'gstin' => $pdAllDistributorDealer->gstin,
                'area' => $pdAllDistributorDealer->area,
                'updated_folder_given' => $pdAllDistributorDealer->updated_folder_given,
                'app_explained' => $pdAllDistributorDealer->app_explained,
                'notes' => $pdAllDistributorDealer->notes,
                'company_id' => $pdAllDistributorDealer->company_id,
                'distributor_id' => $distributorId,
                'payment_type' => $pdAllDistributorDealer->payment_type,
                'type' => $pdAllDistributorDealer->type,
                'credit_limit' => $pdAllDistributorDealer->credit_limit,
                'overdue_balance' => $pdAllDistributorDealer->overdue_balance,
                'overdue_hold_days' => $pdAllDistributorDealer->overdue_hold_days,
                'created_by' => $pdAllDistributorDealer->created_by,
                'created_at' => $pdAllDistributorDealer->created_at,
                'updated_at' => $pdAllDistributorDealer->updated_at,
                'deleted_at' => $pdAllDistributorDealer->deleted_at,
                'address' => $pdAllDistributorDealer->address,
                'address_2' => $pdAllDistributorDealer->address_2,
                'city_code' => $pdAllDistributorDealer->city_code,
                'state_code' => $pdAllDistributorDealer->state_code,
                'post_code' => $pdAllDistributorDealer->post_code,
                'is_moved_from_pd' => 1
            ]);


            $pdAllDistributorUsers = \DB::connection($this->env)->table('users')->where('dealer_id', $pdAllDistributorDealer->id)->orderBy('created_at', 'asc')->get();
            $distributorUserID = $this->migrateDistributorUser($pdAllDistributorUsers, $newDealersData->id, 'dealer');
        }
    }


    public function handleActivateProducts()
    {
        //qle();
        // Connect to the Production Database (PD) to fetch the distributors
        $superStokistId = 1;
        $pdDistributors = \DB::connection($this->env)->table('distributors')->whereIn('id',[2, 98, 116, 121, 136, 137])->orderBy('created_at', 'asc')->limit(100)->get();
        // $getDistributorsIds = $pdDistributors->pluck('id');


        if($pdDistributors){
            foreach ($pdDistributors as $pdDistributor) {
                $pdDistributorsActiveProducts = \DB::connection($this->env)->table('active_product_attributes')->where('activable_id',$pdDistributor->id)->whereNull('deleted_at')->orderBy('created_at', 'asc')->get();
                $this->insertActiveProducts($pdDistributorsActiveProducts, 'distributor');
            }
        }
    }
    
private function insertActiveProducts($pdDistributorsActiveProducts, $type){
    
    foreach ($pdDistributorsActiveProducts as $pdDistributorsActiveProduct) {
        $data = [];        
        //if(!empty($pdDistributorsActiveProduct->activable_id)){
            $actualActiableId = $this->getDistributorArrAndValues($pdDistributorsActiveProduct->activable_id);
            $data['activable_id'] = $actualActiableId;
       // }
        //if(!empty($pdDistributorsActiveProduct->activable_type)){
            $data['activable_type'] = $pdDistributorsActiveProduct->activable_type;
        //}
        //if(!empty($pdDistributorsActiveProduct->company_id)){
            $data['company_id'] = $pdDistributorsActiveProduct->company_id;
        //}
        //if(!empty($pdDistributorsActiveProduct->category_code)){
            $data['category_code'] = $pdDistributorsActiveProduct->category_code;
        //}
        //if(!empty($pdDistributorsActiveProduct->category_display)){
            $data['category_display'] = $pdDistributorsActiveProduct->category_display;
        //}
        //if(!empty($pdDistributorsActiveProduct->collection_code)){
            $data['collection_code'] = $pdDistributorsActiveProduct->collection_code;
        //}
        //if(!empty($pdDistributorsActiveProduct->collection_display)){
            $data['collection_display'] = $pdDistributorsActiveProduct->collection_display;
        //}

       // if(!empty($pdDistributorsActiveProduct->colour_code)){
            $data['colour_code'] = $pdDistributorsActiveProduct->colour_code;
        //}
       // if(!empty($pdDistributorsActiveProduct->colour_display)){
            $data['colour_display'] = $pdDistributorsActiveProduct->colour_display;
       // }
     

        $isActiveProductExists = ActiveProductAttributes::where( $data)->first();

        //if(!empty($pdDistributorsActiveProduct->created_at)){
            $data['created_at'] = $pdDistributorsActiveProduct->created_at;
        //}
       // if(!empty($pdDistributorsActiveProduct->updated_at)){
            $data['updated_at'] = $pdDistributorsActiveProduct->updated_at;
        //}
        if(!$isActiveProductExists){
            $data['is_moved_from_pd']    = 1;
            ActiveProductAttributes::create($data);
        }
        
    }
} 


private function insertActiveProductsForDealers($pdDistributorsActiveProductsForDealers, $dealer){
    $xyzReturn = [];
    $getROSDealerByNavId = Dealer::where('nav_id', $dealer->nav_id)->where('is_moved_from_pd',1)->withTrashed()->first();
    foreach ($pdDistributorsActiveProductsForDealers as $pdDistributorsActiveProductsForDealer) {
        $data = []; 
      
        //if(!empty($pdDistributorsActiveProductsForDealer->activable_id)){
            $data['activable_id'] = $getROSDealerByNavId->id;
        //}
        //if(!empty($pdDistributorsActiveProductsForDealer->activable_type)){
            $data['activable_type'] = $pdDistributorsActiveProductsForDealer->activable_type;
        //}
        //if(!empty($pdDistributorsActiveProductsForDealer->company_id)){
            $data['company_id'] = $pdDistributorsActiveProductsForDealer->company_id;
        //}
        //if(!empty($pdDistributorsActiveProductsForDealer->category_code)){
            $data['category_code'] = $pdDistributorsActiveProductsForDealer->category_code;
       // }
        //if(!empty($pdDistributorsActiveProductsForDealer->category_display)){
            $data['category_display'] = $pdDistributorsActiveProductsForDealer->category_display;
        //}
       // if(!empty($pdDistributorsActiveProductsForDealer->collection_code)){
            $data['collection_code'] = $pdDistributorsActiveProductsForDealer->collection_code;
        //}
        //if(!empty($pdDistributorsActiveProductsForDealer->collection_display)){
            $data['collection_display'] = $pdDistributorsActiveProductsForDealer->collection_display;
        //}

        //if(!empty($pdDistributorsActiveProductsForDealer->colour_code)){
            $data['colour_code'] = $pdDistributorsActiveProductsForDealer->colour_code;
        //}
        //if(!empty($pdDistributorsActiveProductsForDealer->colour_display)){
            $data['colour_display'] = $pdDistributorsActiveProductsForDealer->colour_display;
       // }
     

        $isActiveProductExists = ActiveProductAttributes::where( $data)->first();

        //if(!empty($pdDistributorsActiveProductsForDealer->created_at)){
            $data['created_at'] = $pdDistributorsActiveProductsForDealer->created_at;
        //}
        //if(!empty($pdDistributorsActiveProductsForDealer->updated_at)){
            $data['updated_at'] = $pdDistributorsActiveProductsForDealer->updated_at;
        //}
        
        if(!$isActiveProductExists){
            $data['is_moved_from_pd']    = 1;
            ActiveProductAttributes::create($data);
        }
        
    }
    return $xyzReturn;
} 

public function handleActivateProductsForDealers()
{
    //qle();
    // Connect to the Production Database (PD) to fetch the distributors
    $pdDealersUnderDistributors = \DB::connection($this->env)->table('dealers')->whereIn('distributor_id',[2, 98, 116, 121, 136, 137])->orderBy('created_at', 'asc')->get();
    // $getDistributorsIds = $pdDistributors->pluck('id');

foreach ($pdDealersUnderDistributors as $pdDealersUnderDistributor) {
    $getAllActivatedProductsForDealers = \DB::connection($this->env)->table('active_product_attributes')->where('activable_id',$pdDealersUnderDistributor->id)->whereNull('deleted_at')->orderBy('created_at', 'asc')->get();

    $this->insertActiveProductsForDealers($getAllActivatedProductsForDealers, $pdDealersUnderDistributor);
}
}

public function getDistributorArrAndValues($key=''){
    $existingDistributors = [2=>137, 98=>138, 116=>139, 121=>140, 136=>141, 137=>142];
    //2, 98, 116, 121, 136, 137
    //137, 138, 139, 140, 141, 142  
    if(!empty($key)){
        return $existingDistributors[$key];
    }else{
        return $existingDistributors;    
    }
}

public function generateQuery()
{
    qle();
    // Connect to the Production Database (PD) to fetch the distributors
    $pdDealersUnderDistributors = \DB::table('dealers')->whereIn('distributor_id',[137, 138, 139, 140, 141, 142])->orderBy('created_at', 'asc')->pluck('id');
    //select `id` from `dealers` where `distributor_id` in ('2', '98', '116', '121', '136', '137') order by `created_at` asc

    // $getDistributorsIds = $pdDistributors->pluck('id');
    $getAllActivatedProductsForDealers = \DB::table('active_product_attributes')->whereIN('activable_id',$pdDealersUnderDistributors)->where('activable_type','Rosetta\Models\Dealer')->whereNull('deleted_at')->orderBy('created_at', 'asc')->get();
    qldd($getAllActivatedProductsForDealers);

}
}