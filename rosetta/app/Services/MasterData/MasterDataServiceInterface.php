<?php
namespace Rosetta\Services\MasterData;

Interface MasterDataServiceInterface
{
	public function get();
	public function getTenants($term);
	public function getStock_point_tenants($term);
}