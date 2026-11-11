<?php
namespace Rosetta\Services;
interface OTPServiceInterface
{
	public function send($phone);
	public function verify($token,$otp);
}