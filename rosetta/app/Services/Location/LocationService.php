<?php
namespace Rosetta\Services\Location;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Rosetta\Models\City;
use Rosetta\Models\Country;
use Rosetta\Models\State;
use Rosetta\Services\Location\LocationServiceInterface;

class LocationService implements LocationServiceInterface
{
	public function getCountries()
 	{
 		return Country::all();
 	}
 	public function getStates($countryCode)
 	{
 		return State::all()->where('country_code', '=', $countryCode);
 	}
 	public function getCities($stateCode)
 	{
 		return City::all()->where('state_code', '=', $stateCode);
 	}
 	public function findCountry($countryCode)
	{
		$country = Country::find($countryCode);
		if(!$country)
		{
			throw new ModelNotFoundException;
		}
		return $country;
	}
}