<?php
namespace Rosetta\Transformers\Inventory;

use Carbon\Carbon;
use League\Fractal\TransformerAbstract;
use Rosetta\NAV\WS\ILE\ILE;
use Rosetta\Transformers\Inventory\CoLocationTransformer;
class LedgerEntryTransformer extends TransformerAbstract
{
	protected $availableIncludes = ['co_location'];
	protected $defaultIncludes = ['co_location'];
	public function transform(ILE $ile)
	{
		return [
			"lot_no" => $ile->getLot_No(),
			"remaining_quantity" => round($ile->getRemaining_Quantity(),4),
			"location_code" => $ile->getLocation_Code(),
			"location_source" => $ile->getLocation_Source(),
			"location_name" => $ile->getLocation_Name(),
			"location_source_id" => $ile->getLocation_SourceId(),
			"eta" => $ile->getPosting_Date(),
		//	"eta" => Carbon::parse($ile->getPosting_Date())->format('d-m-Y'),
			"intransit" => $ile->getEntry_Type() == 'In-Transit' ? 1 : 0,
		];
	}

	public function includeCoLocation(ILE $ile)
	{
		if($ile->getCoLocation())
		{
			return $this->item($ile->getCoLocation(),new CoLocationTransformer);
		}
		return null;
	}
}