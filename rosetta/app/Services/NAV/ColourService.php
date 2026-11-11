<?php
namespace Rosetta\Services\NAV;

use Rosetta\Models\Company;
use Rosetta\NAV\WS\Colour\Colour;
use Rosetta\NAV\WS\Colour\Colour_Service;
use Rosetta\NAV\WS\Colour\Create;
use Rosetta\NAV\WS\Colour\ReadMultiple;
use Rosetta\NAV\WS\Colour\ReadMultiple_Result;
use Rosetta\NAV\WS\Colour\getColour;
use Rosetta\Services\NAV\ColourServiceInterface;
use Rosetta\Traits\Nav;
class ColourService implements ColourServiceInterface
{
	use Nav;
	const LOCATION = 'Page/Colour';
	const WSDL = 'Colour';

	public function getColours(Company $company)
	{
		$colourService = new Colour_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$readMultiple = new ReadMultiple(null,null,null);
		$colours = $colourService->ReadMultiple($readMultiple)->getReadMultiple_Result()->getColour();
		if($colours == null)
			return [];
		if(!is_array($colours))
			return [$colours];
		return $colours;
	}
	public function createColour($company, $params, $collection_code)
	{
		$colourService = new Colour_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$colour = new Colour;
		$colour->setCode($params['code']);
		$colour->setCollection_Code($collection_code);
		$colourCreate = new Create($colour);
		$colourService->Create($colourCreate);
	}
}