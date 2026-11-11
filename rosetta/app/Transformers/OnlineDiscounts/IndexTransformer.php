<?php
namespace Rosetta\Transformers\OnlineDiscounts;

use Rosetta\Models\OnlineDiscount;
use League\Fractal\TransformerAbstract;
use Rosetta\Transformers\Product\CategoryTransformer;
use Rosetta\Transformers\Product\CollectionTransformer;
use Rosetta\Transformers\Product\ColourTransformer;

class IndexTransformer extends TransformerAbstract
{
    protected $availableIncludes = ['category','collection','colour'];
    public function transform(OnlineDiscount $discount)
    {
        return [
            "id" => $discount->id,
            "discount" => $discount->discount,
            "created_at" => $discount->created_at->format('U'),
            "updated_at" => $discount->updated_at->format('U'),
        ];
    }
    public function includeCategory(OnlineDiscount $discount)
    {
        if($discount->category)
        {
            return $this->item($discount->category, new CategoryTransformer());
        }
        return null;
    }

    public function includeCollection(OnlineDiscount $discount)
    {
        if($discount->collection)
        {
            return $this->item($discount->collection,new CollectionTransformer());
        }
        return null;
    }

    public function includeColour(OnlineDiscount $discount)
    {
        if($discount->colour)
        {
            return $this->item($discount->colour, new ColourTransformer());
        }
        return null;
    }
}