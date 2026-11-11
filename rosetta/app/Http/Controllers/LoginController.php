<?php

namespace Rosetta\Http\Controllers;

use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Rosetta\Exceptions\InvalidOTPException;
use Rosetta\Models\Setting;
use Rosetta\Models\User;
use Rosetta\Services\OTPServiceInterface;
use Rosetta\Transformers\LoginResponseTransformer;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Validator;

class LoginController extends Controller
{
    protected $otpService;
    public function __construct(OTPServiceInterface $otpService)
    {
        $this->otpService = $otpService;
    }
    public function postOTPLogin(Request $request)
    {
        try{
            $phone = $this->otpService->verify($request->get('token'),$request->get('otp'));
        }
        catch(TokenInvalidException $e)
        {
            return response("",401);
        }
        catch(TokenExpiredException $e)
        {
            return response("",401);
        }
        catch(InvalidOTPException $e)
        {
            return response("",401);
        }
        $user = User::where('phone','=',$phone)->first();
        if(!$user)
        {
            return response("",401);
        }
        if(! $token = JWTAuth::fromUser($user))
        {
            return response("",401);   
        }
        $user->change_password = false;
        $user->save();
        Auth::login($user);
        $out = fractal()
            ->item(Auth::user())
            ->transformWith(new LoginResponseTransformer())
            ->serializeWith(new \Spatie\Fractalistic\ArraySerializer())
            ->toArray();
        $out['token'] = $token;
        return $out;
    }
    /**
	 * Login api
	 * @param  Request $request [description]
	 * @return json for logged in user
	 */
    public function postLogin(Request $request)
    {
       
    	$validator = Validator::make($request->all(), [
			"email"    => "required",
			"password" => 'required',
        ]);
        
        if ($validator->fails())
        {
            return response()->json($validator->errors(),400);
        }
        $credentials = $request->only('email', 'password');
        
        try {
            // verify the credentials and create a token for the user
            if(preg_match('/@/',$credentials['email']))
            {
                if (! $token = JWTAuth::attempt($credentials)) {
                    return response("",401);
                }
            }
            else
            {
                $user = User::where('phone','=',$credentials['email'])->first();
                if(!$user)
                {
                    return response("",401);
                }
                if(!Hash::check($credentials['password'],$user->password))
                {
                    return response("",401);
                }
                if(! $token = JWTAuth::fromUser($user))
                {
                    return response("",401);   
                }
                Auth::login($user);
            }
        } catch (JWTException $e) {
            return response()->json(['error' => 'could_not_create_token'], 500);
        }
    	$out = fractal()
   			->item(Auth::user())
   			->transformWith(new LoginResponseTransformer())
   			->serializeWith(new \Spatie\Fractalistic\ArraySerializer())
   			->toArray();
   		$out['token'] = $token;
   		return $out;
    }
    public function validateToken(Request $request)
    {
        $token = $request->get('token');
        $system_token = Setting::where('key','=','token')->pluck('value');
        if($token == $system_token[0])
            return response()->json([],200);
        else
            return response("",401);
    }
}
