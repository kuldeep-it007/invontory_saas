<?php

namespace Rosetta\NAV\WS\Inventory;

class Inventory
{

    /**
     * @var string $Key
     */
    protected $Key = null;

    /**
     * @var string $No
     */
    protected $No = null;

    /**
     * @var string $Description
     */
    protected $Description = null;

    /**
     * @var string $Base_Unit_of_Measure
     */
    protected $Base_Unit_of_Measure = null;

    /**
     * @var string $Inventory_Posting_Group
     */
    protected $Inventory_Posting_Group = null;

    /**
     * @var float $Inventory
     */
    protected $Inventory = null;

    /**
     * @var float $Qty_on_Purch_Order
     */
    protected $Qty_on_Purch_Order = null;

    /**
     * @var float $Qty_on_Sales_Order
     */
    protected $Qty_on_Sales_Order = null;

    /**
     * @var string $Gen_Prod_Posting_Group
     */
    protected $Gen_Prod_Posting_Group = null;

    /**
     * @var string $Colour
     */
    protected $Colour = null;

    /**
     * @var string $Size
     */
    protected $Size = null;

    
    public function __construct()
    {
    
    }

    /**
     * @return string
     */
    public function getKey()
    {
      return $this->Key;
    }

    /**
     * @param string $Key
     * @return \Rosetta\NAV\WS\Inventory\Inventory
     */
    public function setKey($Key)
    {
      $this->Key = $Key;
      return $this;
    }

    /**
     * @return string
     */
    public function getNo()
    {
      return $this->No;
    }

    /**
     * @param string $No
     * @return \Rosetta\NAV\WS\Inventory\Inventory
     */
    public function setNo($No)
    {
      $this->No = $No;
      return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
      return $this->Description;
    }

    /**
     * @param string $Description
     * @return \Rosetta\NAV\WS\Inventory\Inventory
     */
    public function setDescription($Description)
    {
      $this->Description = $Description;
      return $this;
    }

    /**
     * @return string
     */
    public function getBase_Unit_of_Measure()
    {
      return $this->Base_Unit_of_Measure;
    }

    /**
     * @param string $Base_Unit_of_Measure
     * @return \Rosetta\NAV\WS\Inventory\Inventory
     */
    public function setBase_Unit_of_Measure($Base_Unit_of_Measure)
    {
      $this->Base_Unit_of_Measure = $Base_Unit_of_Measure;
      return $this;
    }

    /**
     * @return string
     */
    public function getInventory_Posting_Group()
    {
      return $this->Inventory_Posting_Group;
    }

    /**
     * @param string $Inventory_Posting_Group
     * @return \Rosetta\NAV\WS\Inventory\Inventory
     */
    public function setInventory_Posting_Group($Inventory_Posting_Group)
    {
      $this->Inventory_Posting_Group = $Inventory_Posting_Group;
      return $this;
    }

    /**
     * @return float
     */
    public function getInventory()
    {
      return $this->Inventory;
    }

    /**
     * @param float $Inventory
     * @return \Rosetta\NAV\WS\Inventory\Inventory
     */
    public function setInventory($Inventory)
    {
      $this->Inventory = $Inventory;
      return $this;
    }

    /**
     * @return float
     */
    public function getQty_on_Purch_Order()
    {
      return $this->Qty_on_Purch_Order;
    }

    /**
     * @param float $Qty_on_Purch_Order
     * @return \Rosetta\NAV\WS\Inventory\Inventory
     */
    public function setQty_on_Purch_Order($Qty_on_Purch_Order)
    {
      $this->Qty_on_Purch_Order = $Qty_on_Purch_Order;
      return $this;
    }

    /**
     * @return float
     */
    public function getQty_on_Sales_Order()
    {
      return $this->Qty_on_Sales_Order;
    }

    /**
     * @param float $Qty_on_Sales_Order
     * @return \Rosetta\NAV\WS\Inventory\Inventory
     */
    public function setQty_on_Sales_Order($Qty_on_Sales_Order)
    {
      $this->Qty_on_Sales_Order = $Qty_on_Sales_Order;
      return $this;
    }

    /**
     * @return string
     */
    public function getGen_Prod_Posting_Group()
    {
      return $this->Gen_Prod_Posting_Group;
    }

    /**
     * @param string $Gen_Prod_Posting_Group
     * @return \Rosetta\NAV\WS\Inventory\Inventory
     */
    public function setGen_Prod_Posting_Group($Gen_Prod_Posting_Group)
    {
      $this->Gen_Prod_Posting_Group = $Gen_Prod_Posting_Group;
      return $this;
    }

    /**
     * @return string
     */
    public function getColour()
    {
      return $this->Colour;
    }

    /**
     * @param string $Colour
     * @return \Rosetta\NAV\WS\Inventory\Inventory
     */
    public function setColour($Colour)
    {
      $this->Colour = $Colour;
      return $this;
    }

    /**
     * @return string
     */
    public function getSize()
    {
      return $this->Size;
    }

    /**
     * @param string $Size
     * @return \Rosetta\NAV\WS\Inventory\Inventory
     */
    public function setSize($Size)
    {
      $this->Size = $Size;
      return $this;
    }

}
