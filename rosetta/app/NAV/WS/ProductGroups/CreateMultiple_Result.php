<?php

namespace Rosetta\NAV\WS\ProductGroups;

class CreateMultiple_Result
{

    /**
     * @var Product_Groups_Item_Cat_List $Product_Groups_Item_Cat_List
     */
    protected $Product_Groups_Item_Cat_List = null;

    /**
     * @param Product_Groups_Item_Cat_List $Product_Groups_Item_Cat_List
     */
    public function __construct($Product_Groups_Item_Cat_List)
    {
      $this->Product_Groups_Item_Cat_List = $Product_Groups_Item_Cat_List;
    }

    /**
     * @return Product_Groups_Item_Cat_List
     */
    public function getProduct_Groups_Item_Cat_List()
    {
      return $this->Product_Groups_Item_Cat_List;
    }

    /**
     * @param Product_Groups_Item_Cat_List $Product_Groups_Item_Cat_List
     * @return \Rosetta\NAV\WS\ProductGroups\CreateMultiple_Result
     */
    public function setProduct_Groups_Item_Cat_List($Product_Groups_Item_Cat_List)
    {
      $this->Product_Groups_Item_Cat_List = $Product_Groups_Item_Cat_List;
      return $this;
    }

}
