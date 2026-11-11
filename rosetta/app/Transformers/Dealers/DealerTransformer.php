<?php 
namespace Rosetta\Transformers\Dealers;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Dealer;
use Rosetta\Models\MasterData;
use Rosetta\Models\State;
use Rosetta\Transformers\Company\CompanyTransformer;
use Rosetta\Transformers\Distributors\IndexTransformer;
use Rosetta\Transformers\Location\StateTransformer;
use Rosetta\Transformers\SalesManager\RegionTransformer;
use Rosetta\Transformers\SalesManager\SalesManagerTransformer;
use Rosetta\Transformers\TaxInformationTransformer;

class DealerTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['distributor','tax_information','state', 'sales_manager'];
	protected $defaultIncludes = ['company'];
	public function transform(Dealer $dealer)
	{
		$territory = null;
		if($dealer->nav->getTerritory_Code())
			$territory = MasterData::where('type','=','territory_code')->where('key','=',$dealer->nav->getTerritory_Code())->first();
		$generalBusinessPostingGroup = null;
		if($dealer->nav->getGen_Bus_Posting_Group())
			$generalBusinessPostingGroup = MasterData::where('type','=','gen_bus_postin_grp')->where('key','=',$dealer->nav->getGen_Bus_Posting_Group())->first();
		$billingLocationCode = null;
		if($dealer->nav->getLocation_Code())
			$billingLocationCode = $dealer->company->billing_locations()->where('code','=',$dealer->nav->getLocation_Code())->first();
		$paymentApplicationMethod = null;
		if($dealer->nav->getApplication_Method())
			$paymentApplicationMethod = MasterData::where('type','=','payment_application_method')->where('key','=',$dealer->nav->getApplication_Method())->first();
		$paymentMethod = null;
		if($dealer->nav->getPayment_Method_Code())
			$paymentMethod = MasterData::where('type','=','payment_method')->where('key','=',$dealer->nav->getPayment_Method_Code())->first();
		$paymentTerms = null;
		if($dealer->nav->getPayment_Terms_Code())
			$paymentTerms = MasterData::where('type','=','payment_terms')->where('key','=',$dealer->nav->getPayment_Terms_Code())->first();
		$cashFlowPaymentTerms = null;
		if($cashFlowPaymentTerms)
			$cashFlowPaymentTerms = MasterData::where('type','=','cash_flow_payment_terms')->where('key','=',$dealer->nav->getCash_Flow_Payment_Terms_Code())->first();
		$shippingReserve = null;
		if($dealer->nav->getReserve())
			$shippingReserve = MasterData::where('type','=','shipping_reserve')->where('key','=',$dealer->nav->getReserve())->first();
		$shippingAdvice = null;
		if($dealer->nav->getShipping_Advice())
			$shippingAdvice = MasterData::where('type','=','shipping_advice')->where('key','=',$dealer->nav->getShipping_Advice())->first();
		$shippingMethod = null;
		if($dealer->nav->getShipment_Method_Code())
			$shippingMethod = MasterData::where('type','=','shipping_method')->where('key','=',$dealer->nav->getShipment_Method_Code())->first();
		$copySellFrom = null;
		if($dealer->nav->getCopy_Sell_to_Addr_to_Qte_From())
			$copySellFrom = MasterData::where('type','=','sell_to_addr')->where('key','=',$dealer->nav->getCopy_Sell_to_Addr_to_Qte_From())->first();
		$structure = null;
		if($dealer->nav->getStructure())
			$structure = $dealer->company->region->taxTypes()->where('code','=',$dealer->nav->getStructure())->first();
		$data = [
			"id" => $dealer->id,
			"nav_id" => $dealer->nav_id,
			"name" => $dealer->name,
			"dealer_type" => $dealer->type == "Dealer" ? 1 : 2,
			"contact" => $dealer->nav->getContact(),
			"credit_limit" => $dealer->credit_limit,
			"overdue_balance" => $dealer->overdue_balance,
			"overdue_hold" => $dealer->overdue_hold,
			"address" => $dealer->nav->getAddress(),
			"address2" => $dealer->nav->getAddress_2(),
			"city" => $dealer->nav->getCity(),
			"zipcode" => $dealer->nav->getPost_Code(),
			"email" => $dealer->nav->getE_Mail(),
			"phone" => $dealer->nav->getPhone_No(),
			"fax" => $dealer->nav->getFax_No(),
			"area" => $dealer->area,
			"updated_folder_given" => $dealer->updated_folder_given == 1 ? true : false,
			"app_explained" => $dealer->app_explained == 1 ? true : false,
			"notes" => $dealer->notes,
			"sales_manager_id" => $dealer->sales_manager_id,
			"priority" => $dealer->priority,
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
	public function includeCompany(Dealer $dealer)
	{
		return $this->item($dealer->company,new CompanyTransformer);
	}
	public function includeDistributor(Dealer $dealer)
	{
		if($dealer->distributor)
			return $this->item($dealer->distributor,new IndexTransformer);
	}
	public function includeTaxInformation(Dealer $dealer)
	{
		return $this->item($dealer->nav,new TaxInformationTransformer($dealer->company));
	}
	public function includeState(Dealer $dealer)
	{
		$state = State::find($dealer->nav->getState_Code());
		if(!$state)
		{
			return null;
		}
		return $this->item($state,new StateTransformer);
	}
	
	public function includeSalesManager(Dealer $dealer)
	{
		if($dealer->sales_manager)
			return $this->item($dealer->sales_manager,new SalesManagerTransformer);
	}
}
