<?php

namespace Rosetta\NAV\WS\Inventory;

class CreateMultiple_Result
{

    /**
     * @var Inventory_List $Inventory_List
     */
    protected $Inventory_List = null;

    /**
     * @param Inventory_List $Inventory_List
     */
    public function __construct($Inventory_List)
    {
      $this->Inventory_List = $Inventory_List;
    }

    /**
     * @return Inventory_List
     */
    public function getInventory_List()
    {
      return $this->Inventory_List;
    }

    /**
     * @param Inventory_List $Inventory_List
     * @return \Rosetta\NAV\WS\Inventory\CreateMultiple_Result
     */
    public function setInventory_List($Inventory_List)
    {
      $this->Inventory_List = $Inventory_List;
      return $this;
    }

}
