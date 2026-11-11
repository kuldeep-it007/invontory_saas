<?php

namespace Rosetta\NAV\WS\Inventory;

class Inventory_List
{

    /**
     * @var Inventory[] $Inventory
     */
    protected $Inventory = null;

    /**
     * @param Inventory[] $Inventory
     */
    public function __construct(array $Inventory)
    {
      $this->Inventory = $Inventory;
    }

    /**
     * @return Inventory[]
     */
    public function getInventory()
    {
      return $this->Inventory;
    }

    /**
     * @param Inventory[] $Inventory
     * @return \Rosetta\NAV\WS\Inventory\Inventory_List
     */
    public function setInventory(array $Inventory)
    {
      $this->Inventory = $Inventory;
      return $this;
    }

}
