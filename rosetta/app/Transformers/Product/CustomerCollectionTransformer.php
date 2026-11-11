<?php
namespace Rosetta\Transformers\Product;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Distributor;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Models\Product\ActiveProductAttributes;
use Rosetta\Services\ActiveProductAttributes\ActiveProductAttributesServiceInterface;

class CustomerCollectionTransformer extends TransformerAbstract
{
    protected $availableIncludes = ['colours'];
    /**
     * Undocumented variable
     *
     * @var ActiveProductAttributesServiceInterface
     */
    protected $activeProductAttributesServiceInterface;

    public function __construct()
    {
        $this->activeProductAttributesServiceInterface = resolve(ActiveProductAttributesServiceInterface::class);
    }
    public function transform(ActiveProductAttributes $attr)
    {
        return [
            "code" => $attr->collection_code,
            "display" => $attr->collection_display,
        ];
    }
    public function includeColours(ActiveProductAttributes $attr)
    {
        if(get_class($attr->activable) == SuperStockist::class){
            $customer_type = 'SUPER_STOCKISTS';
        }else if(get_class($attr->activable) == Distributor::class){
            $customer_type = 'DISTRIBUTO';
        }else{
            $customer_type = 'DEALER';
        }
        $collections = $this->activeProductAttributesServiceInterface->getActiveColoursForCustomer($attr->company,$customer_type,$attr->activable_id,$attr->category_code,$attr->collection_code);
        
        return $this->collection($collections, new CustomerColourTransformer);
    }
}
