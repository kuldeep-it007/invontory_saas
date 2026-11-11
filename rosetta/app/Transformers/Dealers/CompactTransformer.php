<?php 
namespace Rosetta\Transformers\Dealers;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Dealer;
use Rosetta\Models\MasterData;
use Rosetta\Models\State;
use Rosetta\Models\User;
use Rosetta\Transformers\Company\IndexTransformer as CompanyTransformer;
use Rosetta\Transformers\Distributors\IndexTransformer as DistributorTransformer;
use Rosetta\Transformers\User\IndexTransformer as UserTransformer;
use Rosetta\Transformers\Location\StateTransformer;
use Rosetta\Transformers\TaxInformationTransformer;

class CompactTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['company'];
	protected $defaultIncludes = ['company'];
	public function transform(Dealer $dealer)
	{

		$data = [
			"id" => $dealer->id,
			"nav_id" => $dealer->nav_id,
			"name" => $dealer->name,
			"type" => $dealer->type,//#816
			"distributor_id" => $dealer->distributor_id,
			"distributor_name" => $dealer->distributor ? $dealer->distributor->name : '',
			"distributor_nav_id" => $dealer->distributor ? $dealer->distributor->nav_id : '',
			"state_code" => $dealer->state_code,
			"contact" => $dealer->contact,//#838
			"phone" => $dealer->phone,//#838
			"email" => $dealer->email,//#838
		];
		$user = User::where('dealer_id','=',$dealer->id)->first();
		if($user)
		{
			$data['user_email'] = $user->email;
			$data['user_phone'] = $user->phone;
		}
		return $data;
	}
	public function includeCompany(Dealer $dealer)
	{
		return $this->item($dealer->company,new CompanyTransformer);
	}
}
