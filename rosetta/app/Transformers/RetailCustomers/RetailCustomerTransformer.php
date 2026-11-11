<?php
namespace Rosetta\Transformers\RetailCustomers;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\MasterData;
use Rosetta\Models\RetailCustomer;
use Rosetta\Models\State;
use Rosetta\Transformers\Company\CompanyTransformer;
use Rosetta\Transformers\Location\StateTransformer;
use Rosetta\Transformers\TaxInformationTransformer;
class RetailCustomerTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['company','tax_information','state'];
	protected $defaultIncludes = ['company'];
	public function transform(RetailCustomer $retailCustomer)
	{
		$territory = null;
		if($retailCustomer->nav->getTerritory_Code())
			$territory = MasterData::where('type','=','territory_code')->where('key','=',$retailCustomer->nav->getTerritory_Code())->first();
		$generalBusinessPostingGroup = null;
		if($retailCustomer->nav->getGen_Bus_Posting_Group())
			$generalBusinessPostingGroup = MasterData::where('type','=','gen_bus_postin_grp')->where('key','=',$retailCustomer->nav->getGen_Bus_Posting_Group())->first();
		$billingLocationCode = null;
		if($retailCustomer->nav->getLocation_Code())
			$billingLocationCode = $retailCustomer->company->billing_locations()->where('code','=',$retailCustomer->nav->getLocation_Code())->first();
		$paymentApplicationMethod = null;
		if($retailCustomer->nav->getApplication_Method())
			$paymentApplicationMethod = MasterData::where('type','=','payment_application_method')->where('key','=',$retailCustomer->nav->getApplication_Method())->first();
		$paymentMethod = null;
		if($retailCustomer->nav->getPayment_Method_Code())
			$paymentMethod = MasterData::where('type','=','payment_method')->where('key','=',$retailCustomer->nav->getPayment_Method_Code())->first();
		$paymentTerms = null;
		if($retailCustomer->nav->getPayment_Terms_Code())
			$paymentTerms = MasterData::where('type','=','payment_terms')->where('key','=',$retailCustomer->nav->getPayment_Terms_Code())->first();
		$cashFlowPaymentTerms = null;
		if($retailCustomer->nav->getCash_Flow_Payment_Terms_Code())
			$cashFlowPaymentTerms = MasterData::where('type','=','cash_flow_payment_terms')->where('key','=',$retailCustomer->nav->getCash_Flow_Payment_Terms_Code())->first();
		$shippingReserve = null;
		if($retailCustomer->nav->getReserve())
			$shippingReserve = MasterData::where('type','=','shipping_reserve')->where('key','=',$retailCustomer->nav->getReserve())->first();
		$shippingAdvice = null;
		if($retailCustomer->nav->getShipping_Advice())
			$shippingAdvice = MasterData::where('type','=','shipping_advice')->where('key','=',$retailCustomer->nav->getShipping_Advice())->first();
		$shippingMethod = null;
		if($retailCustomer->nav->getShipment_Method_Code())
			$shippingMethod = MasterData::where('type','=','shipping_method')->where('key','=',$retailCustomer->nav->getShipment_Method_Code())->first();
		$copySellFrom = null;
		if($retailCustomer->nav->getCopy_Sell_to_Addr_to_Qte_From())
			$copySellFrom = MasterData::where('type','=','sell_to_addr')->where('key','=',$retailCustomer->nav->getCopy_Sell_to_Addr_to_Qte_From())->first();
		$structure = null;
		if($retailCustomer->nav->getStructure())
			$structure = $retailCustomer->company->region->taxTypes()->where('code','=',$retailCustomer->nav->getStructure())->first();
		$data = [
			"id" => $retailCustomer->id,
			"nav_id" => $retailCustomer->nav_id,
			"name" => $retailCustomer->name,
			"address" => $retailCustomer->nav->getAddress(),
			"address2" => $retailCustomer->nav->getAddress_2(),
			"city" => $retailCustomer->nav->getCity(),
			"zipcode" => $retailCustomer->nav->getPost_Code(),
			"email" => $retailCustomer->nav->getE_Mail(),
			"phone" => $retailCustomer->nav->getPhone_No(),
			"fax" => $retailCustomer->nav->getFax_No(),
		];
		if($territory)
		{
			$data["territory"] = [
				"key" => $territory->key,
				"value" => $territory->value
			];
		}
		else
		{
			$data["territory"] = [
				"key" => "",
				"value" => ""
			];
		}
		if($paymentMethod)
		{
			$data["paymentMethod"] = [
				"key" => $paymentMethod->key,
				"value" => $paymentMethod->value
			];
		}
		else
		{
			$data["paymentMethod"] = [
				"key" => "",
				"value" => ""
			];
		}
		if($paymentTerms)
		{
			$data["paymentTerms"] = [
				"key" => $paymentTerms->key,
				"value" => $paymentTerms->value
			];
		}
		else
		{
			$data["paymentTerms"] = [
				"key" => "",
				"value" => ""
			];
		}
		if($cashFlowPaymentTerms)
		{
			$data["cashFlowPaymentTerms"] = [
				"key" => $cashFlowPaymentTerms->key,
				"value" => $cashFlowPaymentTerms->value
			];
		}
		else
		{
			$data["cashFlowPaymentTerms"] = [
				"key" => "",
				"value" => ""
			];
		}
		if($shippingMethod)
		{
			$data["shippingMethod"] = [
				"key" => $shippingMethod->key,
				"value" => $shippingMethod->value
			];
		}
		else
		{
			$data["shippingMethod"] = [
				"key" => "",
				"value" => ""
			];
		}
		if($structure)
		{
			$data["structure"] = [
				"code"=> $structure->code,
				"name" => $structure->name,
			];
		}
		else
		{
			$data["structure"] = [
				"code"=> "",
				"name" => ""
			];
		}
		if($copySellFrom)
		{
			$data["copySellFrom"] = [
				"key"=> $copySellFrom->key,
				"value" => $copySellFrom->value,
			];
		}
		else
		{
			$data["copySellFrom"] = [
				"key"=> "",
				"value" => ""
			];
		}
		if($shippingAdvice)
		{
			$data["shippingAdvice"] = [
				"key" => $shippingAdvice->key,
				"value" => $shippingAdvice->value
			];
		}
		else
		{
			$data["shippingAdvice"] = [
				"key"=> "",
				"value" => ""
			];
		}
		if($shippingReserve)
		{
			$data["shippingReserve"] = [
				"key" => $shippingReserve->key,
				"value" => $shippingReserve->value
			];
		}
		else
		{
			$data["shippingReserve"] = [
				"key"=> "",
				"value" => ""
			];
		}
		if($paymentApplicationMethod)
		{
			$data["paymentApplicationMethod"] = [
				"key" => $paymentApplicationMethod->key,
				"value" => $paymentApplicationMethod->value
			];
		}
		else
		{
			$data["paymentApplicationMethod"] = [
				"key"=> "",
				"value" => ""
			];
		}
		if($billingLocationCode)
		{
			$data["billingLocationCode"] = [
				'code' => $billingLocationCode->code,
				'name' => $billingLocationCode->name,
			];
		}
		else
		{
			$data["billingLocationCode"] = [
				"code"=> "",
				"name" => ""
			];
		}
		if($generalBusinessPostingGroup)
		{
			$data["generalBusinessPostingGroup"] = [
				"key" => $generalBusinessPostingGroup->key,
				"value" => $generalBusinessPostingGroup->value
			];
		}
		else
		{
			$data["generalBusinessPostingGroup"] = [
				"key"=> "",
				"value" => ""
			];
		}
		return $data;
	}
	public function includeCompany(RetailCustomer $retailCustomer)
	{
		return $this->item($retailCustomer->company,new CompanyTransformer);
	}	
	public function includeTaxInformation(RetailCustomer $retailCustomer)
	{
		return $this->item($retailCustomer->nav,new TaxInformationTransformer($retailCustomer->company));
	}
	public function includeState(RetailCustomer $retailCustomer)
	{
		$state = State::find($retailCustomer->nav->getState_Code());
		return $this->item($state,new StateTransformer);
	}
}