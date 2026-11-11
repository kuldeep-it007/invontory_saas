<?php
namespace Rosetta\Services\Region;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Rosetta\Exceptions\Tax\RecordAlreadyExistsException;
use Rosetta\Models\Product\Tax;
use Rosetta\Models\Region;
use Rosetta\Models\User;
use Rosetta\Services\Location\LocationServiceInterface;
use Rosetta\Services\Region\RegionServiceInterface;
class RegionService implements RegionServiceInterface
{
	protected $locationService;
	public function __construct(LocationServiceInterface $locationService)
	{
		$this->locationService = $locationService;
	}
	public function create(User $user,Array $params)
	{
		$region = new Region;
		$region->name = $params['name'];
		$country = $this->locationService->findCountry($params['country']);
		$region->country()->associate($country);
		$region->save();
		if(array_has($params,'tax_types'))
		{
			$this->manageTaxes($region,$params['tax_types']);
		}
		if(array_has($params,'tax_information'))
		{
			$region->tax_information = implode(',',$params['tax_information']);
		}
		return $region;
	}
	public function all(Array $params = [])
	{
		if(array_has($params,'page'))
		{
			return Region::paginate($params['per_page'],['*'],'',$params['page']);
		}
		return Region::all();
	}
	public function find(int $id)
	{
		$region = Region::find($id);
		if(!$region)
		{
			throw new ModelNotFoundException;
		}
		return $region;
	}
	public function update(User $user,int $id,Array $params)
	{
		$region = $this->find($id);
		$region->name = $params['name'];
		if(array_has($params,'tax_types'))
		{
			$this->manageTaxes($region,$params['tax_types']);
		}
		if(array_has($params,'tax_information'))
		{
			$region->tax_information = implode(',',$params['tax_information']);
		}
		$country = $this->locationService->findCountry($params['country']);
		$region->country()->associate($country);
		$region->save();
		return $region;
	}
	protected function manageTaxes(Region $region,Array $taxes)
	{
		if(count($taxes))
		{
			$region->taxTypes()->sync($taxes);
		}
		else
		{
			$region->taxTypes()->sync([]);
		}
	}
	public function product_taxes(int $id,Array $params)
	{
		$region = Region::find($id);
		if(!$region)
		{
			throw new ModelNotFoundException;
		}
		$query = $region->taxes();
		if(array_has($params,'page'))
		{
			return $query->paginate($params['per_page'],['*'],'',$params['page']);
		}
		return $query->get();
	}
	public function update_product_taxes(int $id,Array $params)
	{
		$region = Region::find($id);
		if(!$region)
		{
			throw new ModelNotFoundException;
		}
		$region_product_tax = $region->taxes()->where('id','=',$params['productTaxId'])->first();
		if($region_product_tax)
		{
			$region_product_tax->tax_percent = $params['tax'];
			$region_product_tax->save();
		}
		return $region_product_tax;
	}
	public function add_product_taxes(int $id,Array $params)
	{
		$region = Region::find($id);
		if(!$region)
		{
			throw new ModelNotFoundException;
		}
		if(array_has($params,'collection_code'))
		{
			$tax = $region->taxes()
				->where('category_code','=',$params['category_code'])
				->where('collection_code','=',$params['collection_code'])
				->where('tax_type_code','=',$params['tax_type_code'])->first();
		}
		else
		{
			$tax = $region->taxes()
				->where('category_code','=',$params['category_code'])
				->where('tax_type_code','=',$params['tax_type_code'])->first();
		}
		if($tax)
		{
			throw new RecordAlreadyExistsException;
		}
		$region_product_tax = new Tax;
		$region_product_tax->region_id = $id;
		$region_product_tax->tax_type_code = $params['tax_type_code'];
		$region_product_tax->category_code = $params['category_code'];
		if(array_has($params,'collection_code'))
			$region_product_tax->collection_code = $params['collection_code'];
		$region_product_tax->tax_percent = $params['tax_percent'];
		$region_product_tax->save();
		return $region_product_tax;
	}
	public function delete_product_taxes($regionId, $taxId)
	{
		$region = Region::find($regionId);
		$region_product_tax = $region->taxes()->where('id','=',$taxId)->first();
		$region_product_tax->delete();
	}
}