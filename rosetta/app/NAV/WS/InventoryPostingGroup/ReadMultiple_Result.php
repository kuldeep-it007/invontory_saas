<?php

namespace Rosetta\NAV\WS\InventoryPostingGroup;

class ReadMultiple_Result
{

    /**
     * @var Inventory_Posting_Groups_List $ReadMultiple_Result
     */
    protected $ReadMultiple_Result = null;

    /**
     * @param Inventory_Posting_Groups_List $ReadMultiple_Result
     */
    public function __construct($ReadMultiple_Result)
    {
      $this->ReadMultiple_Result = $ReadMultiple_Result;
    }

    /**
     * @return Inventory_Posting_Groups_List
     */
    public function getReadMultiple_Result()
    {
      return $this->ReadMultiple_Result;
    }

    /**
     * @param Inventory_Posting_Groups_List $ReadMultiple_Result
     * @return \Rosetta\NAV\WS\InventoryPostingGroup\ReadMultiple_Result
     */
    public function setReadMultiple_Result($ReadMultiple_Result)
    {
      $this->ReadMultiple_Result = $ReadMultiple_Result;
      return $this;
    }

}
