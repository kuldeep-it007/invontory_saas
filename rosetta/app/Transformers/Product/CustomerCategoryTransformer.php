<?php
namespace Rosetta\Transformers\Product;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Distributor;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Models\Product\ActiveProductAttributes;
use Rosetta\Services\ActiveProductAttributes\ActiveProductAttributesServiceInterface;

class CustomerCategoryTransformer extends TransformerAbstract
{
    protected $availableIncludes = ['collections'];
    protected $activeProductAttributesServiceInterface;

    public function __construct()
    {
        $this->activeProductAttributesServiceInterface =  resolve(ActiveProductAttributesServiceInterface::class);
    }
    public function transform(ActiveProductAttributes $attr)
    {
        return [
            "code" => $attr->category_code,
            "display" => $attr->category_display,
        ];
    }
    public function includeCollections(ActiveProductAttributes $attr)
    {
        if(get_class($attr->activable) == SuperStockist::class){
            $customer_type = 'SUPER_STOCKISTS';
        }else if(get_class($attr->activable) == Distributor::class){
            $customer_type = 'DISTRIBUTO';
        }else{
            $customer_type = 'DEALER';
        }

        $collections = $this->activeProductAttributesServiceInterface->getActiveCollectionsForCustomer($attr->company,$customer_type,$attr->activable_id,$attr->category_code);

        return $this->collection($collections, new CustomerCollectionTransformer);
    }
}