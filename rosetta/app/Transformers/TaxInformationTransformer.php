<?php
namespace Rosetta\Transformers;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Company;
use Rosetta\NAV\WS\Customer\Customer_Card;
class TaxInformationTransformer extends TransformerAbstract
{
	protected $company;
	public function __construct(Company $company = null)
	{
		$this->company = $company;
	}
	public function transform(Customer_Card $card)
	{
		$out = [];
		$taxSections = explode(',',$this->company->region->tax_information);
		if(in_array('gst',$taxSections))
		{
			$out['gst'] = [
				'number' => $card->getGST_Registration_No(),
				'registration_type' => [
					"key" => $card->getGST_Registration_Type(),
					"value" => $card->getGST_Registration_Type(),
				],
				'customer_type' => [
					"key" => $card->getGST_Customer_Type(),
					"value" => $card->getGST_Customer_Type(),
				]
			];
			if($card->getGST_Customer_Type() != "_blank_")
			{
				$out['gst']['customer_type'] = [
					"key" => $card->getGST_Customer_Type(),
					"value" => $card->getGST_Customer_Type(),
				];
			}
			else
			{
				$out['gst']['customer_type'] = null;

			}
		}
		if(in_array('pan',$taxSections))
		{
			$out['pan'] = [
				'reference_number' => $card->getP_A_N_Reference_No(),
				'number' => $card->getP_A_N_No(),
			];
			if($card->getP_A_N_Status() != "_blank_")
			{
				$out['pan']['status'] = [
					"key" => $card->getP_A_N_Status(),
					"value" => $card->getP_A_N_Status(),
				];
			}
			else
			{
				$out['pan']['status'] = null;

			}
		}
		if(in_array('vat',$taxSections))
		{
			$out['vat'] = [
				'tin' => $card->getT_I_N_No(),
				'registration_number' => $card->getVAT_Registration_No(),
				'export' => false,
				'exempted' => false,
			];
		}
		$out['tax']= [
			"lst" => $card->getL_S_T_No(),
			"cst" => $card->getC_S_T_No(),
		];
		return $out;
	}
}