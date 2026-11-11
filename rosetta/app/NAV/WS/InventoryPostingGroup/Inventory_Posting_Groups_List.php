<?php

namespace Rosetta\NAV\WS\InventoryPostingGroup;

class Inventory_Posting_Groups_List
{

    /**
     * @var Inventory_Posting_Groups[] $Inventory_Posting_Groups
     */
    protected $Inventory_Posting_Groups = null;

    /**
     * @param Inventory_Posting_Groups[] $Inventory_Posting_Groups
     */
    public function __construct(array $Inventory_Posting_Groups)
    {
      $this->Inventory_Posting_Groups = $Inventory_Posting_Groups;
    }

    /**
     * @return Inventory_Posting_Groups[]
     */
    public function getInventory_Posting_Groups()
    {
      return $this->Inventory_Posting_Groups;
    }

    /**
     * @param Inventory_Posting_Groups[] $Inventory_Posting_Groups
     * @return \Rosetta\NAV\WS\InventoryPostingGroup\Inventory_Posting_Groups_List
     */
    public function setInventory_Posting_Groups(array $Inventory_Posting_Groups)
    {
      $this->Inventory_Posting_Groups = $Inventory_Posting_Groups;
      return $this;
    }

}
