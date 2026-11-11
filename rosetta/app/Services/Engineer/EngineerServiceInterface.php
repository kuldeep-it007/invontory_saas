<?php
namespace Rosetta\Services\Engineer;

use Rosetta\Models\Company;
use Rosetta\Models\User;
Interface EngineerServiceInterface
{
	public function createEngineer($companyId, $dealerId, Array $params, $user);
	public function updateEngineer($dealerId, $engineerId, Array $params);
	public function getAllEngineers($distributorId);
	public function deleteEngineer($dealerId, $engineerId);
}