<?php

namespace Rosetta\NAV\WS\InventoryPostingGroup;

class Read_Result
{

    /**
     * @var Inventory_Posting_Groups $Inventory_Posting_Groups
     */
    protected $Inventory_Posting_Groups = null;

    /**
     * @param Inventory_Posting_Groups $Inventory_Posting_Groups
     */
    public function __construct($Inventory_Posting_Groups)
    {
      $this->Inventory_Posting_Groups = $Inventory_Posting_Groups;
    }

    /**
     * @return Inventory_Posting_Groups
     */
    public function getInventory_Posting_Groups()
    {
      return $this->Inventory_Posting_Groups;
    }

    /**
     * @param Inventory_Posting_Groups $Inventory_Posting_Groups
     * @return \Rosetta\NAV\WS\InventoryPostingGroup\Read_Result
     */
    public function setInventory_Posting_Groups($Inventory_Posting_Groups)
    {
      $this->Inventory_Posting_Groups = $Inventory_Posting_Groups;
      return $this;
    }

}
