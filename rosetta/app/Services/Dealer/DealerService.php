<?php
namespace Rosetta\Services\Dealer;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Predis\Connection\ConnectionException;
use Rosetta\Events\Notifications;
use Rosetta\Exceptions\RedisException;
use Rosetta\Mail\AccountCreated;
use Rosetta\Mail\ApproveRejectDealer;
use Rosetta\Mail\DealerApproved;
use Rosetta\Mail\DealerCreated;
use Rosetta\Mail\DealerRejected;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\Notification;
use Rosetta\Models\Region;
use Rosetta\Models\Role;
use Rosetta\Models\TempDealer;
use Rosetta\Models\User;
use Rosetta\Models\setCustomerCard;
use Rosetta\Models\stocks;
use Rosetta\Services\ActiveProductAttributes\ActiveProductAttributesServiceInterface;
use Rosetta\Services\Customer\CustomerServiceInterface;
use Rosetta\Services\Dealer\DealerServiceInterface;
use Rosetta\Services\Distributor\DistributorServiceInterface;
use Rosetta\Services\Engineer\EngineerServiceInterface;
use Rosetta\Services\NAV\CustomerServiceInterface as NAVCustomerServiceInterface;
use Rosetta\Services\NAV\STAServiceInterface;
use Illuminate\Support\Facades\Notification as SMSNotification;
use Rosetta\Notifications\SMS\AccountCreatedNotification;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
//use Rosetta\Services\Users\UserServiceInterface;

class DealerService implements DealerServiceInterface
{
	protected $navCustomerService, $distributorService, $staService, $customerService, $engineerService;
	public function __construct(NAVCustomerServiceInterface $navCustomerService, DistributorServiceInterface $distributorService, STAServiceInterface $staService, ActiveProductAttributesServiceInterface $activeProductAttributesService, CustomerServiceInterface $customerService, EngineerServiceInterface $engineerService)
	{
		$this->navCustomerService = $navCustomerService;
		$this->customerService = $customerService;
		$this->distributorService = $distributorService;
		$this->staService = $staService;
		$this->activeProductAttributesService = $activeProductAttributesService;
		$this->engineerService = $engineerService;
		//$this->userService = $userService;
	}
	public function create(User $user, Array $params, Company $company, Distributor $distributor = null)
	{
		$navDealer = $this->navCustomerService->createDealer($company, $params,$distributor);
		$dealer = new Dealer;
		$dealer->name = $params['name'];
		if(array_has($params,'tax_information'))
		{
			$taxInformation = $params['tax_information'];
			if(array_has($taxInformation,'gst'))
			{
				$gst = $taxInformation['gst'];
				if(isset($gst['number']))
					$dealer->gstin = $gst['number'];
				else
					$dealer->gstin = '';
			}
		}//#834
		if(array_has($params, 'contact'))
			$dealer->contact = $params['contact'];
		if(array_has($params, 'phone'))
			$dealer->phone = $params['phone'];
		if(array_has($params, 'email'))
			$dealer->email = $params['email'];
		$dealer->post_code = $params['zipcode'];
		$dealer->state_code = $params['state'];
		$dealer->city_code = $params['city'];
		if(array_has($params, 'address'))
			$dealer->address = $params['address'];
		if(array_has($params, 'address2'))
			$dealer->address_2 = $params['address2'];
		if(array_has($params, 'dealer_type'))
		{
			if($params['dealer_type'] == "1")
				$dealer->type = 'Dealer';
			if($params['dealer_type'] == "2")
				$dealer->type = 'Retail Customer';
		}
		if(array_has($params, 'credit_limit'))
			$dealer->credit_limit = $params['credit_limit'];
		if(array_has($params, 'overdue_balance'))
			$dealer->overdue_balance = $params['overdue_balance'];
		$dealer->nav_id = $navDealer->getNo();
		if(array_has($params,'paymentTerms'))
		{
			$days = [
				'7 DAYS' => 7,
				'15 DAYS' => 15,
				'1 MONTH' => 30,
				'1.5 MONTH' => 45,
				'100% ADVC' => 0
			];
			$dealer->overdue_hold_days = 0;
			if(array_has($days, $params['paymentTerms']))
				$dealer->overdue_hold_days = $days[$params['paymentTerms']];
		}
		//#872
		if(array_has($params, 'area'))
			$dealer->area = $params['area'];
		if(array_has($params, 'updated_folder_given') && $params['updated_folder_given'] == 'Y')
			$dealer->updated_folder_given = true;
		if(array_has($params, 'app_explained') && $params['app_explained'] == 'Y')
			$dealer->app_explained = true;
        if(array_has($params, 'notes'))
			$dealer->notes = $params['notes'];
		//end of #872
		$dealer->company()->associate($company);
		if($distributor != null)
			$dealer->distributor()->associate($distributor);
		$dealer->author()->associate($user);
		if(array_has($params, 'sales_manager_id'))
			$dealer->sales_manager_id = $params['sales_manager_id'];
		if(array_has($params, 'priority'))
			$dealer->priority = $params['priority'];
		$dealer->save();
		$this->staService->createAddress($company,$dealer->nav_id,$params);//#842
		return $dealer;
	}
	//public function update(User $user, Array $params, $company)
	public function update(User $user, $company,$dealer,Array $params)
	{
		//$dealer = $company->dealers()->find($dealerID);
		if($dealer->distributor_id)
			$distributor = $dealer->distributor;
		else
			$distributor = null;
		if(array_has($params, 'newDistributorId'))//#827
		{
			if($params['newDistributorId'] != null)
			{
				$distributor = Distributor::find($params['newDistributorId']);
				$dealer->distributor_id = $params['newDistributorId'];
			}
			else
			{
				$distributor = null;
				$dealer->distributor_id = null;
			}
			
		}
		$navDealer = $this->navCustomerService->updateDealer($company,$dealer, $params,$distributor);
		if(array_has($params, 'name'))
		{
			$dealer->name = $params['name'];
			if(array_has($params,'tax_information'))
			{
				$taxInformation = $params['tax_information'];
				if(array_has($taxInformation,'gst'))
				{
					$gst = $taxInformation['gst'];
					if(isset($gst['number']))
					{
						$dealer->gstin = $gst['number'];
					}
					else
					{
						$dealer->gstin = '';
					}
				}
			}//#834
			if(array_has($params, 'contact'))
				$dealer->contact = $params['contact'];
			if(array_has($params, 'phone'))
				$dealer->phone = $params['phone'];
			if(array_has($params, 'email'))
				$dealer->email = $params['email'];
			if(array_has($params, 'credit_limit'))
				$dealer->credit_limit = $params['credit_limit'];
			if(array_has($params, 'overdue_balance'))
				$dealer->overdue_balance = $params['overdue_balance'];
			if(array_has($params, 'dealer_type'))
			{
				if($params['dealer_type'] == "1")
					$dealer->type = 'Dealer';
				if($params['dealer_type'] == "2")
					$dealer->type = 'Retail Customer';
			}
			if(array_has($params, 'address'))
				$dealer->address = $params['address'];
			if(array_has($params, 'address2'))
				$dealer->address_2 = $params['address2'];
			if(array_has($params, 'city'))
				$dealer->city_code = $params['city'];
			if(array_has($params, 'state'))
				$dealer->state_code = $params['state'];
			if(array_has($params, 'zipcode'))
				$dealer->post_code = $params['zipcode'];
			//#872
			if(array_has($params, 'area'))
				$dealer->area = $params['area'];
			if(array_has($params, 'updated_folder_given'))
			{
				if($params['updated_folder_given'] == 'Y')
					$dealer->updated_folder_given = true;
				else
					$dealer->updated_folder_given = false;
			}
			if(array_has($params, 'app_explained'))
			{
				if($params['app_explained'] == 'Y')
					$dealer->app_explained = true;
				else
					$dealer->app_explained = false;
			}
	        if(array_has($params, 'notes'))
				$dealer->notes = $params['notes'];

			if(array_has($params, 'sales_manager_id'))
				$dealer->sales_manager_id = $params['sales_manager_id'];
			if(array_has($params, 'priority'))
				$dealer->priority = $params['priority'];
				//end of #872
				$dealer->save();
		}
		$dealer->setCustomerCard($navDealer);
		return $dealer;
	}
    public function createTempDealer(User $user, Array $params)
    {
        $tempDealer = new TempDealer;
        $tempDealer->name = $params['name'];
        $tempDealer->text = json_encode($params);
        $tempDealer->company_id = $params['companyId'];
        $tempDealer->distributor_id = $params['distributorId'];
		$tempDealer->status = 'Pending_Approval';
        $tempDealer->author()->associate($user);
        $tempDealer->save();

        $recipients = [];
        $toEmailIds = [];
		$sender = $user;
        if($user->distributor_id != null)
		{
			$distributor = Distributor::find($params['distributorId']);

			$query = User::where('company_id','=',$params['companyId']);
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
				$recipients[] = $user;
				if($user->communication_email)
					$toEmailIds[] = $user->communication_email;
			}//add Super Admin Email Ids
		}
		foreach ($recipients as $recipient) 
		{
			$notification = new Notification;
			$notification->title = 'New Dealer Created - Approve/Reject';
			$notification->text = $distributor->name . ' created new Dealer : ' . $tempDealer->name. '. Please review and Approve/Reject.';
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
		$Qmessage = (new ApproveRejectDealer($tempDealer, $distributor))->onQueue($queue);
        try
	    {
	    	if(!empty($toEmailIds))
        		Mail::to($toEmailIds)->queue($Qmessage);
       	}
       	catch(ConnectionException $e)
       	{
       		throw new RedisException;
	    }
        return $tempDealer;
    }
    public function getDealersWithCartItems(Company $company, Distributor $distributor=null, Array $params = [])
    {
    	if($distributor != null)
		{
			$dealers = $distributor->dealers()->whereHas('cartItems')->get();
		}//#749
    }
    public function getDealersWithBlockedItems(Company $company, Distributor $distributor=null, Array $params = [])
    {
    	if($distributor != null)
		{
			$dealers = $distributor->dealers()->whereHas('blockedItems')->get();
		}//#749
    }
    public function getDealersWithOrders(Company $company, Distributor $distributor=null, Array $params = [])
    {
    	if($distributor != null)
		{
			$dealers = $distributor->dealers()->whereHas('orders')->get();
		}//#749
    }
	public function getDealers(Company $company, Distributor $distributor=null, Array $params = [])
	{ 
		if($distributor == null)
		{
			if(array_has($params,'page'))
			{
				//$dealers = $company->companyDealers()->when($params, function ($query) use ($params){//#422
				$dealers = $company->dealers()->when($params, function ($query) use ($params){
					if(array_has($params, 'customer_name'))
					{
						//$query->where('name', '=', $params['customer_name']);
						$query->where('name', 'like', '%' . $params['customer_name'] . '%');
					}
					if(array_has($params,'customer_id'))
					{
						$query->where('nav_id', '=', $params['customer_id']);
					}
					if(array_has($params,'isDealerUnderDistUnderSs') && $params['isDealerUnderDistUnderSs']==true){
						$query->whereNull('distributor_id');
					}
				})->orderBy('created_at','desc')->paginate($params['per_page']);
			}
			else
			{
				if(array_has($params,'onlyCompany'))
					$dealers = $company->dealers()->whereNull('distributor_id')->get();
				else
				$dealers = $company->dealers()->when($params, function ($query) use ($params){
					if(array_has($params,'isDealerUnderDistUnderSs') && $params['isDealerUnderDistUnderSs']==true){
						$query->whereNotNull('distributor_id');
					}
				})->get();
			}
			return $dealers;
		}
		else
		{
			if(array_has($params,'page'))
			{
				$dealers = $distributor->dealers()->when($params, function ($query) use ($params){
					if(array_has($params, 'customer_name'))
					{
						//$query->where('name', '=', $params['customer_name']);
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
				if(array_has($params,'withOrders'))//#750
				{
					$dealers = $distributor->dealers()->whereHas('orders')->get();
				}
				elseif(array_has($params,'withCartItems'))//#749
				{
					$dealers = $distributor->dealers()->whereHas('cartItems')->get();
				}
				elseif(array_has($params,'withBlockedItems'))//#749
				{
					$dealers = $distributor->dealers()->whereHas('blockedItems')->get();
				}
				else
					$dealers = $distributor->dealers;
			}
			return $dealers;
		}
	}
	public function getTempDealers($company, $distributorId, Array $params)
	{
		if($distributorId == null)
		{
			if(array_has($params,'page'))
			{
				$dealers = TempDealer::where('company_id', '=', $company->id)
						->where('status', '=', 'Pending_Approval')->when($params, function ($query) use ($params){
								if(array_has($params, 'customer_name'))
								{
									//$query->where('name', '=', $params['customer_name']);
									$query->where('name', 'like', '%' . $params['customer_name'] . '%');
								}
								if(array_has($params,'customer_id'))
								{
									$query->where('distributor_id', '=', $params['customer_id']);
								}
							})->orderBy('created_at','desc')->paginate($params['per_page'], ['*'], '', $params['page']);
			}
		}
		else
		{
			if(array_has($params,'page'))
			{
				$dealers = TempDealer::where('company_id', '=', $company->id)
							->where('distributor_id', '=', $distributorId)
							->where('status', '=', 'Pending_Approval')
							->paginate($params['per_page'], ['*'], '', $params['page']);
			}
		}
		return $dealers;
	}
	public function getRejectedDealers($company, $distributorId, Array $params)
	{
		if(array_has($params,'page'))
		{
			return TempDealer::where('company_id', '=', $company->id)
							->where('distributor_id', '=', $distributorId)
							->where('status', '=', 'Rejected')
							->paginate($params['per_page'], ['*'], '', $params['page']);
		}
	}
	public function findTempDealer($company=null, $id)
	{
		if($company != null)
			return $company->tempDealers()->find($id);
		else
			return TempDealer::find($id);
	}
	public function createDealerUnderDistributor(User $user, $companyId, $distributorId, Array $params)
	{
		$company = Company::find($companyId);
		$distributor = $this->distributorService->find($distributorId);
		$navDistributor = $this->navCustomerService->get($company, $distributor->nav_id);
		$params["territory"] = $navDistributor->getTerritory_Code();
		$params["billingLocationCode"] = $navDistributor->getLocation_Code();
		$params["copySellFrom"] = "Company";
		$params["generalBusinessPostingGroup"] = "NATIONAL";
		$params["paymentApplicationMethod"] = "Manual";
		$params["paymentMethod"] = "AS AGREED";
		$params["shippingAdvice"] = "Complete";
		$params["shippingReserve"] = "Optional";
		$params["shippingMethod"] = "AS AGREED";

		if(array_has($params,'tax_information'))
		{
			$taxInformation = $params['tax_information'];
			if(array_has($taxInformation,'gst'))
			{
				$params["structure"] = "GST SERVIC";
			}
			if(array_has($taxInformation,'vat'))
			{
				$params["structure"] = "SALES UAE";	//#652 changed from VAT (EX) to SALES UAE as confirmed by NAV and Rosetta team for TRF/UAE this is the value that appears on Invoice generated in NAV
			}
		}
		$dealer = $this->create($user, $params, $company, $distributor);
	
		$newUser = new User;
		$newUser->email = $params['email'];
		$newUser->communication_email = $params['email'];
		$newUser->name = $dealer->name;
		$newUser->phone = $params['phone'];
		//$password = Str::random(8);
	//	$newUser->password = bcrypt($password);
		$newUser->password = bcrypt($params['password']);//#742
	//	$newUserRole = Role::where('name', '=', 'dealerAdmin')->first();//changed as part of laravel 6.0 upgarde
		$newUser->assignRole('dealerAdmin');
		$newUser->dealer()->associate($dealer);
		$newUser->change_password = true;
		$newUser->save();
	//	$newUser->roles()->sync($newUserRole);

        $this->activeProductAttributesService->load($company->id, 'dealer', $dealer->id, $distributorId);

        $notifiable = (object)[
            "customer" => $dealer->name,
            "number" => $newUser->phone
        ];
        SMSNotification::send($notifiable,new AccountCreatedNotification($company));//#595 Notify Customer

        $recipients = [];
        $toEmailIds = [];
		$sender = $user;
		$brand = config('brand.brand_short');

		$query = User::where('distributor_id','=',$distributorId);
		$query->whereHas('roles',function($q) {
				$q->where('name','=','distributorAdmin');
			});
		$users = $query->get();
		
		foreach ($users as $user) {
			if($user->communication_email)
				$toEmailIds[] = $user->communication_email;
			$recipients[] = $user;
		}//add Distributor Admin Email Ids

		$companyAdminQuery = User::where('company_id','=',$company->id);
		$companyAdminQuery->whereHas('roles',function($q) {
				$q->where('name','=','companyAdmin');
			});
		$companyAdmins = $companyAdminQuery->get();

		foreach ($companyAdmins as $user) {
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
		$superAdmins = $superAdminEmailQuery->get();
		foreach ($superAdmins as $user) {
			if($user->communication_email)
				$toEmailIds[] = $user->communication_email;
			$recipients[] = $user;
		}//add Super Admin Email Ids

		if($newUser->communication_email)
			$toDealerEmailId[] = $newUser->communication_email;//add  New User Email Id

		foreach ($recipients as $recipient) 
		{
			$notification = new Notification;
			$notification->title = 'New Dealer Created!';
			$notification->text = 'Dealer '. $dealer->name. ' has been created. Please check your email for '. $brand. ' credentials of the Dealer.';
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
        try
	    {
	    /*	$Qmessage = (new DealerCreated($dealer, $distributor,$params['email'], $params['password']))->onQueue($queue);
	    	if(!empty($toEmailIds))
        		Mail::to($toEmailIds)->queue($Qmessage);*/
        	$Qmessage = (new AccountCreated($dealer, $distributor,$params['email'], $params['password']))->onQueue($queue);
	    	if(!empty($toDealerEmailId))
        		Mail::to($toDealerEmailId)->queue($Qmessage);
       	}
       	catch(ConnectionException $e)
       	{
       		throw new RedisException;
	    }
	}
	public function rejectDealer(User $user, $company, $dealer, Array $params)
	{
		$dealer->status = 'Rejected';
		$dealer->rejection_reason = $params['reason'];
        $dealer->save();
        $recipients = [];
        $toEmailIds = [];
		$sender = $user;
		$brand = config('brand.brand_short');
        if($user->hasRole('superadmin') || $user->hasRole('companyAdmin'))
		{
			$query = User::where('distributor_id','=',$dealer->distributor_id);
			$query->whereHas('roles',function($q) {
					$q->where('name','=','distributorAdmin');
				});
			$users = $query->get();
			
			foreach ($users as $user) {
				if($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
			}

			$companyAdminQuery = User::where('company_id','=',$company->id);
			$companyAdminQuery->whereHas('roles',function($q) {
					$q->where('name','=','companyAdmin');
				});
			$companyAdmins = $companyAdminQuery->get();
	
			foreach ($companyAdmins as $user) {
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
			$superAdmins = $superAdminEmailQuery->get();
			foreach ($superAdmins as $user) {
				if($user->communication_email)
					$toEmailIds[] = $user->communication_email;
				$recipients[] = $user;
			}//add Super Admin Email Ids
			

			foreach ($recipients as $recipient) 
			{
				$notification = new Notification;
				$notification->title = 'Dealer Rejected!';
				$notification->text = 'Dealer '. $dealer->name. ' has been rejected.';
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
			$Qmessage = (new DealerRejected($dealer))->onQueue($queue);
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
	public function getInventory(int $dealerID, Array $params)
	{
		/*$dealer = Dealer::find($dealerID);
		$category = $params['filters']['category'];
		$collection = $params['filters']['collection'];
		$colour = $params['filters']['colour'];
		if($category == null && $collection == null && $colour == null)
		{
			$inventory = $dealer->stocks()->paginate($params['per_page'],['*'],'',$params['page']);
		}
		else
		{
			$inventory = $dealer->stocks()->whereHas('product', function($q)use($category, $collection, $colour)
				{
					if($colour == null && $collection == null && $category != null)
					{
						$q->where('category_code', '=', $category);
					}
					elseif($colour == null && $collection != null && $category != null)
					{
						$q->where('category_code', '=', $category)->where('collection_code', '=', $collection);
					}
					elseif($colour != null && $collection != null && $category != null)
					{
						$q->where('category_code', '=', $category)->where('collection_code', '=', $collection)->where('colour_code', '=', $colour);
					}
				})->paginate($params['per_page'],['*'],'',$params['page']);
		}
		return $inventory;*/
		$dealer = $this->find($dealerID);
		$category = null;
		$collection = null;
		$colour = null;
		$query = $dealer->stocks()->where('quantity', '<>', 0);
		if(array_has($params['params'],'filters') && count($params['params']['filters']))
		{
			$filters = $params['params']['filters'];
			if(array_has($filters,'category'))
			{
				$query->whereHas('product',function($q) use($filters){
					$q->where('category_code','=',$filters['category']);
					if(array_has($filters,'collection'))
					{
						$q->where('collection_code','=',$filters['collection']);
					}
					if(array_has($filters,'colour'))
					{
						$q->where('colour_code','=',$filters['colour']);
					}

				});
			}
			if(array_has($filters,'quantity'))
			{
				preg_match('/([=<>])(\d+)/',urldecode($filters['quantity']),$matches);
				$operator = $matches[1];
				$quantity = $matches[2];

				$query->where('quantity',$operator,$quantity);
			}
		}
		return $query->paginate($params['params']['per_page'],['*'],'',$params['params']['page']);
	}
	public function getInventoryDetails(int $dealerID, int $productID)
	{
		$dealer = Dealer::find($dealerID);
		return $inventory = $dealer->stocks->where('product_id', '=', $productID);
	}
	public function findForCompany(User $user, $companyID, $dealerID)
	{
		$company = Company::find($companyID);
		$dealer = $company->dealers()->find($dealerID);
		return $dealer;
	}
	public function findForDistributor(User $user,$companyID,$distributorID,$dealerID)
	{
		$company = Company::find($companyID);
		$distributor = $company->distributors()->find($distributorID);
		$dealer = $distributor->dealers()->find($dealerID);
		return $dealer;	
	}
	public function find(int $id)
	{
		$dealer = Dealer::find($id);
		if(!$dealer)
		{
			throw new ModelNotFoundException;
		}
		return $dealer;
	}
	public function createShippingAddresses(int $companyId,int $dealerId,Array $params)
	{
		$company = Company::find($companyId);
		$dealer = $company->dealers()->find($dealerId);
		return $this->staService->createAddress($company,$dealer->nav_id,$params);
	}
	public function updateShippingAddress(int $companyID, int $dealerId, $addressCode, Array $params)
	{
		$company = Company::find($companyID);
		$dealer = $company->dealers()->find($dealerId);
		return $this->staService->updateAddress($company,$dealer->nav_id, $addressCode, $params);
	}
	public function getShippingAddresses(int $companyId,int $dealerId)
	{
		$company = Company::find($companyId);
		$dealer = $company->dealers()->find($dealerId);
		return $this->staService->getAllAddresses($company,$dealer->nav_id);
	}
	public function deleteAddress(int $companyID, int $dealerId, $addressCode)
	{
		$company = Company::find($companyID);
		$dealer = $company->dealers()->find($dealerId);
		return $this->staService->deleteAddress($company,$dealer->nav_id, $addressCode);
	}
	public function createCustomers($companyId, $dealerId,Array $params, User $user)
	{
		return $this->customerService->createCustomer($companyId,$dealerId,$params, $user);
	}
	public function updateCustomer($dealerId, $customerId, Array $params)
	{
		return $this->customerService->updateCustomer($dealerId, $customerId, $params);
	}
	public function getCustomers($dealerId)
	{
		return $this->customerService->getAllCustomers($dealerId);
	}
	public function deleteCustomer($dealerId, $customerId)
	{
		return $this->customerService->deleteCustomer($dealerId, $customerId);
	}
	public function createEngineers($companyId, $dealerId,Array $params, User $user)
	{
		return $this->engineerService->createEngineer($companyId,$dealerId,$params, $user);
	}
	public function getEngineers($dealerId)
	{
		return $this->engineerService->getAllEngineers($dealerId);
	}
	public function sync(User $user,Company $company, $nav_id)
	{
		$navDealer = $this->navCustomerService->get($company, $nav_id);
        $dealer = new Dealer;
        $dealer->company()->associate($company);
        $dealer->nav_id = $navDealer->getNo();
        $dealer->name = $navDealer->getName();
        //dd($navDealer->getDistributor_Code());
        if($navDealer->getDistributor_Code())
        {
        	$distributor_nav_id = $navDealer->getDistributor_Code();
       		$distributor = Distributor::where('nav_id', '=', $distributor_nav_id)->first();
       		if($distributor)
        		$dealer->distributor_id = $distributor->id;
        	else
        		return null;
        }
        $dealer->city_code = $navDealer->getCity();
        $dealer->state_code = $navDealer->getState_Code();
        $dealer->post_code = $navDealer->getPost_Code()??'';
        $dealer->author()->associate($user);
        $dealer->save();
        $dealer->setCustomerCard($navDealer);
		return $dealer;
	}
	public function setPaymentType($dealer, Array $params)
	{
		$dealer->payment_type = $params['payment_type'];
		$dealer->save();
		return $dealer;
	}
	public function exportDealers($company, $distributor, $distributors)
	{
		$company_dealers = true;
		$distributor_ids = [];
		if($distributor == null)
		{
			$dealers = $company->dealers()->get();
		}	
		else
		{
			if($distributors != null)//#826
			{	
				foreach($distributors as $d)
				{
					$distributor_ids[] = $d['id'];
				}
				$dealers = Dealer::whereIn('distributor_id', $distributor_ids)->whereNull('deleted_at')->get();	
			}
			else
			{
				$dealers = $distributor->dealers;
			}
			$company_dealers = false;
		}

		if($dealers)
		{
			$spreadsheet = new Spreadsheet();
			if($company_dealers == false)
				$activeSheet = $spreadsheet->getActiveSheet()->setTitle("Dealers_Under_Distributor");
			else
				$activeSheet = $spreadsheet->getActiveSheet()->setTitle("Company_Dealers");
	        $activeSheet->setCellValue('A1' , 'NAV ID')->getStyle('A1')->getFont()->setBold(true);
	        $activeSheet->setCellValue('B1' , 'Name')->getStyle('B1')->getFont()->setBold(true);
	        $activeSheet->setCellValue('C1' , 'GSTIN')->getStyle('C1')->getFont()->setBold(true);
	        $activeSheet->setCellValue('D1' , 'Address')->getStyle('D1')->getFont()->setBold(true);  
	        $activeSheet->setCellValue('E1' , 'Address_2')->getStyle('E1')->getFont()->setBold(true);
	        $activeSheet->setCellValue('F1' , 'City')->getStyle('F1')->getFont()->setBold(true);  
	        $activeSheet->setCellValue('G1' , 'State')->getStyle('G1')->getFont()->setBold(true);
	        $activeSheet->setCellValue('H1' , 'Pincode')->getStyle('H1')->getFont()->setBold(true);
	        $activeSheet->setCellValue('I1' , 'Email')->getStyle('I1')->getFont()->setBold(true);   
	        $activeSheet->setCellValue('J1' , 'Phone #')->getStyle('J1')->getFont()->setBold(true);   
	        $activeSheet->setCellValue('K1' , 'Customer Type')->getStyle('K1')->getFont()->setBold(true);
	    	$activeSheet->setCellValue('L1' , 'Distributor NAV ID')->getStyle('L1')->getFont()->setBold(true);  
	        $activeSheet->setCellValue('M1' , 'Distributor Name')->getStyle('M1')->getFont()->setBold(true);
	        $activeSheet->setCellValue('N1' , 'Created Date')->getStyle('N1')->getFont()->setBold(true);
	        $activeSheet->setCellValue('O1' , 'Created Time')->getStyle('O1')->getFont()->setBold(true);
	    	$activeSheet->setCellValue('P1' , 'Area')->getStyle('P1')->getFont()->setBold(true);
			$activeSheet->setCellValue('Q1' , 'Sales Manager Name')->getStyle('Q1')->getFont()->setBold(true);
			$activeSheet->setCellValue('R1' , 'Priority')->getStyle('R1')->getFont()->setBold(true);      
	        $row = 2;
			foreach ($dealers as $dealer)
	        {
            	$activeSheet->setCellValue('A'.$row, $dealer->nav_id);
            	$activeSheet->setCellValue('B'.$row, $dealer->name);
            	$activeSheet->setCellValue('C'.$row, $dealer->gstin);
            	$activeSheet->setCellValue('D'.$row, $dealer->address);
            	$activeSheet->setCellValue('E'.$row, $dealer->address_2);
            	$activeSheet->setCellValue('F'.$row, $dealer->city_code);
            	$activeSheet->setCellValue('G'.$row, $dealer->state_code);
            	$activeSheet->setCellValue('H'.$row, $dealer->post_code);
            	$user = $dealer->users->first();
            	if($user)
            	{
                   	$activeSheet->setCellValue('I'.$row, $user->email);
            		$activeSheet->setCellValue('J'.$row, $user->phone);
            	}
            	$activeSheet->setCellValue('K'.$row, $dealer->type);
            	if($dealer->distributor)
	        	{
	            	$activeSheet->setCellValue('L'.$row, $dealer->distributor->nav_id);
	            	$activeSheet->setCellValue('M'.$row, $dealer->distributor->name);
	            }
	            $timezone = config('rosetta.time_zone');
	            $date = Carbon::createFromTimestamp($dealer->created_at->format('U'))->setTimezone($timezone);//#840

	           	$activeSheet->setCellValue('N'.$row, $date->format('d M Y'));//'Y-m-d'
	            $activeSheet->setCellValue('O'.$row, $date->format('h:i a'));//'Y-m-d'
				$activeSheet->setCellValue('P'.$row, $dealer->area);
				$activeSheet->setCellValue('Q'.$row, $dealer->sales_manager->name??'-');
				$activeSheet->setCellValue('R'.$row, $dealer->priority);
           		$row++;
	        }
	        for($col = 'A'; $col !== 'O'; $col++) 
	        {
	            $activeSheet->getColumnDimension($col)->setAutoSize(true);
	        }
	        $writer = new Xlsx($spreadsheet);
	        if($company_dealers == false)
	        {
	        	if($distributor_ids != null)
	        	{
	        		$distributor_ids_1 = implode(',', $distributor_ids);
	        		$file = storage_path() . '/app/Dealers_Under_Distributor_'.$distributor_ids_1.'.xlsx';
	        	}
	        	else
	        		$file = storage_path() . '/app/Dealers_Under_Distributor_'.$distributor->id.'.xlsx';
	        }
	        else
	        {	
	       		$file = storage_path() . '/app/Company_Dealers_'.$company->id.'.xlsx';
	        }
	        $writer->save($file);
	        return $file;
		}
	} //#813

	public function updateViaWhatsApp($company, $dealer, array $params)
	{
		//$dealer = $company->dealers()->find($dealerID);
		if ($dealer->distributor_id) {
			$distributor = $dealer->distributor;
		} else {
			$distributor = null;
		}

		$navDealer = $this->navCustomerService->updateDealer($company,$dealer, $params,$distributor);
		if (array_has($params, 'name')) {
			$dealer->name = $params['name'];
			if (array_has($params, 'gstin')) {
				if (isset($params['gstin'])) {
					$dealer->gstin = $params['gstin'];
				} else {
					$dealer->gstin = '';
				}
			} //#834
			if (array_has($params, 'contact'))
				$dealer->contact = $params['contact'];
			if (array_has($params, 'phone'))
				$dealer->phone = $params['phone'];
			if (array_has($params, 'email'))
				$dealer->email = $params['email'];
			if (array_has($params, 'credit_limit'))
				$dealer->credit_limit = $params['credit_limit'];
			if (array_has($params, 'overdue_balance'))
				$dealer->overdue_balance = $params['overdue_balance'];
			if (array_has($params, 'dealer_type')) {
				if ($params['dealer_type'] == "1")
					$dealer->type = 'Dealer';
				if ($params['dealer_type'] == "2")
					$dealer->type = 'Retail Customer';
			}
			if (array_has($params, 'address'))
				$dealer->address = $params['address'];
			if (array_has($params, 'address2'))
				$dealer->address_2 = $params['address2'];
			if (array_has($params, 'city'))
				$dealer->city_code = $params['city'];
			if (array_has($params, 'state'))
				$dealer->state_code = $params['state'];
			if (array_has($params, 'zipcode'))
				$dealer->post_code = $params['zipcode'];
			//#872
			if (array_has($params, 'area'))
				$dealer->area = $params['area'];
			if (array_has($params, 'updated_folder_given')) {
				if ($params['updated_folder_given'] == 'Y')
					$dealer->updated_folder_given = true;
				else
					$dealer->updated_folder_given = false;
			}
			if (array_has($params, 'app_explained')) {
				if ($params['app_explained'] == 'Y')
					$dealer->app_explained = true;
				else
					$dealer->app_explained = false;
			}
			if (array_has($params, 'notes'))
				$dealer->notes = $params['notes'];
			if(array_has($params, 'sales_manager_id'))
				$dealer->sales_manager_id = $params['sales_manager_id'];
			if(array_has($params, 'priority'))
				$dealer->priority = $params['priority'];
			//end of #872
			$dealer->save();
		}
		$dealer->setCustomerCard($navDealer);
		return $dealer;
	}
	
	public function createDealerFromWhatsApp(Array $params, Company $company, Distributor $distributor = null)
	{
		$navDealer = $this->navCustomerService->createDealer($company, $params,$distributor);
		$dealer = new Dealer;
		$dealer->name = $params['name'];
		if(array_has($params,'tax_information'))
		{
			$taxInformation = $params['tax_information'];
			if(array_has($taxInformation,'gst'))
			{
				$gst = $taxInformation['gst'];
				if(isset($gst['number']))
					$dealer->gstin = $gst['number'];
				else
					$dealer->gstin = '';
			}
		}//#834
		if(array_has($params, 'contact'))
			$dealer->contact = $params['contact'];
		if(array_has($params, 'phone'))
			$dealer->phone = $params['phone'];
		if(array_has($params, 'email'))
			$dealer->email = $params['email'];
		$dealer->post_code = $params['zipcode'];
		$dealer->state_code = $params['state'];
		$dealer->city_code = $params['city'];
		if(array_has($params, 'address'))
			$dealer->address = $params['address'];
		if(array_has($params, 'address2'))
			$dealer->address_2 = $params['address2'];
		if(array_has($params, 'dealer_type'))
		{
			if($params['dealer_type'] == "1")
				$dealer->type = 'Dealer';
			if($params['dealer_type'] == "2")
				$dealer->type = 'Retail Customer';
		}
		if(array_has($params, 'credit_limit'))
			$dealer->credit_limit = $params['credit_limit'];
		if(array_has($params, 'overdue_balance'))
			$dealer->overdue_balance = $params['overdue_balance'];
		$dealer->nav_id = $navDealer->getNo();
		if(array_has($params,'paymentTerms'))
		{
			$days = [
				'7 DAYS' => 7,
				'15 DAYS' => 15,
				'1 MONTH' => 30,
				'1.5 MONTH' => 45,
				'100% ADVC' => 0
			];
			$dealer->overdue_hold_days = 0;
			if(array_has($days, $params['paymentTerms']))
				$dealer->overdue_hold_days = $days[$params['paymentTerms']];
		}
		//#872
		if(array_has($params, 'area'))
			$dealer->area = $params['area'];
		if(array_has($params, 'updated_folder_given') && $params['updated_folder_given'] == 'Y')
			$dealer->updated_folder_given = true;
		if(array_has($params, 'app_explained') && $params['app_explained'] == 'Y')
			$dealer->app_explained = true;
        if(array_has($params, 'notes'))
			$dealer->notes = $params['notes'];
		if(array_has($params, 'sales_manager_id'))
			$dealer->sales_manager_id = $params['sales_manager_id'];
		if(array_has($params, 'priority'))
			$dealer->priority = $params['priority'];
		//end of #872
		$dealer->company()->associate($company);
		if($distributor != null)
			$dealer->distributor()->associate($distributor);
		// $dealer->author()->associate($user);
		$dealer->save();
		$this->staService->createAddress($company,$dealer->nav_id,$params);//#842
		return $dealer;
	}	
}