<?php
namespace Rosetta\Transformers\Distributors;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Distributor;
use Rosetta\Models\MasterData;
use Rosetta\Models\State;
use Rosetta\Transformers\Company\CompanyTransformer;
use Rosetta\Transformers\Distributors\AbilitiesTransformer;
use Rosetta\Transformers\Location\StateTransformer;
use Rosetta\Transformers\SuperStockists\IndexTransformer;
use Rosetta\Transformers\TaxInformationTransformer;
class DistributorTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['company','tax_information','state','abilities','superStockist'];
	protected $defaultIncludes = ['company'];
	public function transform(Distributor $distributor)
	{
		$territory = null;
		if($distributor->nav->getTerritory_Code())
			$territory = MasterData::where('type','=','territory_code')->where('key','=',$distributor->nav->getTerritory_Code())->first();
		$generalBusinessPostingGroup = null;
		if($distributor->nav->getGen_Bus_Posting_Group())
			$generalBusinessPostingGroup = MasterData::where('type','=','gen_bus_postin_grp')->where('key','=',$distributor->nav->getGen_Bus_Posting_Group())->first();
		$billingLocationCode = null;
		if($distributor->nav->getLocation_Code())
			$billingLocationCode = $distributor->company->billing_locations()->where('code','=',$distributor->nav->getLocation_Code())->first();
		$paymentApplicationMethod = null;
		if($distributor->nav->getApplication_Method())
			$paymentApplicationMethod = MasterData::where('type','=','payment_application_method')->where('key','=',$distributor->nav->getApplication_Method())->first();
		$paymentMethod = null;
		if($distributor->nav->getPayment_Method_Code())
			$paymentMethod = MasterData::where('type','=','payment_method')->where('key','=',$distributor->nav->getPayment_Method_Code())->first();
		$paymentTerms = null;
		if($distributor->nav->getPayment_Terms_Code())
			$paymentTerms = MasterData::where('type','=','payment_terms')->where('key','=',$distributor->nav->getPayment_Terms_Code())->first();
		$cashFlowPaymentTerms = null;
		if($distributor->nav->getCash_Flow_Payment_Terms_Code())
			$cashFlowPaymentTerms = MasterData::where('type','=','cash_flow_payment_terms')->where('key','=',$distributor->nav->getCash_Flow_Payment_Terms_Code())->first();
		$shippingReserve = null;
		if($distributor->nav->getReserve())
			$shippingReserve = MasterData::where('type','=','shipping_reserve')->where('key','=',$distributor->nav->getReserve())->first();
		$shippingAdvice = null;
		if($distributor->nav->getShipping_Advice())
			$shippingAdvice = MasterData::where('type','=','shipping_advice')->where('key','=',$distributor->nav->getShipping_Advice())->first();
		$shippingMethod = null;
		if($distributor->nav->getShipment_Method_Code())
			$shippingMethod = MasterData::where('type','=','shipping_method')->where('key','=',$distributor->nav->getShipment_Method_Code())->first();
		$copySellFrom = null;
		if($distributor->nav->getCopy_Sell_to_Addr_to_Qte_From())
			$copySellFrom = MasterData::where('type','=','sell_to_addr')->where('key','=',$distributor->nav->getCopy_Sell_to_Addr_to_Qte_From())->first();
		$structure = null;
		if($distributor->nav->getStructure())
			$structure = $distributor->company->region->taxTypes()->where('code','=',$distributor->nav->getStructure())->first();
		$data = [
			"id" => $distributor->id,
			"nav_id" => $distributor->nav_id,
			"name" => $distributor->name,
			"display_name" => $distributor->display_name,
			"address" => $distributor->nav->getAddress(),
			"address2" => $distributor->nav->getAddress_2(),
			"city" => $distributor->nav->getCity(),
			"zipcode" => $distributor->nav->getPost_Code(),
			"email" => $distributor->nav->getE_Mail(),
			"phone" => $distributor->nav->getPhone_No(),
			"fax" => $distributor->nav->getFax_No(),
			"credit_limit" => $distributor->nav->getCredit_Limit_LCY(),
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
	public function includeCompany(Distributor $distributor)
	{
		return $this->item($distributor->company,new CompanyTransformer);
	}	
	public function includeSuperStockist(Distributor $distributor)
	{
		if($distributor->superStockist)
			return $this->item($distributor->superStockist,new IndexTransformer);
	}
	public function includeTaxInformation(Distributor $distributor)
	{
		return $this->item($distributor->nav,new TaxInformationTransformer($distributor->company));
	}
	public function includeState(Distributor $distributor)
	{
		$state = State::find($distributor->nav->getState_Code());
		return $this->item($state,new StateTransformer);
	}
	public function includeAbilities(Distributor $distributor)
	{
		return $this->item($distributor,new AbilitiesTransformer);
	}
}