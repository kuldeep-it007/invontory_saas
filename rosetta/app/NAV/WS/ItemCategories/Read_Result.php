<?php

namespace Rosetta\NAV\WS\ItemCategories;

class Read_Result
{

    /**
     * @var Item_Categories $Item_Categories
     */
    protected $Item_Categories = null;

    /**
     * @param Item_Categories $Item_Categories
     */
    public function __construct($Item_Categories)
    {
      $this->Item_Categories = $Item_Categories;
    }

    /**
     * @return Item_Categories
     */
    public function getItem_Categories()
    {
      return $this->Item_Categories;
    }

    /**
     * @param Item_Categories $Item_Categories
     * @return \Rosetta\NAV\WS\ItemCategories\Read_Result
     */
    public function setItem_Categories($Item_Categories)
    {
      $this->Item_Categories = $Item_Categories;
      return $this;
    }

}
