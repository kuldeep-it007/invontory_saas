<?php

namespace Rosetta\NAV\WS\Inventory;

class Update
{

    /**
     * @var Inventory $Inventory
     */
    protected $Inventory = null;

    /**
     * @param Inventory $Inventory
     */
    public function __construct($Inventory)
    {
      $this->Inventory = $Inventory;
    }

    /**
     * @return Inventory
     */
    public function getInventory()
    {
      return $this->Inventory;
    }

    /**
     * @param Inventory $Inventory
     * @return \Rosetta\NAV\WS\Inventory\Update
     */
    public function setInventory($Inventory)
    {
      $this->Inventory = $Inventory;
      return $this;
    }

}
