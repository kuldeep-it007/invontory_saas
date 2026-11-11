<?php
namespace Rosetta\Services\ActiveProductAttributes;

use Illuminate\Database\Eloquent\trashed;
use Illuminate\Support\Facades\DB;
use Rosetta\Exceptions\ActiveProductAttributes\RecordAlreadyExistsException;
use Rosetta\Jobs\ProcessActiveProduct;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\Product\ActiveProductAttributes;
use Rosetta\Models\Product\Category;
use Rosetta\Models\Product\Collection;
use Rosetta\Models\Product\Colour;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Services\ActiveProductAttributes\ActiveProductAttributesServiceInterface;
use Rosetta\Services\CartItems\CartItemsServiceInterface;

class ActiveProductAttributesService implements ActiveProductAttributesServiceInterface
{
    protected $cartItemsService;
    public function __construct(CartItemsServiceInterface $cartItemsService)
    {
        $this->cartItemsService = $cartItemsService;
    }
    public function list(Array $params = [], $company, $customerType, $customerId)
    {
    	if($customerType)
        {
            if($customerType == 'distributors')
            {
                $customer = $company->distributors()->find($customerId);
            }
            if($customerType == 'dealers')
            {
                $customer = $company->dealers()->find($customerId);
            }
            if($customerType == 'super_stockists')
            {
                $customer = $company->super_stockists()->find($customerId);
            }
            $query = $customer->activeProductAttributes()->whereNotNull('category_code')->whereNotNull('collection_code');
        }
    	if($company && !($customerType))
    		$query = $company->activeProductAttributes()->whereNotNull('category_code')->whereNotNull('collection_code');
    	if(array_has($params,'category'))
    	{
    		$query = $query->where('category_code','=',$params['category']);
    	}
    	if(array_has($params,'collection'))
    	{
    		$query->where('collection_code','=',$params['collection']);
    	}
    	if(array_has($params,'colour'))
    	{
    		$query->where('colour_code','=',$params['colour']);
    	}
    	if(array_has($params,'page'))
		{
			if($query)
			{
				return $query->paginate($params['per_page'],['*'],'',$params['page']);	
			}
			return Product::paginate($params['per_page'],['*'],'',$params['page']);
		}
		if($query)
		{
			return $query->get();
		}
		return $company->activeProductAttributes()->get();
    }
    public function update(Array $params = [], $company, $attributeId)
    {
        $attribute = ActiveProductAttributes::find($attributeId);   
        $currentCategory = $attribute->category_display;
        $currentCollection = $attribute->collection_display;
        $currentColour = $attribute->colour_display;
        $customer = null;
        if(array_has($params, 'distributorId') && array_has($params, 'dealerId'))
           $customer = $company->dealers()->find($params['dealerId']);
        else if(array_has($params, 'distributorId'))
           $customer = $company->distributors()->find($params['distributorId']);
        else if(array_has($params, 'dealerId'))
           $customer = $company->dealers()->find($params['dealerId']);
        if(!$customer)
        {
            $customer = $company;
        }

        if(!array_has($params, 'colour_display'))
        {
            if(array_has($params, 'category_display') && array_has($params, 'collection_display'))
            {
                if($currentCategory != $params['category_display'] && $currentCollection != $params['collection_display'])
                {
                    $allAttributes = $customer->activeProductAttributes()
                                    ->where('category_code', '=', $attribute->category_code)
                                    ->where('collection_code', '=', $attribute->collection_code)
                                    ->get();
    
                    foreach ($allAttributes as $attribute)
                    {
                        $attribute->category_display = $params['category_display'];
                        $attribute->collection_display = $params['collection_display'];
                        $attribute->save();
                    }
                }
            }
            if(array_has($params, 'category_display') && $currentCategory != $params['category_display'])
            {
                $allAttributes = $customer->activeProductAttributes()
                                ->where('category_code', '=', $attribute->category_code)
                                ->get();
                foreach ($allAttributes as $attribute)
                {
                    $attribute->category_display = $params['category_display'];
                    $attribute->save();
                }
            }
            if(array_has($params, 'collection_display') && $currentCollection != $params['collection_display'])
            {
                $allAttributes = $customer->activeProductAttributes()
                                ->where('category_code', '=', $attribute->category_code)
                                ->where('collection_code', '=', $attribute->collection_code)
                                ->get();
                foreach ($allAttributes as $attribute)
                {
                    $attribute->collection_display = $params['collection_display'];
                    $attribute->save();
                }
            }
            $attribute = ActiveProductAttributes::find($attributeId);
        }
        elseif(array_has($params, 'colour_display') && $currentColour != $params['colour_display'])
        {
            $attribute->colour_display = $params['colour_display'];
            $attribute->save();
        }
        return $attribute;
    }
    public function add(Array $params = [], $company)
    {
        $customer = null;
        $attribute = new ActiveProductAttributes;
        $attribute->category_code = $params['category_code'];
        $attribute->collection_code = $params['collection_code'];
        if(array_has($params, 'colour_code'))
            $attribute->colour_code = $params['colour_code'];
        $attribute->category_display = $params['category_display'];
        $attribute->collection_display = $params['collection_display'];
        if(array_has($params, 'colour_display'))
            $attribute->colour_display = $params['colour_display'];
        $attribute->company_id = $company->id;
        if(array_has($params, 'distributorId') && array_has($params, 'dealerId'))
            $customer = $company->dealers()->find($params['dealerId']);
        else if(array_has($params, 'distributorId'))
            $customer = $company->distributors()->find($params['distributorId']);
        else if(array_has($params, 'dealerId'))
            $customer = $company->dealers()->find($params['dealerId']);
        else if(array_has($params, 'superStockistId'))
            $customer = $company->super_stockists()->find($params['superStockistId']);
        if(!$customer)
        {
            $customer = $company;
            list($recordExists, $noRecord) = $this->find($attribute, $customer);
            if($recordExists)
            {
                throw new RecordAlreadyExistsException;
            }
            if($noRecord)
            {
                $customer->activeProductAttributes()->save($attribute);   
            }
            $queuePrefix = config('brand.queue_prefix');
            $queue = $queuePrefix.'.jobs';
            ProcessActiveProduct::dispatch($attribute, $company)->onQueue($queue);
        }
        else
        {
            list($recordExists, $noRecord) = $this->find($attribute, $customer);
            if($recordExists)
            {
                throw new RecordAlreadyExistsException;
            }
            if($noRecord)
            {
                $customer->activeProductAttributes()->save($attribute);   
            }
        }
    }
    public function find($attribute, $customer)
    {
        $recordExists = false;
        $noRecord = false;
        $query = $customer->activeProductAttributes()
                ->where('company_id', '=', $attribute->company_id)
                ->where('category_code','=',$attribute->category_code)
                ->where('collection_code','=',$attribute->collection_code);
        if($attribute->colour_code)
        {
            $query->where('colour_code','=',$attribute->colour_code);

            $parentQuery = $customer->activeProductAttributes()
                                ->where('company_id', '=', $attribute->company_id)
                                ->where('category_code','=',$attribute->category_code)
                                ->where('collection_code','=',$attribute->collection_code)
                                ->whereNull('colour_code');
            $categoryCollection = $parentQuery->get();
            if(!$categoryCollection->count())
            {
                $categoryCollectionTrashed = $parentQuery->withTrashed()->first();
                if($categoryCollectionTrashed)
                {
                    $parentAttribute = ActiveProductAttributes::withTrashed()->find($categoryCollectionTrashed->id);
                    $parentAttribute->deleted_at = null;
                    $parentAttribute->save();

                    $parentCategoryQuery = $customer->activeProductAttributes()
                                ->where('category_code','=',$attribute->category_code)
                                ->whereNull('collection_code')
                                ->whereNull('colour_code');
                    $parentCategoryAttribute = $parentCategoryQuery->get();//chk if category present

                    if(!$parentCategoryAttribute->count())
                    {
                        $parentCategoryAttributeTrashed = $parentCategoryQuery->withTrashed()->first();
                        if($parentCategoryAttributeTrashed)
                        {
                            $categoryAttribute = ActiveProductAttributes::withTrashed()->find($parentCategoryAttributeTrashed->id);
                            $categoryAttribute->deleted_at = null;
                            $categoryAttribute->save();
                        }
                        else
                        {
                            $newCategory = new ActiveProductAttributes;
                            $newCategory->category_code = $attribute->category_code;
                            $newCategory->category_display = $attribute->category_display;
                            $newCategory->company_id = $attribute->company_id;
                            $customer->activeProductAttributes()->save($newCategory);   
                        }
                    }
                }
                else
                {
                    $newCategoryCollection = new ActiveProductAttributes;
                    $newCategoryCollection->category_code = $attribute->category_code;
                    $newCategoryCollection->collection_code = $attribute->collection_code;
                    $newCategoryCollection->category_display = $attribute->category_display;
                    $newCategoryCollection->collection_display = $attribute->collection_display;
                    $newCategoryCollection->company_id = $attribute->company_id;
                    $customer->activeProductAttributes()->save($newCategoryCollection);   
                }
            }
        }
        else
        {
           $query->whereNull('colour_code');
        }
        $result = $query->get();
        if(!$result->count())
        {
            $result = $query->withTrashed()->first();
            if($result)
            {
                $collectionAttribute = ActiveProductAttributes::withTrashed()->find($result->id);
                $collectionAttribute->deleted_at = null;
                $collectionAttribute->save();

                $parentCategoryQuery = $customer->activeProductAttributes()
                                ->where('category_code','=',$attribute->category_code)
                                ->whereNull('collection_code')
                                ->whereNull('colour_code');
                $parentCategoryAttribute = $parentCategoryQuery->get();//chk if category present

                if(!$parentCategoryAttribute->count())
                {
                    $parentCategoryAttributeTrashed = $parentCategoryQuery->withTrashed()->first();
                    if($parentCategoryAttributeTrashed)
                    {
                        $categoryAttribute = ActiveProductAttributes::withTrashed()->find($parentCategoryAttributeTrashed->id);
                        $categoryAttribute->deleted_at = null;
                        $categoryAttribute->save();
                    }
                    else
                    {
                        $newCategory = new ActiveProductAttributes;
                        $newCategory->category_code = $attribute->category_code;
                        $newCategory->category_display = $attribute->category_display;
                        $newCategory->company_id = $attribute->company_id;
                        $customer->activeProductAttributes()->save($newCategory);   
                    }
                }

            }
            else
            {
                $parentCategoryQuery = $customer->activeProductAttributes()
                                ->where('category_code','=',$attribute->category_code)
                                ->whereNull('collection_code')
                                ->whereNull('colour_code');
                $parentCategoryAttribute = $parentCategoryQuery->get();//chk if category present

                if(!$parentCategoryAttribute->count())
                {
                    $parentCategoryAttributeTrashed = $parentCategoryQuery->withTrashed()->first();
                    if($parentCategoryAttributeTrashed)
                    {
                        $categoryAttribute = ActiveProductAttributes::withTrashed()->find($parentCategoryAttributeTrashed->id);
                        $categoryAttribute->deleted_at = null;
                        $categoryAttribute->save();
                    }
                    else
                    {
                        $newCategory = new ActiveProductAttributes;
                        $newCategory->category_code = $attribute->category_code;
                        $newCategory->category_display = $attribute->category_display;
                        $newCategory->company_id = $attribute->company_id;
                        $customer->activeProductAttributes()->save($newCategory);   
                    }
                }
                $noRecord = true;
            }
        }
        else
        {
            $recordExists = true;
        }
        return [$recordExists, $noRecord];
    }
    public function delete(Array $params = [], $company, $attributeId)
    {
    	$attribute = ActiveProductAttributes::find($attributeId);	
    	$customer = null;
    	
    	if(array_has($params, 'deleteAll') && $params['deleteAll'] == true)
    	{
            $attributeCnt = $company->activeProductAttributes()
                    ->where('category_code', '=', $attribute->category_code)
                    ->whereNotNull('collection_code')
                    ->whereNull('colour_code')
                    ->count();

            if($attributeCnt == 1)
            {
                ActiveProductAttributes::where('company_id', '=', $company->id)
                    ->where('category_code', '=', $attribute->category_code)
                    ->delete(); 
            }
            else
            {
    		  ActiveProductAttributes::where('company_id', '=', $company->id)
    				->where('category_code', '=', $attribute->category_code)
					->where('collection_code', '=', $attribute->collection_code)
					->delete();	
            }
            $this->cartItemsService->findAndDeleteCartItems($attribute, $company, null);
    	}
    	else
    	{
            if(array_has($params, 'distributorId') && array_has($params, 'dealerId'))
            {
                $distributor = $company->distributors()->find($params['distributorId']);
                $customer = $distributor->dealers()->find($params['dealerId']);   
            }
    		else if(array_has($params, 'distributorId'))
	    		$customer = $company->distributors()->find($params['distributorId']);
	    	else if(array_has($params, 'dealerId'))
	    		$customer = $company->dealers()->find($params['dealerId']);
            $attributeCnt = $customer->activeProductAttributes()
                    ->where('category_code', '=', $attribute->category_code)
                    ->whereNotNull('collection_code')
                    ->whereNull('colour_code')
                    ->count();

            if($attributeCnt == 1)
            {
                $customer->activeProductAttributes()
                    ->where('category_code', '=', $attribute->category_code)
                    ->delete(); 
            }
            else
            {
	    	    $customer->activeProductAttributes()
    				->where('category_code', '=', $attribute->category_code)
					->where('collection_code', '=', $attribute->collection_code)
					->delete();
            }
            $this->cartItemsService->findAndDeleteCartItems($attribute, null, $customer);
    	}
    }
	public function deleteAttributesWithColours(Array $params = [], $company, $attributeId)
	{
		$attribute = ActiveProductAttributes::find($attributeId);	
    	$customer = null;
    	if(array_has($params, 'deleteAll') && $params['deleteAll'] == true)//delete records for customers as well
    	{
            $attributeCnt = $company->activeProductAttributes()
                    ->where('category_code', '=', $attribute->category_code)
                    ->where('collection_code', '=', $attribute->collection_code)
                    ->whereNotNull('colour_code')
                    ->count();

            if($attributeCnt == 1)//chk if there is single attribute with colour for this collection, if yes delete the collection as well 
            {
                ActiveProductAttributes::where('company_id', '=', $company->id)
                    ->where('category_code', '=', $attribute->category_code)
                    ->where('collection_code', '=', $attribute->collection_code)
                    ->delete();

                //start delete category if collection not present
                $collectionCnt = $company->activeProductAttributes()
                            ->where('category_code', '=', $attribute->category_code)
                            ->whereNotNull('collection_code')
                            ->whereNull('colour_code')
                            ->count();

                if($collectionCnt == 0)
                {
                    ActiveProductAttributes::where('company_id', '=', $company->id)
                        ->where('category_code', '=', $attribute->category_code)
                        ->delete(); 
                }
                else
                {
                    ActiveProductAttributes::where('company_id', '=', $company->id)
                        ->where('category_code', '=', $attribute->category_code)
                        ->where('collection_code', '=', $attribute->collection_code)
                        ->delete(); 
                }
                //end
            }
    		ActiveProductAttributes::where('company_id', '=', $company->id)
    				->where('category_code', '=', $attribute->category_code)
					->where('collection_code', '=', $attribute->collection_code)
					->where('colour_code', '=', $attribute->colour_code)
					->delete();
            $this->cartItemsService->findAndDeleteCartItems($attribute, $company, null);
    	}
    	else//delete records only for a customer
    	{
    		if(array_has($params, 'distributorId'))
	    		$customer = $company->distributors()->find($params['distributorId']);
	    	if(array_has($params, 'dealerId'))
	    		$customer = $company->dealers()->find($params['dealerId']);
            $attributeCnt = $customer->activeProductAttributes()
                    ->where('category_code', '=', $attribute->category_code)
                    ->where('collection_code', '=', $attribute->collection_code)
                    ->whereNotNull('colour_code')
                    ->count();

            if($attributeCnt == 1)//chk if there is single attribute with colour for this collection, if yes delete the collection as well 
            {
                $customer->activeProductAttributes()
                    ->where('category_code', '=', $attribute->category_code)
                    ->where('collection_code', '=', $attribute->collection_code)
                    ->delete();

                //start delete category if collection not present
                $collectionCnt = $customer->activeProductAttributes()
                            ->where('category_code', '=', $attribute->category_code)
                            ->whereNotNull('collection_code')
                            ->whereNull('colour_code')
                            ->count();

                if($collectionCnt == 0)
                {
                    $customer->activeProductAttributes()
                        ->where('category_code', '=', $attribute->category_code)
                        ->delete(); 
                }
                else
                {
                    $customer->activeProductAttributes()
                        ->where('category_code', '=', $attribute->category_code)
                        ->where('collection_code', '=', $attribute->collection_code)
                        ->delete(); 
                }
                //end
            }
	    	$customer->activeProductAttributes()
    				->where('category_code', '=', $attribute->category_code)
					->where('collection_code', '=', $attribute->collection_code)
					->where('colour_code', '=', $attribute->colour_code)
					->delete();
            $this->cartItemsService->findAndDeleteCartItems($attribute, null, $customer);
    	}
	}
    public function load($companyId, $customerType, $customerId, $parentDistributorId = null, $parentSuperStockistId = null)
    {
        $company = Company::find($companyId);
        if($customerType == 'distributor')
        {
            $customer = Distributor::find($customerId);
            if($customer->super_stockist_id)
                $parentSuperStockistId = $customer->super_stockist_id;
        }
        else if($customerType == 'dealer')
        {
            $customer = Dealer::find($customerId);
            if(!$customer)
            {
                return;
            }
            if($customer->distributor_id)
                $parentDistributorId = $customer->distributor_id;
        }
        else if($customerType == 'superStockist')
        {
            $customer = SuperStockist::find($customerId);
        }

        if($parentDistributorId == null && $parentSuperStockistId == null)
            $parentAttributes = $company->activeProductAttributes;
        else if($parentDistributorId != null)
        {
            $parentDistributor = Distributor::find($parentDistributorId);
            $parentAttributes = $parentDistributor->activeProductAttributes;
        }
        else if($parentSuperStockistId != null)
        {
            $parentSuperStockist = SuperStockist::find($parentSuperStockistId);
            $parentAttributes = $parentSuperStockist->activeProductAttributes;
        }
        foreach($parentAttributes as $parentAttribute)
        {
            $attribute = new ActiveProductAttributes;
            $attribute->company_id = $parentAttribute->company_id;
            $attribute->category_code = $parentAttribute->category_code;
            $attribute->category_display = $parentAttribute->category_display;
            $attribute->collection_code = $parentAttribute->collection_code;
            $attribute->collection_display = $parentAttribute->collection_display;
            $attribute->colour_code = $parentAttribute->colour_code;
            $attribute->colour_display = $parentAttribute->colour_display;
            $customer->activeProductAttributes()->save($attribute);
        }
    }
    public function loadNew($companyId, $customerType, $customerId, $category, $collection, $colour)
    {
        $company = Company::find($companyId);
        if($customerType == 'distributor')
            $customer = Distributor::find($customerId);
        if($customerType == 'dealer')
            $customer = Dealer::find($customerId);
        
        $categoryPresent = $customer->activeProductAttributes()->where('category_code', '=', $category)
                            ->whereNull('collection_code')
                            ->whereNull('colour_code')
                            ->withTrashed()->first();
        if(!$categoryPresent)
        {
            $categoryAttribute = new ActiveProductAttributes;
            $categoryAttribute->company_id = $companyId;
            $categoryAttribute->category_code = $category;
            $categoryAttribute->category_display = $category;
            $customer->activeProductAttributes()->save($categoryAttribute);
        }
        else
        {
            $categoryPresent->deleted_at = null;
            $categoryPresent->save();
        }

        $collectionPresent = $customer->activeProductAttributes()->where('category_code', '=', $category)
                            ->where('collection_code', '=', $collection)
                            ->whereNull('colour_code')
                            ->withTrashed()->first();

        if(!$collectionPresent)
        {
            $collectionAttribute = new ActiveProductAttributes;
            $collectionAttribute->company_id = $companyId;
            $collectionAttribute->category_code = $category;
            $collectionAttribute->category_display = $category;
            $collectionAttribute->collection_code = $collection;
            $collectionAttribute->collection_display = $collection;
            $customer->activeProductAttributes()->save($collectionAttribute);
        }
        else
        {
            $collectionPresent->deleted_at = null;
            $collectionPresent->save();
        }

        if($colour)
        {
            $colourPresent = $customer->activeProductAttributes()->where('category_code', '=', $category)
                            ->where('collection_code', '=', $collection)
                            ->where('colour_code', '=', $colour)
                            ->withTrashed()->first();

            if(!$colourPresent)
            {
                $colourAttribute = new ActiveProductAttributes;
                $colourAttribute->company_id = $companyId;
                $colourAttribute->category_code = $category;
                $colourAttribute->category_display = $category;
                $colourAttribute->collection_code = $collection;
                $colourAttribute->collection_display = $collection;
                $colourAttribute->colour_code = $colour;
                $colourAttribute->colour_display = $colour;
                $customer->activeProductAttributes()->save($colourAttribute);
            }
            else
            {
                $colourPresent->deleted_at = null;
                $colourPresent->save();
            }
        }
    }

    public function getActiveCategoriesForCustomer($company, $customerType, $customerId)
    {
        if($customerType == 'distributors')
        {
            $customer = $company->distributors()->find($customerId);
        }
        if($customerType == 'dealers')
        {
            $customer = $company->dealers()->find($customerId);
        }
        if($customerType == 'super_stockists')
        {
            $customer = $company->super_stockists()->find($customerId);
        }
        \DB::statement("SET sql_mode=''");
        $query = $customer->activeProductAttributes()->whereNotNull('category_code')->whereNull('collection_code')->whereNull('colour_code')->whereNull('deleted_at')->groupBy('category_code');
        return $query->get();
    }

    public function getActiveCollectionsForCustomer($company, $customerType, $customerId,$category)
    {
        if($customerType == 'DISTRIBUTO')
        {
            $customer = $company->distributors()->find($customerId);
        }
        if($customerType == 'DEALER')
        {
            $customer = $company->dealers()->find($customerId);
        }
        if($customerType == 'SUPER_STOCKISTS')
        {
            $customer = $company->super_stockists()->find($customerId);
        }
        \DB::statement("SET sql_mode=''");
        $query = $customer->activeProductAttributes()->where('category_code',$category)->whereNotNull('collection_code')->whereNull('colour_code')->whereNull('deleted_at')->groupBy('collection_code');
        return $query->get();
    }

    public function getActiveColoursForCustomer($company, $customerType, $customerId,$category,$collection)
    {
        if($customerType == 'DISTRIBUTO')
        {
            $customer = $company->distributors()->find($customerId);
        }
        if($customerType == 'DEALER')
        {
            $customer = $company->dealers()->find($customerId);
        }
        if($customerType == 'SUPER_STOCKISTS')
        {
            $customer = $company->super_stockists()->find($customerId);
        }
        \DB::statement("SET sql_mode=''");
        $query = $customer->activeProductAttributes()->where('category_code',$category)->where('collection_code',$collection)->whereNotNull('colour_code')->whereNull('deleted_at')->groupBy('colour_code');
        return $query->get();
    }
}