<?php
namespace Rosetta\Services\Region;

use Rosetta\Models\User;
interface RegionServiceInterface
{
	public function create(User $user,Array $params);
	public function all(Array $params = []);
	public function update(User $user,int $id,Array $params);
	public function product_taxes(int $id, Array $params);
}