<?php
namespace Rosetta\Services\Collection;

use Rosetta\Models\User;
Interface CollectionServiceInterface
{
	public function create(User $user, Array $params, $category_code);
	public function getActiveCollections(User $user, $category_code, $customerType, $customerId);
}