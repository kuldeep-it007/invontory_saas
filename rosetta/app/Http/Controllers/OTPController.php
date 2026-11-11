<?php

namespace Rosetta\Http\Controllers;

use Illuminate\Http\Request;
use Rosetta\Services\OTPServiceInterface;

class OTPController extends Controller
{
	protected $otpService;

	public function __construct(OTPServiceInterface $otpService)
	{
		$this->otpService = $otpService;
	}
    public function create(Request $request)
    {
    	$token = $this->otpService->send($request->get('phone'));
    	return [
    		"otp_token" => $token->get(),
    	];
    }
}
