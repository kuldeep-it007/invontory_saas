<?php
namespace Rosetta\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Rosetta\Exceptions\InvalidOTPException;
use Rosetta\Notifications\OTPNotification;
use Rosetta\Services\OTPServiceInterface;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;
use Tymon\JWTAuth\Token;
class OTPService implements OTPServiceInterface
{
	public function send($phone)
	{
		$otp = mt_rand(1000, 9999);
		Notification::send((Object)[
			"otp" => $otp,
			"number" => $phone,
		],
		new OTPNotification);
		$customClaims = [
			"sub" => $phone,
			"otp" => $otp,
			"exp" => Carbon::now()->addMinutes(30)->format('U'),
		];

		$payload = JWTFactory::make($customClaims);
		$token = JWTAuth::encode($payload);
		return $token;
	}

	public function verify($token,$otp)
	{
		$jwtToken = JWTAuth::setToken(new Token($token));
		$payload = JWTAuth::getPayLoad();
		$setOtp = $payload->get('otp');
		$setNumber = $payload->get('sub');
		if($setOtp != $otp)
			throw new InvalidOTPException;

		return $setNumber;
	}
}