<?php

namespace Rosetta\NAV\WS\Inventory;

class ReadMultiple_Result
{

    /**
     * @var Inventory_List $ReadMultiple_Result
     */
    protected $ReadMultiple_Result = null;

    /**
     * @param Inventory_List $ReadMultiple_Result
     */
    public function __construct($ReadMultiple_Result)
    {
      $this->ReadMultiple_Result = $ReadMultiple_Result;
    }

    /**
     * @return Inventory_List
     */
    public function getReadMultiple_Result()
    {
      return $this->ReadMultiple_Result;
    }

    /**
     * @param Inventory_List $ReadMultiple_Result
     * @return \Rosetta\NAV\WS\Inventory\ReadMultiple_Result
     */
    public function setReadMultiple_Result($ReadMultiple_Result)
    {
      $this->ReadMultiple_Result = $ReadMultiple_Result;
      return $this;
    }

}
