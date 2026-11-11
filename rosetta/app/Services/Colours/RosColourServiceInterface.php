<?php
namespace Rosetta\Services\Colours;

use Rosetta\Models\User;

Interface RosColourServiceInterface
{
	public function create(User $user, Array $params, $collection_code);
	public function getActiveColours(User $user, $category_code, $collection_code, $customerType, $customerId);
}