<?php

namespace Rosetta\NAV\WS\ItemCategories;

class ReadMultiple_Result
{

    /**
     * @var Item_Categories_List $ReadMultiple_Result
     */
    protected $ReadMultiple_Result = null;

    /**
     * @param Item_Categories_List $ReadMultiple_Result
     */
    public function __construct($ReadMultiple_Result)
    {
      $this->ReadMultiple_Result = $ReadMultiple_Result;
    }

    /**
     * @return Item_Categories_List
     */
    public function getReadMultiple_Result()
    {
      return $this->ReadMultiple_Result;
    }

    /**
     * @param Item_Categories_List $ReadMultiple_Result
     * @return \Rosetta\NAV\WS\ItemCategories\ReadMultiple_Result
     */
    public function setReadMultiple_Result($ReadMultiple_Result)
    {
      $this->ReadMultiple_Result = $ReadMultiple_Result;
      return $this;
    }

}
