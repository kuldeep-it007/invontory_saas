<?php

namespace Rosetta\NAV\WS\Inventory;

class ReadByRecId_Result
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
     * @return \Rosetta\NAV\WS\Inventory\ReadByRecId_Result
     */
    public function setInventory($Inventory)
    {
      $this->Inventory = $Inventory;
      return $this;
    }

}
