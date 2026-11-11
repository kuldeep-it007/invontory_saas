<?php

namespace Rosetta\NAV\WS\InventoryPostingGroup;

class UpdateMultiple
{

    /**
     * @var Inventory_Posting_Groups_List $Inventory_Posting_Groups_List
     */
    protected $Inventory_Posting_Groups_List = null;

    /**
     * @param Inventory_Posting_Groups_List $Inventory_Posting_Groups_List
     */
    public function __construct($Inventory_Posting_Groups_List)
    {
      $this->Inventory_Posting_Groups_List = $Inventory_Posting_Groups_List;
    }

    /**
     * @return Inventory_Posting_Groups_List
     */
    public function getInventory_Posting_Groups_List()
    {
      return $this->Inventory_Posting_Groups_List;
    }

    /**
     * @param Inventory_Posting_Groups_List $Inventory_Posting_Groups_List
     * @return \Rosetta\NAV\WS\InventoryPostingGroup\UpdateMultiple
     */
    public function setInventory_Posting_Groups_List($Inventory_Posting_Groups_List)
    {
      $this->Inventory_Posting_Groups_List = $Inventory_Posting_Groups_List;
      return $this;
    }

}
