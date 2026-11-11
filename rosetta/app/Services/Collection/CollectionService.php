<?php
namespace Rosetta\Services\Collection;

use Illuminate\Support\Facades\DB;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\Product\Category;
use Rosetta\Models\Product\Collection;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Models\User;
use Rosetta\Services\CMS\CMSServiceInterface;
use Rosetta\Services\Collection\CollectionServiceInterface;
use Rosetta\Services\NAV\ProductGroupsServiceInterface;


class CollectionService implements CollectionServiceInterface
{
	protected $collectionService;
	protected $cmsService;
	public function __construct(ProductGroupsServiceInterface $collectionService,CMSServiceInterface $cmsService)
	{
		$this->collectionService = $collectionService;
		$this->cmsService = $cmsService;
	}
	public function create(User $user, Array $params, $category_code)
	{
		$companies = Company::all();
        $companies = $companies->filter(function($item){return $item->id != 2;});//#782
		foreach ($companies as $company)
		{
			$this->collectionService->createCollection($company, $params, $category_code);
		}
		$collection = new Collection;
		$collection->code = $params['code'];
		$collection->category_code = $category_code;
		if(array_has($params, 'description'))
			$collection->description= $params['description'];
		if(array_has($params, 'in_multiples'))
			$collection->in_multiples = $params['in_multiples'];
        if(array_has($params, 'plank_size'))
            $collection->plank_size = $params['plank_size'];
		$collection->save();
		return $collection;
	}
	public function getTechSpecs($category_code,$collection_code)
	{
		$category = Category::find($category_code);
		$collection = $category->collections()->find($collection_code);
		return $this->cmsService->getTechSpecs($category->code,$collection->code);

	}
	public function getActiveCollections(User $user, $category_code, $customerType, $customerId)
	{
        $customer = null;
        $company = null;
        if($customerId)//For Collection Search while superadmin adds new product attribute for customer. This shows Company's Collections.
        {
            if($customerType != null && $customerType == 'distributors')
                $customer = Distributor::find($customerId);
            if($customerType != null && $customerType == 'dealers')
                $customer = Dealer::find($customerId);
            if($customerType != null && $customerType == 'super_stokist')
                $customer = SuperStockist::find($customerId);

            $company = $customer->company;
            $activeCollectionCodes = $company->activeProductCollections()->where('category_code', '=', urldecode($category_code))->pluck('collection_code')->toArray();
            $uniqueCollections = Collection::where('category_code', '=', urldecode($category_code))
                                            ->whereIn('code', $activeCollectionCodes)->orderBy('code')->get();
            return [$uniqueCollections, $company];
        }
        else
        {
            if(!empty($user->distributor))
                $customer = $user->distributor;
            if(!empty($user->dealer))
                $customer = $user->dealer;
            if(!empty($user->superStockist))
                $customer = $user->superStockist;
            if(!empty($user->company))
                $company = $user->company;
            if(!empty($customer->company))
                $company = $customer->company;
        }
        //$collections = Collection::where('category_code', '=',urldecode($category_code))->active()->get();
        $collections = Collection::where('category_code', '=',urldecode($category_code))->get();
     	$uniqueCollections = [];
        // if($customer)
        // {
        //     if($customer->activeProductAttributes()->count() > 0)//chk if category/collection data present in active table for this customer
        //     {
        //         $activeCollectionCodes = $customer->activeProductCollections()->where('category_code', '=', urldecode($category_code))->pluck('collection_code')->toArray();
        //         $uniqueCollections = Collection::where('category_code', '=', urldecode($category_code))
        //                                         ->whereIn('code', $activeCollectionCodes)->orderBy('code')->get();
        //         return [$uniqueCollections, $customer];
        //     }
        //     else
        //     {
        //         $customersCompany = $customer->company;
        //         $activeCollectionCodes = $customersCompany->activeProductCollections()->where('category_code', '=', urldecode($category_code))->pluck('collection_code')->toArray();
        //         $uniqueCollections = Collection::where('category_code', '=', urldecode($category_code))
        //                                         ->whereIn('code', $activeCollectionCodes)->orderBy('code')->get();
        //         return [$uniqueCollections, $customersCompany];
        //     }
        // }
        if($company)
        {
            $activeCollectionCodes = $company->activeProductCollections()->where('category_code', '=', urldecode($category_code))->pluck('collection_code')->toArray();
            $uniqueCollections = Collection::where('category_code', '=', urldecode($category_code))
                                            ->whereIn('code', $activeCollectionCodes)->orderBy('code')->get();
            return [$uniqueCollections, $company];
        }
        if(!$customer && !$company)
        {
            return [$collections, null];
        }
        else
        {
            return [null, null];
        }
	}
}