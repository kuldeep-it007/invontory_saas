<?php
namespace Rosetta\Services\Colours;

use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\Product\Colour;
use Rosetta\Models\User;
use Rosetta\Services\Colours\RosColourServiceInterface;
use Rosetta\Services\NAV\ColourServiceInterface;

class RosColourService implements RosColourServiceInterface
{
	protected $colourService;
	public function __construct(ColourServiceInterface $colourService)
	{
		$this->colourService = $colourService;
	}
	public function create(User $user, Array $params, $collection_code)
	{
		$companies = Company::all();
        $companies = $companies->filter(function($item){return $item->id != 2;});//#782

		foreach ($companies as $company)
		{
			$this->colourService->createColour($company, $params, $collection_code);
		}
		$colour = new Colour;
		$colour->code = $params['code'];
		$colour->collection_code = $collection_code;
		$colour->save();
		return $colour;
	}
	public function getActiveColours(User $user, $category_code, $collection_code, $customerType, $customerId)
	{
        $customer = null;
        $company = null;
        if($customerId)//For Colour Search while superadmin adds new product attribute for customer. This shows Company's Colours.
        {
            if($customerType != null && $customerType == 'distributors')
                $customer = Distributor::find($customerId);
            if($customerType != null && $customerType == 'dealers')
                $customer = Dealer::find($customerId);
            $company = $customer->company;
            $activeColourCodes = $company->activeProductColours()
                                ->where('category_code', '=', urldecode($category_code))
                                ->where('collection_code', '=', urldecode($collection_code))
                                ->pluck('colour_code')->toArray();
            $uniqueColours = Colour::where('collection_code', '=', urldecode($collection_code))
                                    ->whereIn('code', $activeColourCodes)->get();
            return [$uniqueColours, $company];
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
       // $colours = Colour::where('collection_code', '=',urldecode($collection_code))->active()->get();
        $colours = Colour::where('collection_code', '=',urldecode($collection_code))->get();
     	$uniqueColours = [];

        // if($customer)
        // {
        //     $colourCnt = $customer->activeProductColours()
        //         ->where('category_code', '=', urldecode($category_code))
        //         ->where('collection_code', '=', urldecode($collection_code))->count();//chk if colour data present in active table for this customer for a specific category/collection. if not take colour data from customers company
        //     if($colourCnt > 0)
        //     {
        //         $activeColourCodes = $customer->activeProductColours()
        //                             ->where('collection_code', '=', urldecode($collection_code))
        //                             ->where('category_code', '=', urldecode($category_code))
        //                             ->pluck('colour_code')->toArray();
        //         $uniqueColours = Colour::where('collection_code', '=', urldecode($collection_code))
        //                                 ->whereIn('code', $activeColourCodes)->get();
        //         return [$uniqueColours, $customer];
        //     }
        //     else
        //     {
        //         $customersCompany = $customer->company;
        //         $activeColourCodes = $customersCompany->activeProductColours()
        //                             ->where('category_code', '=', urldecode($category_code))
        //                             ->where('collection_code', '=', urldecode($collection_code))
        //                             ->pluck('colour_code')->toArray();
        //         $uniqueColours = Colour::where('collection_code', '=', urldecode($collection_code))
        //                                 ->whereIn('code', $activeColourCodes)->get();
        //         return [$uniqueColours, $customersCompany];
        //     }
        // }
        if($company)
        {
            $activeColourCodes = $company->activeProductColours()
                                ->where('category_code', '=', urldecode($category_code))
                                ->where('collection_code', '=', urldecode($collection_code))
                                ->pluck('colour_code')->toArray();
            $uniqueColours = Colour::where('collection_code', '=', urldecode($collection_code))
                                    ->whereIn('code', $activeColourCodes)->get();
            return [$uniqueColours, $company];
        }
        else
        {
            return [$colours, null];
        }
	}
}