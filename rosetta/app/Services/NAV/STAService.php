<?php
namespace Rosetta\Services\NAV;
use Rosetta\Models\Company;
use Rosetta\NAV\WS\STA\Create;
use Rosetta\NAV\WS\STA\Delete;
use Rosetta\NAV\WS\STA\Read;
use Rosetta\NAV\WS\STA\ReadMultiple;
use Rosetta\NAV\WS\STA\STA;
use Rosetta\NAV\WS\STA\STA_Fields;
use Rosetta\NAV\WS\STA\STA_Filter;
use Rosetta\NAV\WS\STA\STA_Service;
use Rosetta\NAV\WS\STA\Update;
use Rosetta\NAV\WS\STA\getCode;
use Rosetta\Services\NAV\STAServiceInterface;
use Rosetta\Traits\Nav;
class STAService implements STAServiceInterface
{
	use Nav;
	const LOCATION = 'Page/STA';
	const WSDL = 'STA';
	public function getAllAddresses(Company $company,String $nav_id)
	{

		$staService = new STA_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$filter = new STA_Filter(STA_Fields::Customer_No,$nav_id);
		$readMultiple = new ReadMultiple($filter,null,null);
		$addresses = $staService->ReadMultiple($readMultiple)->getReadMultiple_Result()->getSTA();
		if($addresses === null)
			return [];
		if(!is_array($addresses))
			return [$addresses];
		return $addresses;
	}
	public function createAddress(Company $company,String $nav_id,Array $params)
	{
		$address = new STA;
		list($cust,$no) = explode("/",$nav_id);
		$code = substr("{$no}-".strrev(time()),0,10);
		$address->setCode($code);
		$address->setCustomer_No($nav_id);
		$address = $this->setParameters($address, $params);
		$createSTA = new Create($address);
		$staService = new STA_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$newAddress = $staService->Create($createSTA);
		return $newAddress->getSTA();
	}
	public function setParameters($address, Array $params)
	{
		if(array_has($params, 'address'))
			$address->setAddress($params['address']);
		if(array_has($params, 'address_2'))
			$address->setAddress_2($params['address_2']);
		if(array_has($params, 'address2'))
			$address->setAddress_2($params['address2']);
		if(array_has($params, 'post_code'))
			$address->setPost_Code($params['post_code']);
		if(array_has($params, 'zipcode'))
			$address->setPost_Code($params['zipcode']);
		if(array_has($params, 'city'))
			$address->setCity($params['city']);
		if(array_has($params, 'country_region_code'))
			$address->setCountry_Region_Code($params['country_region_code']);
		if(array_has($params, 'state'))
			$address->setState($params['state']);
		if(array_has($params, 'phone_no'))
			$address->setPhone_No($params['phone_no']);
		if(array_has($params, 'phone'))
			$address->setPhone_No($params['phone']);
		if(array_has($params, 'name'))
			$address->setName($params['name']);
		if(array_has($params, 'contact'))
			$address->setContact($params['contact']);
		return $address;
	}
	public function updateAddress(Company $company,String $nav_id, string $addressCode, Array $params)
	{
		$staService = new STA_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$address = $staService->Read(new Read($nav_id, $addressCode))->getSTA();
		$address = $this->setParameters($address, $params);
		$addressUpdate = new Update($address);
		return $staService->Update($addressUpdate)->getSTA();
	}
	public function deleteAddress(Company $company, string $nav_id, string $addressCode)
	{
		$staService = new STA_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$address = $staService->Read(new Read($nav_id, $addressCode))->getSTA();
		$addressDelete = new Delete($address->getKey());
		return $staService->Delete($addressDelete);
	}
}