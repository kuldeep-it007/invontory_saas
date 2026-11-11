<?php
namespace Rosetta\Services\Users;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as SMSNotification;
use Predis\Connection\ConnectionException;
use Rosetta\Exceptions\RedisException;
use Rosetta\Exceptions\User\DeleteUserPermissionDeniedException;
use Rosetta\Mail\PasswordReset;
use Rosetta\Models\Prospect;
use Rosetta\Models\Role;
use Rosetta\Models\User;
use Rosetta\Notifications\SMS\PasswordResetNotification;
use Rosetta\Services\Companies\CompanyServiceInterface;
use Rosetta\Services\Dealer\DealerServiceInterface;
use Rosetta\Services\Distributor\DistributorServiceInterface;
use Rosetta\Services\SuperStockist\SuperStockistServiceInterface;
use Rosetta\Services\Users\UserServiceInterface;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Providers\config;

class UserService implements UserServiceInterface
{
	use HasRoles;
	protected $companyService, $distributorService, $dealerService, $superStockistService;
	public function __construct(CompanyServiceInterface $companyService, DistributorServiceInterface $distributorService, DealerServiceInterface $dealerService, SuperStockistServiceInterface $superStockistService)
	{
		$this->companyService = $companyService;
		$this->distributorService = $distributorService;
		$this->dealerService = $dealerService;
		$this->superStockistService = $superStockistService;
	}
	public function reset(Array $params, $userId)
	{
		$user = $this->find($userId);
		$user->password = bcrypt($params['new_password']);
		$user->change_password = false;
		$user->save();
		if($user->distributor)
		{
			$customer = $user->distributor;
		}
		if($user->dealer)
		{
			$customer = $user->dealer;
		}			

		$notifiable = (object)[
            "number" => $user->phone,
            "customer" => $customer->name
        ];
        SMSNotification::send($notifiable,new PasswordResetNotification($customer->company));//#595
		return $user;
	}
	public function resetCustomerPassword(Array $params, $userId)
	{
		$user = $this->find($userId);
		$user->password = bcrypt($params['new_password']);
		$user->change_password = false;
		$user->save();

		if($user->distributor)
		{
			$customer = $user->distributor;
		}
		if($user->dealer)
		{
			$customer = $user->dealer;
		}			
		if($user->superStockist)
		{
			$customer = $user->superStockist;
		}
		$notifiable = (object)[
            "number" => $user->phone,
            "customer" => $customer->name
        ];
        SMSNotification::send($notifiable,new PasswordResetNotification($customer->company));//#595
		$queuePrefix = config('brand.queue_prefix');
		$queue = $queuePrefix.'.emails';
		$Qmessage = (new PasswordReset($user->communication_email,$params['new_password'],$user->name))->onQueue($queue);
		try
	    {
	    	if(!empty($user->communication_email))
        		Mail::to($user->communication_email)->queue($Qmessage);
       	}
       	catch(ConnectionException $e)
       	{
       		throw new RedisException;
	    }
		return $user;
	}
	public function update(Array $params, $userId)
	{
		$user = $this->find($userId);
		if(array_has($params,'active'))
			$user->active = $params['active'];
		if(array_has($params,'email'))
			$user->email = $params['email'];//#751
		if(array_has($params,'phone'))
			$user->phone = $params['phone'];//#751
		$user->save();
		return $user;
	}
	public function delete($id, User $loggedInUser)
	{
		$user = User::findorFail($id);
		if($user->hasRole('superadmin') || $user->hasRole('distributorAdmin') || $user->hasRole('dealerAdmin') || $user->hasRole('companyAdmin'))
		{
			throw new DeleteUserPermissionDeniedException;	
		}
		else
			$user->delete();
        return $user;
	}
	public function create(User $loggedInUser, Array $params, $type, $customer)
	{
		//After upgrading to laravel 6.0 zizaco roles is replaced by spatie as laravel 6..0 doesn't support zizaco. Hence, Role assigning is changed.
		$newUser = new User;
		$newUser->email = $params['email'];
		$newUser->communication_email = $params['email'];
		if(array_has($params, 'username'))
			$newUser->name = $params['username'];
		else
			$newUser->name = $customer->name;
		if(array_has($params, 'password'))
			$newUser->password = bcrypt($params['password']);
		else
			$newUser->password = bcrypt('passw0rd!');
		if(array_has($params,'active'))
			$newUser->active = $params['active'];
		if(array_has($params,'phone'))
			$newUser->phone = $params['phone'];
		if($type == null)
		{
			if($loggedInUser->hasRole('superadmin'))
			{
				$newUser->assignRole('superadmin');
			}
			elseif($loggedInUser->hasRole('companyAdmin'))
			{
				$newUser->assignRole('companyAdmin');
				$company = $this->companyService->find($loggedInUser->company_id);
				$newUser->company()->associate($company);
			}
			elseif($loggedInUser->hasRole('distributorAdmin'))
			{
				$newUser->assignRole('distributorAdmin');
				$distributor = $this->distributorService->find($loggedInUser->distributor_id);
				$newUser->distributor()->associate($distributor);
			}
			elseif ($loggedInUser->hasRole('dealerAdmin'))
			{
				$newUser->assignRole('dealerAdmin');
				$dealer = $this->dealerService->find($loggedInUser->dealer_id);
				$newUser->dealer()->associate($dealer);
			}
			elseif($loggedInUser->hasRole('superStockistAdmin'))
			{
				$newUser->assignRole('superStockistAdmin');
				$superStockist = $this->superStockistService->find($loggedInUser->super_stockist_id);
				$newUser->superStockist()->associate($superStockist);
			}
		}
		else
		{
			if($type == 'company')
			{
				$newUser->assignRole('companyAdmin');
				$newUser->company()->associate($customer);
			}
			elseif($type == 'distributor')
			{
				if(array_has($params, 'role') && $params['role'] == 'salesRep')
				{
					$newUser->assignRole('distributorSalesRep');
				}
				else
					$newUser->assignRole('distributorAdmin');
				$newUser->distributor()->associate($customer);
			}
			elseif ($type == 'dealer')
			{
				if(array_has($params, 'role') && $params['role'] == 'salesRep')
				{
					$newUser->assignRole('dealerSalesRep');
				}
				else
					$newUser->assignRole('dealerAdmin');
				$newUser->dealer()->associate($customer);
			}	
			elseif ($type == 'dealerUnderDistributor')
			{
				if(array_has($params, 'role') && $params['role'] == 'salesRep')
				{
					$newUser->assignRole('dealerSalesRep');
				}
				else
					$newUser->assignRole('dealerAdmin');
				$newUser->change_password = true;
				$newUser->dealer()->associate($customer);
			}
			elseif($type == 'superStockist')
			{
				if(array_has($params, 'role') && $params['role'] == 'salesRep')
				{
					$newUser->assignRole('superStockistSalesRep');
				}
				else
					$newUser->assignRole('superStockistAdmin');
				$newUser->superStockist()->associate($customer);
			}		
		}
		$newUser->save();
	//	$newUser->roles()->sync($newUserRole);
		return $newUser;
	}
	public function find(int $id)
	{
		$user = User::find($id);
		if(!$user)
		{
			throw new ModelNotFoundException;
		}
		return $user;
	}
	public function all(Array $params = [], $entityId=null, $entityType=null)
	{
		if($entityId === null)
		{
			if(array_has($params,'page'))
			{
				return User::paginate($params['per_page'],['*'],'',$params['page']);
			}
			return User::all();
		}
		if($entityType == "distributor")
		{
			if(array_has($params,'page'))
			{
				return $users = User::when($params, function ($query) use ($params, $entityId){
					$query->where('distributor_id', '=', $entityId);
				})->paginate($params['per_page']);
			}
			else
			{
				return $users = User::when($params, function ($query) use ($params,$entityId){
					$query->where('distributor_id', '=', $entityId);
				})->get();
			}
		}
		else if($entityType == "superStockist")
		{
			if(array_has($params,'page'))
			{
				return $users = User::when($params, function ($query) use ($params, $entityId){
					$query->where('super_stockist_id', '=', $entityId);
				})->paginate($params['per_page']);
			}
			else
			{
				return $users = User::when($params, function ($query) use ($params,$entityId){
					$query->where('super_stockist_id', '=', $entityId);
				})->get();
			}
		}
		else if($entityType == "dealer")
		{
			if(array_has($params,'page'))
			{
				return $users = User::when($params, function ($query) use ($params, $entityId){
					$query->where('dealer_id', '=', $entityId);
				})->paginate($params['per_page']);
			}
			else
			{
				return $users = User::when($params, function ($query) use ($params){
					$query->where('dealer_id', '=', $entityId);
				})->get();
			}
		}
		else if($entityType == "company")
		{
			if(array_has($params,'page'))
			{
				return $users = User::when($params, function ($query) use ($params, $entityId){
					$query->where('company_id', '=', $entityId);
				})->paginate($params['per_page']);
			}
			else
			{
				return $users = User::when($params, function ($query) use ($params){
					$query->where('company_id', '=', $entityId);
				})->get();
			}
		}
	}

	public function impersonate(User $user, $userID)
	{
		return $this->find($userID);
	}
	public function signUp(Array $params, $userId)//#785
	{
		$newProspect = new Prospect;
		if(array_has($params, 'name'))
			$newProspect->name = $params['name'];
		if(array_has($params, 'business_name'))
			$newProspect->business_name = $params['business_name'];
		if(array_has($params, 'type'))
			$newProspect->type = $params['type'];
		if(array_has($params, 'email'))
			$newProspect->email = $params['email'];
		if(array_has($params,'phone'))
			$newProspect->phone = $params['phone'];
		if(array_has($params,'receive_updates'))
			$newProspect->receive_updates = $params['receive_updates'];
		if(array_has($params,'gst_no'))
			$newProspect->gst_no = $params['gst_no'];
		if(array_has($params,'pan'))
			$newProspect->pan = $params['pan'];
		if(array_has($params,'street'))
			$newProspect->street = $params['street'];
		if(array_has($params,'floor'))
			$newProspect->floor_unit = $params['floor'];
		if(array_has($params,'city'))
			$newProspect->city = $params['city'];
		if(array_has($params,'state'))
			$newProspect->state = $params['state'];
		if(array_has($params,'post_code'))
			$newProspect->post_code = $params['post_code'];
		if(array_has($params,'country_code'))
			$newProspect->country_code = $params['country_code'];
		if(array_has($params,'password'))
			$newProspect->password = bcrypt($params['password']);
		$newProspect->save();
		return $newProspect;
	}
	public function listProspects(Array $params)
	{
		if(array_has($params,'page'))
		{
			$prospects = Prospect::when($params, function ($query) use ($params){
				if(array_has($params, 'email'))
				{
					$query->where('email', '=', $params['email']);
				}
				if(array_has($params, 'phone'))
				{
					$query->where('phone', '=', $params['phone']);
				}
			})->orderBy('created_at','asc')->paginate($params['per_page']);
		}
		else
		{
			$prospects = Prospect::when($params, function ($query) use ($params){
				if(array_has($params, 'email'))
				{
					$query->where('email', '=', $params['email']);
				}
				if(array_has($params, 'phone'))
				{
					$query->where('phone', '=', $params['phone']);
				}
			})->get();
		}
		return $prospects;
	}
	public function deleteProspect($id)
	{
		$prospect = Prospect::findorFail($id);	
		$prospect->forceDelete();
        return $prospect;
	}

	public function createUserFromWhatsApp(Array $params, $type, $customer)
	{
		//After upgrading to laravel 6.0 zizaco roles is replaced by spatie as laravel 6..0 doesn't support zizaco. Hence, Role assigning is changed.
		$newUser = new User;
		$newUser->email = $params['email'];
		$newUser->communication_email = $params['email'];
		if(array_has($params, 'username'))
			$newUser->name = $params['username'];
		else
			$newUser->name = $customer->name;
		if(array_has($params, 'password'))
			$newUser->password = bcrypt($params['password']);
		else
			$newUser->password = bcrypt('passw0rd!');
		if(array_has($params,'active'))
			$newUser->active = $params['active'];
		if(array_has($params,'phone'))
			$newUser->phone = $params['phone'];
			if($type == 'company')
			{
				$newUser->assignRole('companyAdmin');
				$newUser->company()->associate($customer);
			}
			elseif($type == 'distributor')
			{
				if(array_has($params, 'role') && $params['role'] == 'salesRep')
				{
					$newUser->assignRole('distributorSalesRep');
				}
				else
					$newUser->assignRole('distributorAdmin');
				$newUser->distributor()->associate($customer);
			}
			elseif ($type == 'dealer')
			{
				if(array_has($params, 'role') && $params['role'] == 'salesRep')
				{
					$newUser->assignRole('dealerSalesRep');
				}
				else
					$newUser->assignRole('dealerAdmin');
				$newUser->dealer()->associate($customer);
			}	
			elseif ($type == 'dealerUnderDistributor')
			{
				if(array_has($params, 'role') && $params['role'] == 'salesRep')
				{
					$newUser->assignRole('dealerSalesRep');
				}
				else
					$newUser->assignRole('dealerAdmin');
				$newUser->change_password = true;
				$newUser->dealer()->associate($customer);
			}
			elseif($type == 'superStockist')
			{
				if(array_has($params, 'role') && $params['role'] == 'salesRep')
				{
					$newUser->assignRole('superStockistSalesRep');
				}
				else
					$newUser->assignRole('superStockistAdmin');
				$newUser->superStockist()->associate($customer);
			}
		$newUser->save();
		return $newUser;
	}	
}