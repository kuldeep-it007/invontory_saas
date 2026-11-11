<?php
namespace Rosetta\Services\CoDistributor;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Rosetta\Exceptions\ActiveProductAttributes\RecordAlreadyExistsException;
use Rosetta\Models\CoDistributorDiscount;
use Rosetta\Models\Distributor;
use Rosetta\Models\Product\Product;
use Rosetta\Services\CoDistributor\CoDistributorDiscountServiceInterface;
class CoDistributorDiscountService implements CoDistributorDiscountServiceInterface
{
	public function getDiscount(Distributor $coDistributor,Product $product,Distributor $distributor)
	{
		$discount = 
			(
				CoDistributorDiscount::where('distributor_id', '=', $coDistributor->id)
					->where('customer_id', '=', $distributor->id)
					->where('category_code', '=', $product->category_code)
					->where('collection_code', '=', $product->collection_code)
					->where('colour_code', '=', $product->colour_code)
					->limit(1)
			)->union(
				CoDistributorDiscount::where('distributor_id', '=', $coDistributor->id)
				->where('customer_id', '=', $distributor->id)
				->where('category_code', '=', $product->category_code)
				->where('collection_code', '=', $product->collection_code)
				->whereNull('colour_code')
				->limit(1)
			)->union(
				CoDistributorDiscount::where('distributor_id', '=', $coDistributor->id)
				->where('customer_id', '=', $distributor->id)
				->where('category_code', '=', $product->category_code)
				->whereNull('colour_code')
				->whereNull('collection_code')
				->limit(1)
			)->union(
				CoDistributorDiscount::where('distributor_id', '=', $coDistributor->id)
				->where('customer_id', '=', $distributor->id)
				->whereNull('category_code')
				->whereNull('collection_code')
				->whereNull('colour_code')
				->limit(1)
			)->union(
				CoDistributorDiscount::where('distributor_id', '=', $coDistributor->id)
				->whereNull('customer_id')
				->where('category_code', '=', $product->category_code)
				->where('collection_code', '=', $product->collection_code)
				->where('colour_code', '=', $product->colour_code)
				->limit(1)
			)->union(
				CoDistributorDiscount::where('distributor_id', '=', $coDistributor->id)
				->whereNull('customer_id')
				->where('category_code', '=', $product->category_code)
				->where('collection_code', '=', $product->collection_code)
				->whereNull('colour_code')
				->limit(1)
			)->union(
				CoDistributorDiscount::where('distributor_id', '=', $coDistributor->id)
				->whereNull('customer_id')
				->where('category_code', '=', $product->category_code)
				->whereNull('collection_code')
				->whereNull('colour_code')
				->limit(1)
			)->union(
				CoDistributorDiscount::where('distributor_id', '=', $coDistributor->id)
				->whereNull('customer_id')
				->whereNull('category_code')
				->whereNull('collection_code')
				->whereNull('colour_code')
				->limit(1)
			)->limit(1)->get();
		if($discount->first())
			return $discount->first()->discount;
		return 0;
	}

	public function listCustomerDiscount(Array $params)
	{
		if(array_has($params,'page'))
		{
			$discount = CoDistributorDiscount::where('distributor_id', '=', $params['distributor_id'])->when($params, function ($query) use ($params){
				if(array_has($params, 'customer_id'))
				{
					$query->where('customer_id', '=', $params['customer_id']);
				}
				if(array_has($params, 'category'))
				{
					$query->where('category_code', '=', $params['category']);
				}
				if(array_has($params, 'collection'))
				{
					$query->where('collection_code', '=', $params['collection']);
				}
				if(array_has($params, 'colour'))
				{
					$query->where('colour_code', '=', $params['colour']);
				}
			})->orderBy('customer_id','asc')->orderBy('category_code','asc')->orderBy('collection_code','asc')
			->orderBy('colour_code','asc')->orderBy('discount','asc')->paginate($params['per_page']);
		}
		else
		{
			$discount = CoDistributorDiscount::where('distributor_id', '=', $params['distributor_id'])->when($params, function ($query) use ($params){
				
				if(array_has($params, 'customer_id'))
				{
					$query->where('customer_id', '=', $params['customer_id']);
				}
				if(array_has($params, 'category_code'))
				{
					$query->where('category_code', '=', $params['category_code']);
				}
				if(array_has($params, 'collection_code'))
				{
					$query->where('collection_code', '=', $params['collection_code']);
				}
				if(array_has($params, 'colour_code'))
				{
					$query->where('colour_code', '=', $params['colour_code']);
				}
			})->orderBy('customer_id','asc')->orderBy('category_code','asc')->orderBy('collection_code','asc')
			->orderBy('colour_code','asc')->orderBy('discount','asc')->get();
		}
		return $discount;
	}
	public function delete($id)
	{
		$discount = CoDistributorDiscount::find($id);
		if(!$discount)
			throw New ModelNotFoundException;
		$discount->delete();
	}

	public function create(Array $params)
	{
		$discount = new CoDistributorDiscount;
		$discount->discount = $params['discount'];
		$discount->distributor_id = $params['distributor_id'];

		if(array_has($params, 'category_code'))
			$category = $params['category_code'];
		else
			$category = null;
		if(array_has($params, 'collection_code'))
			$collection = $params['collection_code'];
		else
			$collection = null;
		if(array_has($params, 'colour_code'))
			$colour = $params['colour_code'];
		else
			$colour = null;
		if(array_has($params, 'customer_id'))
		{
			$query = CoDistributorDiscount::where('category_code', '=', $category)
						->where('collection_code', '=', $collection)
						->where('colour_code', '=', $colour)
						->where('distributor_id', '=', $params['distributor_id'])
						->where('customer_id', '=', $params['customer_id'])
						->first();
			if($query == null)
			{
				$discount->customer_id = $params['customer_id'];
				$discount->category_code = $category;
				$discount->collection_code = $collection;
				$discount->colour_code = $colour;
				$discount->save();
				return $discount;
			}
			else
	        {
	            throw new RecordAlreadyExistsException;
	        }
		}
		else
		{
			$query = CoDistributorDiscount::where('category_code', '=', $category)
						->where('collection_code', '=', $collection)
						->where('colour_code', '=', $colour)
						->where('distributor_id', '=', $params['distributor_id'])
						->whereNull('customer_id')
						->first();
			if($query == null)
			{
				$discount->category_code = $category;
				$discount->collection_code = $collection;
				$discount->colour_code = $colour;
				$discount->save();
				return $discount;
			}
			else
	        {
	            throw new RecordAlreadyExistsException;
	        }
		}
	}

	public function update(Array $params, int $id)
	{
		$discount = CoDistributorDiscount::find($id);
		if(!$discount)
			throw New ModelNotFoundException;
		$discount->discount = $params['discount'];
		$discount->save();
		return $discount;
	}
}