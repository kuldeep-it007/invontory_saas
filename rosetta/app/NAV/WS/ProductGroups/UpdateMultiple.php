<?php

namespace Rosetta\NAV\WS\ProductGroups;

class UpdateMultiple
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
     * @return \Rosetta\NAV\WS\ProductGroups\UpdateMultiple
     */
    public function setProduct_Groups_Item_Cat_List($Product_Groups_Item_Cat_List)
    {
      $this->Product_Groups_Item_Cat_List = $Product_Groups_Item_Cat_List;
      return $this;
    }

}
