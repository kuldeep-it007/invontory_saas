<?php
namespace Rosetta\Services\Categories;

use Rosetta\Models\User;
Interface CategoryServiceInterface
{
	public function create(User $user, Array $params);
	public function update(User $user, Array $params, $code);
	public function getActiveCategories(User $user, $customerId, $customerType);
}