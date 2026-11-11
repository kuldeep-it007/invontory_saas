<?php

namespace Rosetta\NAV\WS\ItemCategories;

class Item_Categories_Filter
{

    /**
     * @var Item_Categories_Fields $Field
     */
    protected $Field = null;

    /**
     * @var string $Criteria
     */
    protected $Criteria = null;

    /**
     * @param Item_Categories_Fields $Field
     * @param string $Criteria
     */
    public function __construct($Field, $Criteria)
    {
      $this->Field = $Field;
      $this->Criteria = $Criteria;
    }

    /**
     * @return Item_Categories_Fields
     */
    public function getField()
    {
      return $this->Field;
    }

    /**
     * @param Item_Categories_Fields $Field
     * @return \Rosetta\NAV\WS\ItemCategories\Item_Categories_Filter
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
     * @return \Rosetta\NAV\WS\ItemCategories\Item_Categories_Filter
     */
    public function setCriteria($Criteria)
    {
      $this->Criteria = $Criteria;
      return $this;
    }

}
