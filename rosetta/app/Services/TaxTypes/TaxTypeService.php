<?php
namespace Rosetta\Services\TaxTypes;

use Illuminate\Support\Facades\DB;
use Rosetta\Models\TaxType;
use Rosetta\Services\TaxTypes\TaxTypeServiceInterface;
class TaxTypeService implements TaxTypeServiceInterface
{
	public function get(Array $params =[])
	{
		if(array_has($params, 'page'))
			return TaxType::paginate($params['per_page'],['*'],'',$params['page']);
		return TaxType::all();
	}
}