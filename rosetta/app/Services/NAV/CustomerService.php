<?php
namespace Rosetta\Services\NAV;

use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\RetailCustomer;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\NAV\WS\Customer\Create;
use Rosetta\NAV\WS\Customer\Customer_Card;
use Rosetta\NAV\WS\Customer\Customer_Card_Fields;
use Rosetta\NAV\WS\Customer\Customer_Card_Filter;
use Rosetta\NAV\WS\Customer\Customer_Card_Service;
use Rosetta\NAV\WS\Customer\Read;
use Rosetta\NAV\WS\Customer\ReadByRecId;
use Rosetta\NAV\WS\Customer\ReadMultiple;
use Rosetta\NAV\WS\Customer\Update;
use Rosetta\Services\Distributor\NAV\NavServiceInterface;
use Rosetta\Traits\Nav;
use SoapFault;
class CustomerService implements CustomerServiceInterface
{
	use Nav;
	const LOCATION = 'Page/Customer_Card';
	const WSDL = 'CustomerCard';
	protected function create(Company $company,Array $params,$postingGroup,$priceGroup,$distributor = null)
	{
		$customerCardService = new Customer_Card_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$customerCard = new Customer_Card;
		$customerCard = $this->setParameters($customerCard, $params,$postingGroup,$priceGroup);
		if($distributor)
		{
			$customerCard->setDistributor_Code($distributor->nav_id);
		}
		if($priceGroup == 'DEALER' && $postingGroup == 'DEALER')//#674
		{
			if($distributor == null)
			{
				if(array_has($params,'credit_limit'))
				{
					$customerCard->setCredit_Limit_LCY($params['credit_limit']);
				}
			}	
		}
		elseif($priceGroup == 'DISTRIBUTO' && $postingGroup == 'DISTRIBUTO')//#676
		{
			if(array_has($params,'credit_limit'))
			{
				$customerCard->setCredit_Limit_LCY($params['credit_limit']);
			}
		}
		$customerCreate = new Create($customerCard);
		$customerCardReturned =  $customerCardService->Create($customerCreate)->getCustomer_Card();
		return $customerCardReturned;
	}
	public function setTaxInformation(Company $company,Customer_Card $customerCard,Array $taxInformation)
	{
		
	}
	public function get(Company $company,String $navId)
	{
		$customerCardService = new Customer_Card_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		return $customerCardService->Read(new Read($navId))->getCustomer_Card();
	}
	public function createDistributor(Company $company,Array $params)
	{
		return $this->create($company,$params,'DISTRIBUTO','DISTRIBUTO');
	}
	public function createSuperStockist(Company $company,Array $params)
	{
		return $this->create($company,$params,'DISTRIBUTO','DISTRIBUTO');
	}
	public function updateDistributor(Company $company, Distributor $distributor,Array $params, $superStockist)
	{
		$customerCardService = new Customer_Card_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$customerCard = $customerCardService->Read(new Read($distributor->nav_id))->getCustomer_Card();
		$customerCard = $this->setParameters($customerCard, $params,'DISTRIBUTO','DISTRIBUTO');
		if(array_has($params,'credit_limit'))
		{
			$customerCard->setCredit_Limit_LCY($params['credit_limit']);
		}
		$customerCardUpdate = new Update($customerCard);
		if($superStockist != null)//#827
		{
			$customerCard->setDistributor_Code($superStockist->nav_id);
		}	
		else
		{
			$customerCard->setDistributor_Code('');
		}
		return $customerCardService->Update($customerCardUpdate)->getCustomer_Card();
	}
	public function updateDealer(Company $company, Dealer $dealer,Array $params, $distributor)
	{
		$customerCardService = new Customer_Card_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$customerCard = $customerCardService->Read(new Read($dealer->nav_id))->getCustomer_Card();
		$customerCard = $this->setParameters($customerCard, $params,'DEALER','DEALER');
		if($distributor == null)
		{
			if(array_has($params,'credit_limit'))
			{
				$customerCard->setCredit_Limit_LCY($params['credit_limit']);
			}
		}
		if($distributor != null)//#827
		{
			$customerCard->setDistributor_Code($distributor->nav_id);
		}	
		else
		{
			$customerCard->setDistributor_Code('');
		}
		$customerCardUpdate = new Update($customerCard);
		return $customerCardService->Update($customerCardUpdate)->getCustomer_Card();
	}
	protected function setParameters($customerCard, $params, $postingGroup,$priceGroup)
	{
		$customerCard->setName($params['name']);
		$customerCard->setAddress($params['address']);
		if(array_has($params,'address2'))
		{
			if($params['address2'] === null)
				$customerCard->setAddress_2("");	
			else
				$customerCard->setAddress_2($params['address2']);
		}
		if(array_has($params,'state'))
		{
			$customerCard->setState_Code($params['state']);
		}
		if(array_has($params,'city'))
		{
			if($params['city'] === null)
				$customerCard->setCity("");	
			else
				$customerCard->setCity($params['city']);
		}
		if(array_has($params,'zipcode'))
		{
			if($params['zipcode'] === null)
				$customerCard->setPost_Code("");	
			else
				$customerCard->setPost_Code($params['zipcode']);
		}
		if(array_has($params,'territory'))
		{
			$customerCard->setTerritory_Code($params['territory']);
		}
		if(array_has($params,'billingLocationCode'))
		{
			$customerCard->setLocation_Code($params['billingLocationCode']);
		}
		if(array_has($params,'generalBusinessPostingGroup'))
		{
			$customerCard->setGen_Bus_Posting_Group($params['generalBusinessPostingGroup']);
		}
		if(array_has($params,'paymentApplicationMethod'))
		{
			$customerCard->setApplication_Method($params['paymentApplicationMethod']);
		}
		if(array_has($params,'paymentMethod'))
		{
			$customerCard->setPayment_Method_Code($params['paymentMethod']);
		}
		if(array_has($params,'paymentTerms'))
		{
			$customerCard->setPayment_Terms_Code($params['paymentTerms']);
		}
		if(array_has($params,'cashFlowPaymentTerms'))
		{
			$customerCard->setCash_Flow_Payment_Terms_Code($params['cashFlowPaymentTerms']);
		}
		if(array_has($params,'shippingReserve'))
		{
			$customerCard->setReserve($params['shippingReserve']);
		}
		if(array_has($params,'shippingAdvice'))
		{
			$customerCard->setShipping_Advice($params['shippingAdvice']);
		}
		if(array_has($params,'shippingMethod'))
		{
			$customerCard->setShipment_Method_Code($params['shippingMethod']);
		}
		if(array_has($params,'copySellFrom'))
		{
			$customerCard->setCopy_Sell_to_Addr_to_Qte_From($params['copySellFrom']);
		}
		if(array_has($params,'structure'))
		{
			$customerCard->setStructure($params['structure']);
		}
		if(array_has($params,'contact'))
		{
			$customerCard->setContact($params['contact']);
		}
		if(array_has($params,'email'))
		{
			if($params['email'] === null)
				$customerCard->setE_Mail("");	
			else
				$customerCard->setE_Mail($params['email']);
		}
		if(array_has($params,'phone'))
		{
			if($params['phone'] === null)
				$customerCard->setPhone_No("");	
			else
				$customerCard->setPhone_No($params['phone']);
		}
		if(array_has($params,'fax'))
		{
			if($params['fax'] === null)
				$customerCard->setFax_No("");	
			else
				$customerCard->setFax_No($params['fax']);
		}
		if(array_has($params,'tax_information'))
		{
			$taxInformation = $params['tax_information'];
			if(array_has($taxInformation,'vat'))
			{
				$vat = $taxInformation['vat'];
				if(isset($vat['tin']))
					$customerCard->setT_I_N_No($vat['tin']);
				$customerCard->setStructure("SALES UAE");//#652
				if(array_has($vat,'export'))
					$customerCard->setExport_or_Deemed_Export($vat['export']);
				if(array_has($vat,'exempted'))
					$customerCard->setVAT_Exempted($vat['exempted']);
				if(isset($vat['registration_number']))//#673
					$customerCard->setVAT_Registration_No($vat['registration_number']);
			}

			if(array_has($taxInformation,'gst'))
			{
				$gst = $taxInformation['gst'];
				if(isset($gst['number']))
				{
					$customerCard->setGST_Registration_No($gst['number']);
					$customerCard->setGST_Registration_Type($gst['registration_type']);
				}
				else
				{
					$customerCard->setGST_Customer_Type($gst['customer_type']);
				}
			}

			if(array_has($taxInformation,'tax'))
			{
				$tax = $taxInformation['tax'];
				$customerCard->setL_S_T_No($tax['lst']);
				$customerCard->setC_S_T_No($tax['cst']);
			}
			if(array_has($taxInformation,'pan'))
			{
				$pan = $taxInformation['pan'];
				$customerCard->setP_A_N_Status($pan['status']);
				$customerCard->setP_A_N_Reference_No($pan['reference_number']);
				$customerCard->setP_A_N_No($pan['number']);
			}	
		}
		$customerCard->setCustomer_Posting_Group($postingGroup);
		$customerCard->setCustomer_Price_Group($priceGroup);
		//$customerCard->setDistributor_Code('CUST/0273');
		return $customerCard;
	}
	public function getDistributors(Company $company)
	{
		$customerCardService = new Customer_Card_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$customerCardFilter = new Customer_Card_Filter(Customer_Card_Fields::Customer_Posting_Group,'DISTRIBUTO','DISTRIBUTO');
		$readMultiple = new ReadMultiple($customerCardFilter,null,null);
		$distributors = $customerCardService->ReadMultiple($readMultiple)->getReadMultiple_Result()->getCustomer_Card();
		if($distributors == null)
			return [];
		if(!is_array($distributors))
			return [$distributors];
		return $distributors;
	}
	public function getDealers(Company $company)
	{
		$customerCardService = new Customer_Card_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$customerCardFilter = new Customer_Card_Filter(Customer_Card_Fields::Customer_Posting_Group,'DEALER','DEALER');
		$readMultiple = new ReadMultiple($customerCardFilter,null,null);
		$dealers = $customerCardService->ReadMultiple($readMultiple)->getReadMultiple_Result()->getCustomer_Card();
		if($dealers == null)
			return [];
		if(!is_array($dealers))
			return [$dealers];
		return $dealers;
	}
	public function createDealer(Company $company, Array $params,Distributor $distributor=null)
	{
		return $this->create($company, $params, 'DEALER','DEALER',$distributor);
	}
	public function createRetailCustomer(Company $company,Array $params)
	{
		return $this->create($company,$params,'DOMESTIC','');
	}
	public function updateRetailCustomer(Company $company, RetailCustomer $retailCustomer,Array $params)
	{
		$customerCardService = new Customer_Card_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$customerCard = $customerCardService->Read(new Read($retailCustomer->nav_id))->getCustomer_Card();
		$customerCard = $this->setParameters($customerCard, $params,'DOMESTIC','');
		$customerCardUpdate = new Update($customerCard);
		return $customerCardService->Update($customerCardUpdate)->getCustomer_Card();
	}

	public function updateSuperStockist(Company $company, SuperStockist $superStockist,Array $params)
	{
		$customerCardService = new Customer_Card_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$customerCard = $customerCardService->Read(new Read($superStockist->nav_id))->getCustomer_Card();
		$customerCard = $this->setParameters($customerCard, $params,'DISTRIBUTO','DISTRIBUTO');
		if(array_has($params,'credit_limit'))
		{
			$customerCard->setCredit_Limit_LCY($params['credit_limit']);
		}
		$customerCardUpdate = new Update($customerCard);
		return $customerCardService->Update($customerCardUpdate)->getCustomer_Card();
	}	
}