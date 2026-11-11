<?php
namespace Rosetta\Services\Location;

interface LocationServiceInterface
{
	public function getCountries();
	public function getStates($countryCode);
	public function getCities($stateCode);
	public function findCountry($countryCode);
}