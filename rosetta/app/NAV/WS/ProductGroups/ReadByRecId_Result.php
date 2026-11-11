<?php

namespace Rosetta\NAV\WS\ProductGroups;

class ReadByRecId_Result
{

    /**
     * @var Product_Groups_Item_Cat $Product_Groups_Item_Cat
     */
    protected $Product_Groups_Item_Cat = null;

    /**
     * @param Product_Groups_Item_Cat $Product_Groups_Item_Cat
     */
    public function __construct($Product_Groups_Item_Cat)
    {
      $this->Product_Groups_Item_Cat = $Product_Groups_Item_Cat;
    }

    /**
     * @return Product_Groups_Item_Cat
     */
    public function getProduct_Groups_Item_Cat()
    {
      return $this->Product_Groups_Item_Cat;
    }

    /**
     * @param Product_Groups_Item_Cat $Product_Groups_Item_Cat
     * @return \Rosetta\NAV\WS\ProductGroups\ReadByRecId_Result
     */
    public function setProduct_Groups_Item_Cat($Product_Groups_Item_Cat)
    {
      $this->Product_Groups_Item_Cat = $Product_Groups_Item_Cat;
      return $this;
    }

}
