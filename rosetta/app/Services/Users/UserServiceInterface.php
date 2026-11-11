<?php
namespace Rosetta\Services\Users;

use Rosetta\Models\User;

interface UserServiceInterface
{
	public function reset(Array $params, $userId);
	public function update(Array $params, $userId);
	public function create(User $loggedInUser, Array $params, $type, $customer);
	public function delete($id, User $loggedInUser);
	public function resetCustomerPassword(Array $params, $userId);
	public function signUp(Array $params, $userId);//#785
	public function listProspects(Array $params);//#785
	public function deleteProspect($id);//#785
	public function createUserFromWhatsApp(Array $params, $type, $customer);
}