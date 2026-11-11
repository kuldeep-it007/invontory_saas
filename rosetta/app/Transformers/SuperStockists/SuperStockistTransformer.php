<?php
namespace Rosetta\Transformers\SuperStockists;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\MasterData;
use Rosetta\Models\State;
use Rosetta\Models\SuperStockist\SuperStockist;
//use Rosetta\Transformers\Company\CompanyTransformer;
use Rosetta\Transformers\Company\CompactTransformer as CompanyTransformer;
use Rosetta\Transformers\Location\StateTransformer;
use Rosetta\Transformers\SuperStockists\AbilitiesTransformer;
use Rosetta\Transformers\TaxInformationTransformer;
class SuperStockistTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['company','tax_information','state','abilities'];
	protected $defaultIncludes = ['company'];
	public function transform(SuperStockist $superStockist)
	{
		$territory = null;
		if($superStockist->nav->getTerritory_Code())
			$territory = MasterData::where('type','=','territory_code')->where('key','=',$superStockist->nav->getTerritory_Code())->first();
		$generalBusinessPostingGroup = null;
		if($superStockist->nav->getGen_Bus_Posting_Group())
			$generalBusinessPostingGroup = MasterData::where('type','=','gen_bus_postin_grp')->where('key','=',$superStockist->nav->getGen_Bus_Posting_Group())->first();
		$billingLocationCode = null;
		if($superStockist->nav->getLocation_Code())
			$billingLocationCode = $superStockist->company->billing_locations()->where('code','=',$superStockist->nav->getLocation_Code())->first();
		$paymentApplicationMethod = null;
		if($superStockist->nav->getApplication_Method())
			$paymentApplicationMethod = MasterData::where('type','=','payment_application_method')->where('key','=',$superStockist->nav->getApplication_Method())->first();
		$paymentMethod = null;
		if($superStockist->nav->getPayment_Method_Code())
			$paymentMethod = MasterData::where('type','=','payment_method')->where('key','=',$superStockist->nav->getPayment_Method_Code())->first();
		$paymentTerms = null;
		if($superStockist->nav->getPayment_Terms_Code())
			$paymentTerms = MasterData::where('type','=','payment_terms')->where('key','=',$superStockist->nav->getPayment_Terms_Code())->first();
		$cashFlowPaymentTerms = null;
		if($superStockist->nav->getCash_Flow_Payment_Terms_Code())
			$cashFlowPaymentTerms = MasterData::where('type','=','cash_flow_payment_terms')->where('key','=',$superStockist->nav->getCash_Flow_Payment_Terms_Code())->first();
		$shippingReserve = null;
		if($superStockist->nav->getReserve())
			$shippingReserve = MasterData::where('type','=','shipping_reserve')->where('key','=',$superStockist->nav->getReserve())->first();
		$shippingAdvice = null;
		if($superStockist->nav->getShipping_Advice())
			$shippingAdvice = MasterData::where('type','=','shipping_advice')->where('key','=',$superStockist->nav->getShipping_Advice())->first();
		$shippingMethod = null;
		if($superStockist->nav->getShipment_Method_Code())
			$shippingMethod = MasterData::where('type','=','shipping_method')->where('key','=',$superStockist->nav->getShipment_Method_Code())->first();
		$copySellFrom = null;
		if($superStockist->nav->getCopy_Sell_to_Addr_to_Qte_From())
			$copySellFrom = MasterData::where('type','=','sell_to_addr')->where('key','=',$superStockist->nav->getCopy_Sell_to_Addr_to_Qte_From())->first();
		$structure = null;
		if($superStockist->nav->getStructure())
			$structure = $superStockist->company->region->taxTypes()->where('code','=',$superStockist->nav->getStructure())->first();
		$data = [
			"id" => $superStockist->id,
			"nav_id" => $superStockist->nav_id,
			"name" => $superStockist->name,
			"display_name" => $superStockist->display_name,
			"address" => $superStockist->nav->getAddress(),
			"address2" => $superStockist->nav->getAddress_2(),
			"city" => $superStockist->nav->getCity(),
			"zipcode" => $superStockist->nav->getPost_Code(),
			"email" => $superStockist->nav->getE_Mail(),
			"phone" => $superStockist->nav->getPhone_No(),
			"fax" => $superStockist->nav->getFax_No(),
			"credit_limit" => $superStockist->nav->getCredit_Limit_LCY(),
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
		if($copySellFrom)
		{
			$data["copySellFrom"] = [
				"key" => $copySellFrom->key,
				"value" => $copySellFrom->value
			];
		}
		else
		{
			$data["copySellFrom"] = [
				"key"=> "",
				"value" => ""
			];
		}
		return $data;
	}
	public function includeCompany(SuperStockist $superStockist)
	{
		return $this->item($superStockist->company,new CompanyTransformer);
	}	
	public function includeTaxInformation(SuperStockist $superStockist)
	{
		return $this->item($superStockist->nav,new TaxInformationTransformer($superStockist->company));
	}
	public function includeState(SuperStockist $superStockist)
	{
		$state = State::find($superStockist->nav->getState_Code());
		return $this->item($state,new StateTransformer);
	}
	public function includeAbilities(SuperStockist $superStockist)
	{
		return $this->item($superStockist,new AbilitiesTransformer);
	}
}