<?php
namespace Rosetta\Traits;

use Rosetta\Models\Company;
Trait Nav{
	public function getWSLocation(Company $company)
	{
		//http://52.221.47.8:7047/DYNAMICSNAV90/WS/AL%20ANWAR%20MARKETING%20CO.%20%28PART%29
		return config('nav.host') . rawurlencode($company->database) . "/";
	}
}