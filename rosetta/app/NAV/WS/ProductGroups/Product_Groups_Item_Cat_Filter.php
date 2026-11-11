<?php

namespace Rosetta\NAV\WS\ProductGroups;

class Product_Groups_Item_Cat_Filter
{

    /**
     * @var Product_Groups_Item_Cat_Fields $Field
     */
    protected $Field = null;

    /**
     * @var string $Criteria
     */
    protected $Criteria = null;

    /**
     * @param Product_Groups_Item_Cat_Fields $Field
     * @param string $Criteria
     */
    public function __construct($Field, $Criteria)
    {
      $this->Field = $Field;
      $this->Criteria = $Criteria;
    }

    /**
     * @return Product_Groups_Item_Cat_Fields
     */
    public function getField()
    {
      return $this->Field;
    }

    /**
     * @param Product_Groups_Item_Cat_Fields $Field
     * @return \Rosetta\NAV\WS\ProductGroups\Product_Groups_Item_Cat_Filter
     */
    public function setField($Field)
    {
      $this->Field = $Field;
      return $this;
    }

    /**
     * @return string
     */
    public function getCriteria()
    {
      return $this->Criteria;
    }

    /**
     * @param string $Criteria
     * @return \Rosetta\NAV\WS\ProductGroups\Product_Groups_Item_Cat_Filter
     */
    public function setCriteria($Criteria)
    {
      $this->Criteria = $Criteria;
      return $this;
    }

}
