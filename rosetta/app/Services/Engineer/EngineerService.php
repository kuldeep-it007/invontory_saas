<?php
namespace Rosetta\Services\Engineer;

use Illuminate\Support\Facades\DB;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\Engineer;
use Rosetta\Models\User;
use Rosetta\Services\Engineer\EngineerServiceInterface;

class EngineerService implements EngineerServiceInterface
{
	public function createEngineer($companyId, $dealerId, Array $params, $user)
	{
		$dealer = Dealer::find($dealerId);
		$distributor = $dealer->distributor;
		$engineer = new Engineer;
		$engineer->name = $params['name'];
 		$engineer->phone_no = $params['phone_no'];
 		$engineer->type = 'Installer';
 		$engineer->company_id = $companyId;
 		$engineer->author()->associate($user);
 		$distributor->engineers()->save($engineer);
 		return $engineer;
	}
	public function updateEngineer($dealerId, $customerId, Array $params)
	{

	}
	public function getAllEngineers($distributorId)
	{
		$distributor = Distributor::find($distributorId);
		$engineers = $distributor->engineers;
		if($engineers === null)
			return [];
		//if(!is_array($customers))
		//	return [$customers];
		return $engineers;
	}
	public function deleteEngineer($dealerId, $engineerId)
	{
		$dealer = Dealer::find($dealerId);
		$engineer = $dealer->engineers->where('id', '=', $engineerId)->first();
		$engineer->delete();
	}
}