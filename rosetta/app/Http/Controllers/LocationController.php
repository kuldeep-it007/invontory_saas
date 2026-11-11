<?php

namespace Rosetta\Http\Controllers;

use Illuminate\Http\Request;
use Rosetta\Services\Location\LocationServiceInterface;
use Rosetta\Transformers\Location\CityTransformer;
use Rosetta\Transformers\Location\CountryTransformer;
use Rosetta\Transformers\Location\StateTransformer;

class LocationController extends Controller
{
    protected $locationService;
	public function __construct(LocationServiceInterface $locationService)
	{
		$this->locationService = $locationService;
	}
	public function getCountries()
    {
        $countries = $this->locationService->getCountries();
    	return fractal()
            ->collection($countries)
            ->transformWith(new CountryTransformer())
            ->toArray();
    }
    public function getStates($countryCode)
    {
        $states = $this->locationService->getStates($countryCode);
        return fractal()
            ->collection($states)
            ->transformWith(new StateTransformer())
            ->toArray();
    }
    public function getCities($countryCode, $stateCode)
    {
        $cities = $this->locationService->getCities($stateCode);
        return fractal()
            ->collection($cities)
            ->transformWith(new CityTransformer())
            ->toArray();
    }
}
