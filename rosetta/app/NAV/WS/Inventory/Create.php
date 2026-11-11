<?php

namespace Rosetta\NAV\WS\Inventory;

class Create
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
     * @return \Rosetta\NAV\WS\Inventory\Create
     */
    public function setInventory($Inventory)
    {
      $this->Inventory = $Inventory;
      return $this;
    }

}
