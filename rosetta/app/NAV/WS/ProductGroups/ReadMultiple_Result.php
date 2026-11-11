<?php

namespace Rosetta\NAV\WS\ProductGroups;

class ReadMultiple_Result
{

    /**
     * @var Product_Groups_Item_Cat_List $ReadMultiple_Result
     */
    protected $ReadMultiple_Result = null;

    /**
     * @param Product_Groups_Item_Cat_List $ReadMultiple_Result
     */
    public function __construct($ReadMultiple_Result)
    {
      $this->ReadMultiple_Result = $ReadMultiple_Result;
    }

    /**
     * @return Product_Groups_Item_Cat_List
     */
    public function getReadMultiple_Result()
    {
      return $this->ReadMultiple_Result;
    }

    /**
     * @param Product_Groups_Item_Cat_List $ReadMultiple_Result
     * @return \Rosetta\NAV\WS\ProductGroups\ReadMultiple_Result
     */
    public function setReadMultiple_Result($ReadMultiple_Result)
    {
      $this->ReadMultiple_Result = $ReadMultiple_Result;
      return $this;
    }

}
