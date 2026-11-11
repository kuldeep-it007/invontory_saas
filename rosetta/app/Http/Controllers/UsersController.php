<?php

namespace Rosetta\Http\Controllers;

use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Pusher\Pusher;
use Rosetta\Exceptions\User\DeleteUserPermissionDeniedException;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\Notification;
use Rosetta\Models\Prospect;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Services\Users\UserServiceInterface;
use Rosetta\Transformers\Notification\NotificationsTransformer;
use Rosetta\Transformers\ProspectTransformer;
use Rosetta\Transformers\User\AbilitiesTransformer;
use Rosetta\Transformers\User\ImpersonateTransformer;
use Rosetta\Transformers\User\IndexTransformer;
use Rosetta\Transformers\User\SelfTransformer;
use Tymon\JWTAuth\Facades\JWTAuth;

class UsersController extends Controller
{
  //  use Nav;
    // const LOCATION = 'Page/Customer_Card';
      //  const WSDL = 'CustomerCard';
    protected $userService;
    public function __construct(UserServiceInterface $userService)
    {
        $this->userService = $userService;
    }
    public function self(Request $request)
    {
     //   throw new Exception();
        return fractal()
            ->item(Auth::user())
            ->transformWith(new SelfTransformer())
            ->parseIncludes(['abilities','distributor','distributor.cart','distributor.company','distributor.company.region','dealer','dealer.company', 'dealer.company.region', 'dealer.cart','company', 'company.region','distributor.paymentTypes','dealer.paymentTypes','notice','superStockist', 'superStockist.company', 'superStockist.company.region','superStockist.paymentTypes','superStockist.cart'])
            ->serializeWith(new \Spatie\Fractalistic\ArraySerializer())
            ->toArray();
    }   
    public function selfAbilities(Request $request)
    {
        return fractal()
            ->item(Auth::user())
            ->transformWith(new AbilitiesTransformer())
            ->serializeWith(new \Spatie\Fractalistic\ArraySerializer())
            ->toArray();
    }
    public function get($id)
    {
        $user = $this->userService->find($id);
        return fractal()
                ->item($user)
                ->transformWith(new IndexTransformer())
                ->toArray();
    }
    public function reset(Request $request, $userId)//user resets his password
    {   
        if(!Gate::allows('updateUser'))
        {
            return response()->json("", 403);
        }
        $validator = Validator::make($request->all(), [
            "current_password"    => 'required',
            "new_password" => 'required'
        ]);   
        if ($validator->fails())
        {
            return response()->json($validator->errors(),400);
        }
        $user = Auth::user();
        if(!Hash::check($request->get('current_password'),$user->password))
        {
            return response()->json([
                "current_password" => ["Password does not match"]
            ],404);
        }
        $user = $this->userService->reset($request->all(), $userId);
        return fractal()
            ->item($user)
            ->transformWith(new SelfTransformer())
            ->toArray();
    }
    public function resetCustomerPassword(Request $request, $userId)//when superadmin/company admin resets password for users
    {   
        if(!Gate::allows('updateUser'))
        {
            return response()->json("", 403);
        }
        $validator = Validator::make($request->all(), [
            "new_password" => 'required'
        ]);   
        if ($validator->fails())
        {
            return response()->json($validator->errors(),400);
        }
        $user = $this->userService->resetCustomerPassword($request->all(), $userId);
        return fractal()
            ->item($user)
            ->transformWith(new SelfTransformer())
            ->toArray();
    }
    public function update(Request $request, $userId)
    {   
        if(!Gate::allows('updateUser'))
        {
            return response()->json("", 403);
        }
        $validator = Validator::make($request->all(), [
            "email" => ['nullable',
                Rule::unique('users')->ignore($userId)->where(function($query){
                    return $query->whereNull('deleted_at');
                }),
            ],
            "phone" => ['nullable',
                Rule::unique('users')->ignore($userId)->where(function($query){
                    return $query->whereNull('deleted_at');
                }),
            ],
        ]);//#751
        if ($validator->fails())
        {
            return response()->json($validator->errors(),400);
        }
        $user = $this->userService->update($request->all(), $userId);
        return fractal()
            ->item($user)
            ->transformWith(new SelfTransformer())
            ->toArray();
    }
    public function delete($id)
    {   
        if(!Gate::allows('deleteUser'))
            return response()->json("", 403);
        try
        {
            $user = $this->userService->delete($id, Auth::user());
        }
        catch(ModelNotFoundException $e)
        {
            return response()->json("", 404);
        }
        catch(DeleteUserPermissionDeniedException $e)
        {
            return response()->json("Admin User can not be deleted!",412);
        }
        return $user;
    }
    public function deleteUser($companyId, $customerType, $customerId, $userId)
    {   
        if(!Gate::allows('deleteUser'))
            return response()->json("", 403);
        try
        {
            $user = $this->userService->delete($userId, Auth::user());
        }
        catch(ModelNotFoundException $e)
        {
            return response()->json("", 404);
        }
        catch(DeleteUserPermissionDeniedException $e)
        {
            return response()->json("Admin User can not be deleted!",412);
        }
        return $user;
    }
    public function deleteDealerUser($dealerId, $userId)
    {   
        if(!Gate::allows('deleteUser'))
            return response()->json("", 403);
        try
        {
            $user = $this->userService->delete($userId, Auth::user());
        }
        catch(ModelNotFoundException $e)
        {
            return response()->json("", 404);
        }
        catch(DeleteUserPermissionDeniedException $e)
        {
            return response()->json("Admin User can not be deleted!",412);
        }
        return $user;
    }
    public function create(Request $request)
    {
        if(!Gate::allows('createUser'))
        {
            return response()->json("", 403);
        }
        $validator = Validator::make($request->all(), [
            "email" => 'unique:users,email',
            "username" => 'required',
        ]);   
        if ($validator->fails())
        {
            return response()->json($validator->errors(),400);
        }
        try
        {
            $newUser = $this->userService->create(Auth::user(), $request->all(), null, null);
        }
        catch(ModelNotFoundException $e)
        {
            return response()->json("", 404);
        }
        return fractal()
            ->item($newUser)
            ->transformWith(new IndexTransformer())
            ->parseIncludes(['distributor', 'dealer', 'company'])
            ->toArray();
    }
    public function createUser(Request $request, $companyId, $customerType, $customerId)
    {
        if(!Gate::allows('createUser'))
        {
            return response()->json("", 403);
        }
        $validator = Validator::make($request->all(), [
            "email" => ['nullable',
                Rule::unique('users')->where(function($query){
                    return $query->whereNull('deleted_at');
                }),
            ],
            "phone" => ['nullable',
                Rule::unique('users')->where(function($query){
                    return $query->whereNull('deleted_at');
                }),
            ],
            "username" => 'required'
        ]);


        if ($validator->fails())
        {
            return response()->json($validator->errors(),400);
        }
        try
        {
            if($customerType == 'distributors')
            {
                $customer = Distributor::find($customerId);
                $type = 'distributor';
            }
            if($customerType == 'dealers')
            {
                $customer = Dealer::find($customerId);
                $type = 'dealer';
            }
            if($customerType == 'super_stockists')
            {
                $customer = SuperStockist::find($customerId);
                $type = 'superStockist';
            }
            $newUser = $this->userService->create(Auth::user(), $request->all(), $type, $customer);
        }
        catch(ModelNotFoundException $e)
        {
            return response()->json("", 404);
        }
        return fractal()
            ->item($newUser)
            ->transformWith(new IndexTransformer())
            ->parseIncludes(['distributor', 'dealer', 'company', 'superStockist'])
            ->toArray();
    }
    public function createDealerUser(Request $request, $dealerId)
    {
        if(!Gate::allows('createUser'))
        {
            return response()->json("", 403);
        }

        $validator = Validator::make($request->all(), [
            "email" => ['nullable',
                Rule::unique('users')->where(function($query){
                    return $query->whereNull('deleted_at');
                }),
            ],
            "phone" => ['nullable',
                Rule::unique('users')->where(function($query){
                    return $query->whereNull('deleted_at');
                }),
            ],
            "username" => 'required'
        ]);


        if ($validator->fails())
        {
            return response()->json($validator->errors(),400);
        }
        try
        {
            $customer = Dealer::find($dealerId);
            $type = 'dealer';
            $newUser = $this->userService->create(Auth::user(), $request->all(), $type, $customer);
        }
        catch(ModelNotFoundException $e)
        {
            return response()->json("", 404);
        }
        return fractal()
            ->item($newUser)
            ->transformWith(new IndexTransformer())
            ->parseIncludes(['distributor', 'dealer', 'company'])
            ->toArray();
    }
    public function index(Request $request)
    {
        if(!Gate::allows('viewUser'))
        {
            return response()->json("",403);
        }
        $loggedInUser = Auth::user();
        if($loggedInUser->hasRole('superadmin'))
        {
            $users = $this->userService->all($request->all());
        }
        else if($loggedInUser->hasRole('companyAdmin') || $loggedInUser->hasRole('companySalesRep'))
        {
            $companyId = $loggedInUser->company_id;
            $users = $this->userService->all($request->all(), $companyId, "company");
        }
        else if($loggedInUser->hasRole('distributorAdmin') || $loggedInUser->hasRole('distributorSalesRep'))
        {
            $distributorId = $loggedInUser->distributor_id;
            $users = $this->userService->all($request->all(), $distributorId, "distributor");
        }
        else if($loggedInUser->hasRole('dealerAdmin') || $loggedInUser->hasRole('dealerSalesRep'))
        {
            $dealerId = $loggedInUser->dealer_id;
            $users = $this->userService->all($request->all(), $dealerId, "dealer");
        }
        $usersArray =  fractal()
            ->collection($users)
            ->transformWith(new IndexTransformer())
            ->parseIncludes(['distributor', 'dealer', 'company'])
            ->serializeWith(new \Spatie\Fractalistic\ArraySerializer())
            ->toArray();

        if($request->get('page'))
        {
            return [
                "total" => $users->total(),
                "page"  => $users->currentPage(),
                "users" => $usersArray,
            ];
        }
        else
            return $usersArray;
    }
    public function pusherAuth(Request $request)
    {
        $pusher = new Pusher(
            config('broadcasting.connections.pusher.key'), 
            config('broadcasting.connections.pusher.secret'),
            config('broadcasting.connections.pusher.app_id'),
            [
                'cluster' => config('broadcasting.connections.pusher.options.cluster'), 
                'encrypted' => true 
            ]
        );
        echo $pusher->socket_auth($request->get('channel_name'), $request->get('socket_id'));
    }
    public function getAllNotifications($id)
    {
        $notifications = Notification::where('recipient_id', '=', $id)->where('read', '=', 0)->orderBy('created_at', 'DESC')->get();
        $notificationsArray = fractal()
            ->collection($notifications)
            ->transformWith(new NotificationsTransformer())
            ->toArray();
        return $notificationsArray;
    }
    public function list(Request $request, $companyId, $customerType, $customerId)
    {
        if(!Gate::allows('viewUser'))
        {
            return response()->json("",403);
        }
        if($customerType == 'distributors')
            $users = $this->userService->all($request->all(), $customerId, "distributor");
        else if($customerType == 'dealers')
            $users = $this->userService->all($request->all(), $customerId, "dealer");
        else if($customerType == 'super_stockists')
            $users = $this->userService->all($request->all(), $customerId, "superStockist");

        $usersArray =  fractal()
            ->collection($users)
            ->transformWith(new IndexTransformer())
            ->parseIncludes(['distributor', 'dealer', 'company', 'superStockist'])
            ->serializeWith(new \Spatie\Fractalistic\ArraySerializer())
            ->toArray();

        if($request->get('page'))
        {
            return [
                "total" => $users->total(),
                "page"  => $users->currentPage(),
                "users" => $usersArray,
            ];
        }
        else
            return $usersArray;
    }
    public function dealerUsers(Request $request, $dealerId)
    {
        if(!Gate::allows('viewUser'))
        {
            return response()->json("",403);
        }
        $users = $this->userService->all($request->all(), $dealerId, "dealer");

        $usersArray =  fractal()
            ->collection($users)
            ->transformWith(new IndexTransformer())
            ->parseIncludes(['distributor', 'dealer', 'company'])
            ->serializeWith(new \Spatie\Fractalistic\ArraySerializer())
            ->toArray();

        if($request->get('page'))
        {
            return [
                "total" => $users->total(),
                "page"  => $users->currentPage(),
                "users" => $usersArray,
            ];
        }
        else
            return $usersArray;
    }

    public function impersonate(Request $request)
    {
        if(!Gate::allows('impersonate'))
        {
            return response()->json("",403);
        }
        $user = $this->userService->impersonate(Auth::user(),$request->get('user'));
        $token = JWTAuth::fromUser($user);
        $out = fractal()
            ->item($user)
            ->transformWith(new ImpersonateTransformer)
            ->parseIncludes(['self.abilities','self.distributor','self.distributor.cart','self.distributor.company','self.distributor.company.region','self.dealer','self.dealer.company', 'self.dealer.company.region', 'self.dealer.cart','self.company', 'self.company.region','self.distributor.paymentTypes','self.dealer.paymentTypes','self.superStockist','self.superStockist.company','self.superStockist.company.region','self.superStockist.paymentTypes'])
            ->serializeWith(new \Spatie\Fractalistic\ArraySerializer())
            ->toArray();
        return ['token' => $token,"data" => $out];
    }
    public function signUp(Request $request)//#785
    {
     /*   $gst = $request['gst_no'];
        $company = Company::find('3');
        
       
        $customerCardService = new Customer_Card_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
        $customerCardFilter = new Customer_Card_Filter(Customer_Card_Fields::GST_Registration_No,$gst);
        $readMultiple = new ReadMultiple($customerCardFilter,null,null);
        $customer = $customerCardService->ReadMultiple($readMultiple)->getReadMultiple_Result()->getCustomer_Card();

        if($customer == null)
            return [];
        if(!is_array($customer))
        {
            return [$customer->getNo()];
        }
        else
        {
            return $customer->getNo();
        }
*/
        $validator = Validator::make($request->all(), [
            "email" => ['nullable',
                Rule::unique('users')->where(function($query){
                    return $query->whereNull('deleted_at');
                }),
            ],
            "phone" => ['nullable',
                Rule::unique('users')->where(function($query){
                    return $query->whereNull('deleted_at');
                }),
            ],
        ]);
        if ($validator->fails())
        {
            return response()->json($validator->errors(),400);
        }
        try
        {
            $prospect_exist = Prospect::where('email','=',$request['email'])->first();
            if($prospect_exist)
            {
                return response()->json([
                    "email" => ["Thank you for signing in, we have your email and you would be contacted soon."]
                ],404);
            }
            else
            {
                $prospect_exist = Prospect::where('phone','=',$request['phone'])->first();
                if($prospect_exist)
                {
                    return response()->json([
                        "phone" => ["Thank you for signing in, we have you phone and your would be contacted soon."]
                    ],404);
                } 
                else
                    $newProspect = $this->userService->signUp($request->all(),Auth::user());
            }            
        }
        catch(ModelNotFoundException $e)
        {
            return response()->json("", 404);
        }
        return fractal()
            ->item($newProspect)
            ->transformWith(new ProspectTransformer())
            ->toArray();
    }
    public function listProspects(Request $request)
    {
        $prospects = $this->userService->listProspects($request->all());

        $prospectsArray =  fractal()
            ->collection($prospects)
            ->transformWith(new ProspectTransformer())
            ->toArray();

        if($request->get('page'))
        {
            return [
                "total" => $prospects->total(),
                "page"  => $prospects->currentPage(),
                "prospects" => $prospectsArray,
            ];
        }
        else
            return $prospectsArray;
    }
    public function deleteProspect($id)
    {
        try
        {
            $this->userService->deleteProspect($id);
        }
        catch(ModelNotFoundException $e)
        {
            return response()->json("Record not found",404);
        }
    }
}