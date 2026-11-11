<?php
namespace Rosetta\Services\Categories;

use Illuminate\Support\Facades\DB;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\Product\Category;
use Rosetta\Models\User;
use Rosetta\Services\Categories\CategoryServiceInterface;
use Rosetta\Services\NAV\ItemCategoriesServiceInterface;
class CategoryService implements CategoryServiceInterface
{
	protected $itemCategoryService;
	public function __construct(ItemCategoriesServiceInterface $itemCategoryService)
	{
		$this->itemCategoryService = $itemCategoryService;
	}
	public function create(User $user, Array $params)
	{
		$companies = Company::all();
		$companies = $companies->filter(function($item){return $item->id != 2;});//#782
		foreach ($companies as $company)
		{
			$this->itemCategoryService->createCategory($company, $params);
		}
		$category = new Category;
		$category->code = $params['code'];
		$category->description= $params['description'];
		if(array_has($params, 'allow_cl'))
			$category->allow_cl = $params['allow_cl'];
		if(array_has($params, 'cl_percent') && (array_has($params, 'allow_cl') && $params['allow_cl'] == true))
		{
			$category->cl_percent = $params['cl_percent'];
		}
		else
		{
			$category->cl_percent = 0;
		}
		if(array_has($params, 'in_multiples'))
			$category->in_multiples = $params['in_multiples'];
		if(array_has($params, 'plank_size'))
			$category->plank_size = $params['plank_size'];
		$category->save();
		return $category;
	}
	public function update(User $user, Array $params, $code)
	{
		$category = Category::where("code", "=", $code)->get()->first();
		if($category)
		{
			if(array_has($params, 'description'))
				$category->description= $params['description'];
			if(array_has($params, 'allow_cl'))
				$category->allow_cl = $params['allow_cl'];
			if(array_has($params, 'cl_percent') && (array_has($params, 'allow_cl') && $params['allow_cl'] == true))
			{
				$category->cl_percent = $params['cl_percent'];
			}
			else
			{
				$category->cl_percent = 0;
			}
			if(array_has($params, 'in_multiples'))
				$category->in_multiples = $params['in_multiples'];
			if(array_has($params, 'plank_size'))
				$category->plank_size = $params['plank_size'];
			$category->save();
			return $category;
		}	
	}
	public function getActiveCategories(User $user, $customerId = null, $customerType = null,$onlyCl=false)
	{	
		$customer = null;
        $company = null;
        if($customerId)//For Category Search while superadmin adds new product attribute for customer. This shows Company's Categories.
        {
        	if($customerType != null && $customerType == 'distributors')
        		$customer = Distributor::find($customerId);
        	if($customerType != null && $customerType == 'dealers')
        		$customer = Dealer::find($customerId);
            $company = $customer->company;
            $activeCategoryCodes = $company->activeProductCategories()->pluck('category_code')->toArray();
            $uniqueCategoriesQuery = Category::whereIn('code', $activeCategoryCodes);
            if($onlyCl)
            	$uniqueCategoriesQuery->where('allow_cl',true);
            $uniqueCategories = $uniqueCategoriesQuery->get(); 
            return [$uniqueCategories, $company];
        }
        else
        {
        	if($user->distributor)
           		$customer = $user->distributor;
	        if($user->dealer)
	            $customer = $user->dealer;
	        if($user->company)
	            $company = $user->company;
        }
        $categoriesQuery = Category::active();
        if($onlyCl)
            $categoriesQuery->where('allow_cl',true);
        $categories = $categoriesQuery->get();
     	$uniqueCategories = [];
        if($customer)
        {
            if($customer->activeProductAttributes()->count() > 0)
            {
                $activeCategoryCodes = $customer->activeProductCategories()->pluck('category_code')->toArray();
                $uniqueCategoriesQuery = Category::whereIn('code', $activeCategoryCodes);
                if($onlyCl)
            		$uniqueCategoriesQuery->where('allow_cl',true);
            	$uniqueCategories = $uniqueCategoriesQuery->get();
                return [$uniqueCategories, $customer];
            }
            else
            {
                $customersCompany = $customer->company;
                $activeCategoryCodes = $customersCompany->activeProductCategories()->pluck('category_code')->toArray();
                $uniqueCategoriesQuery= Category::whereIn('code', $activeCategoryCodes);
                if($onlyCl)
            		$uniqueCategoriesQuery->where('allow_cl',true);
            	$uniqueCategories = $uniqueCategoriesQuery->get();
                return [$uniqueCategories, $customersCompany];
            }
        }
        if($company)
        {
        	$activeCategoryCodes = $company->activeProductCategories()->pluck('category_code')->toArray();
            $uniqueCategoriesQuery= Category::whereIn('code', $activeCategoryCodes);
            if($onlyCl)
            	$uniqueCategoriesQuery->where('allow_cl',true);
            $uniqueCategories = $uniqueCategoriesQuery->get();
            return [$uniqueCategories, $company];
        }
        else
        {
            return [$categories, null];
        }
	}
}