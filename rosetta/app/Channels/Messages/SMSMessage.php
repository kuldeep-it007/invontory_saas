<?php
namespace Rosetta\Channels\Messages;

use Rosetta\Models\Company;

class SMSMessage
{

	protected $to = [];
	protected $message = "";
	protected $shortCode = "ROSETA";
	protected $smsService;
	protected $company;
	public function __construct(){

	}
	public function to($to)
	{
		$this->to[] = $to;
		return $this;
	}
	public function message($message)
	{
		$this->message = $message;
		return $this;
	}
	public function company(Company $company)
	{
		$this->company = $company;
		return $this;
	}
	public function getCompany()
	{
		return $this->company;
	}
	public function send()
	{

	}

	public function getTo()
	{
		return $this->to;
	}

	public function getMessage()
	{
		return $this->message;
	}
}