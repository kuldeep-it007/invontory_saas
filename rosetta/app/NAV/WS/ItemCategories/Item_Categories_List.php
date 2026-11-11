<?php

namespace Rosetta\NAV\WS\ItemCategories;

class Item_Categories_List
{

    /**
     * @var Item_Categories[] $Item_Categories
     */
    protected $Item_Categories = null;

    /**
     * @param Item_Categories[] $Item_Categories
     */
    public function __construct(array $Item_Categories)
    {
      $this->Item_Categories = $Item_Categories;
    }

    /**
     * @return Item_Categories[]
     */
    public function getItem_Categories()
    {
      return $this->Item_Categories;
    }

    /**
     * @param Item_Categories[] $Item_Categories
     * @return \Rosetta\NAV\WS\ItemCategories\Item_Categories_List
     */
    public function setItem_Categories(array $Item_Categories)
    {
      $this->Item_Categories = $Item_Categories;
      return $this;
    }

}
