<?php
namespace Rosetta\Transformers\Product;

use League\Fractal\TransformerAbstract;
use Rosetta\Models\Product\ActiveProductAttributes;
use Rosetta\Services\ActiveProductAttributes\ActiveProductAttributesServiceInterface;

class CustomerColourTransformer extends TransformerAbstract
{
    protected $activeProductAttributesServiceInterface;

    public function __construct()
    {
        $this->activeProductAttributesServiceInterface = resolve(ActiveProductAttributesServiceInterface::class);
    }
    public function transform(ActiveProductAttributes $attr)
    {
        return [
            "code" => $attr->colour_code,
            "display" => $attr->colour_display,
        ];
    }
}
