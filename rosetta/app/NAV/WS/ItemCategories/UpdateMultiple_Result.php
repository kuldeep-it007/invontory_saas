<?php

namespace Rosetta\NAV\WS\ItemCategories;

class UpdateMultiple_Result
{

    /**
     * @var Item_Categories_List $Item_Categories_List
     */
    protected $Item_Categories_List = null;

    /**
     * @param Item_Categories_List $Item_Categories_List
     */
    public function __construct($Item_Categories_List)
    {
      $this->Item_Categories_List = $Item_Categories_List;
    }

    /**
     * @return Item_Categories_List
     */
    public function getItem_Categories_List()
    {
      return $this->Item_Categories_List;
    }

    /**
     * @param Item_Categories_List $Item_Categories_List
     * @return \Rosetta\NAV\WS\ItemCategories\UpdateMultiple_Result
     */
    public function setItem_Categories_List($Item_Categories_List)
    {
      $this->Item_Categories_List = $Item_Categories_List;
      return $this;
    }

}
