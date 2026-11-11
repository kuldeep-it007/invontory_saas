<?php
namespace Rosetta\Services\CLRate;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Rosetta\Exceptions\CLRate\CLRateExistsException;
use Rosetta\Models\CLRate;
use Rosetta\Services\CLRate\CLRateServiceInterface;
class CLRateService implements CLRateServiceInterface
{
	public function getAll($model,Array $params = [])
	{
		$query = $model->cl_rates();
		

		if(array_has($params,'customer_type'))
		{
			if($params['customer_type'] == 'DEALER')
			{
				$query->where('related_type','=','DEALER');
			}
			if($params['customer_type'] == 'DISTRIBUTO')
			{
				$query->where('related_type','=','DISTRIBUTO');
			}
			if($params['customer_type'] == 'SUPER_STOCKIST')
			{
				$query->where('related_type','=','SUPER_STOCKIST');
			}
			if($params['customer_type'] == 'SUPER_STOCKIST_DISTRIBUTOR')
			{
				$query->where('related_type','=','SUPER_STOCKIST_DISTRIBUTOR');
			}
			if($params['customer_type'] == 'SUPER_STOCKIST_DEALER')
			{
				$query->where('related_type','=','SUPER_STOCKIST_DEALER');
			}
		}
		if(array_has($params,'customer_id'))
		{
			$query->where('related_id','=',$params['customer_id']);
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
		

		if(array_has($params,'page'))
		{
			return $query->paginate($params['per_page']);
		}
		return $query->get();
	}
	public function create($model,Array $params)
	{
		$cl_rates = $this->getAll($model,$params);
		if(count($cl_rates))
		{
			throw new CLRateExistsException;
		}
		$clRate = new CLRate;
		$clRate->percent = $params['rate'];
		$clRate->related_type = $params['customer_type'];
		if(array_has($params,'customer_id'))
			$clRate->related_id = $params['customer_id'];
		if(array_has($params,'category'))
			$clRate->category_code = $params['category'];

		if(array_has($params,'collection'))
			$clRate->collection_code = $params['collection'];

		if(array_has($params,'colour'))
			$clRate->colour_code = $params['colour'];

		$model->cl_rates()->save($clRate);
		return $clRate;
	}
	public function delete($model,$clId)
	{
		$clRate = $model->cl_rates()->find($clId);
		if(!$clRate)
			throw new ModelNotFoundException;
		$clRate->delete();
	}


	public function getRate($model,$product,$related_type = null,$related_id = null)
	{
		$percent = 
			(
				$model->cl_rates()->where('related_type', '=', $related_type)
					->where('related_id', '=', $related_id)
					->where('category_code', '=', $product->category_code)
					->where('collection_code', '=', $product->collection_code)
					->where('colour_code', '=', $product->colour_code)
					->limit(1)
			)->union(
				$model->cl_rates()->where('related_type', '=', $related_type)
				->where('related_id', '=', $related_id)
				->where('category_code', '=', $product->category_code)
				->where('collection_code', '=', $product->collection_code)
				->whereNull('colour_code')
				->limit(1)
			)->union(
				$model->cl_rates()->where('related_type', '=', $related_type)
				->where('related_id', '=', $related_id)
				->where('category_code', '=', $product->category_code)
				->whereNull('colour_code')
				->whereNull('collection_code')
				->limit(1)
			)->union(
				$model->cl_rates()->where('related_type', '=', $related_type)
				->where('related_id', '=', $related_id)
				->whereNull('category_code')
				->whereNull('collection_code')
				->whereNull('colour_code')
				->limit(1)
			)->union(
				$model->cl_rates()->where('related_type', '=', $related_type)
				->whereNull('related_id')
				->where('category_code', '=', $product->category_code)
				->where('collection_code', '=', $product->collection_code)
				->where('colour_code', '=', $product->colour_code)
				->limit(1)
			)->union(
				$model->cl_rates()->where('related_type', '=', $related_type)
				->whereNull('related_id')
				->where('category_code', '=', $product->category_code)
				->where('collection_code', '=', $product->collection_code)
				->whereNull('colour_code')
				->limit(1)
			)->union(
				$model->cl_rates()->where('related_type', '=', $related_type)
				->whereNull('related_id')
				->where('category_code', '=', $product->category_code)
				->whereNull('collection_code')
				->whereNull('colour_code')
				->limit(1)
			)->union(
				$model->cl_rates()->where('related_type', '=', $related_type)
				->whereNull('related_id')
				->whereNull('category_code')
				->whereNull('collection_code')
				->whereNull('colour_code')
				->limit(1)
			)->limit(1)->get();
		if($percent->first())
			return $percent->first()->percent;
		return 0;
	}	
}