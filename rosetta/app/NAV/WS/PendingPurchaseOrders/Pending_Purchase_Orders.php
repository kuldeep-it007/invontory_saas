<?php

namespace Rosetta\NAV\WS\PendingPurchaseOrders;

class Pending_Purchase_Orders
{

    /**
     * @var string $Key
     */
    protected $Key = null;

    /**
     * @var string $Document_No
     */
    protected $Document_No = null;

    /**
     * @var int $Line_No
     */
    protected $Line_No = null;

    /**
     * @var string $Item_No
     */
    protected $Item_No = null;

    /**
     * @var string $Location_Code
     */
    protected $Location_Code = null;

    /**
     * @var string $Description
     */
    protected $Description = null;

    /**
     * @var float $Reserved_Qty
     */
    protected $Reserved_Qty = null;

    /**
     * @var float $Outstanding_Qty
     */
    protected $Outstanding_Qty = null;

    /**
     * @var float $Quantity_Base
     */
    protected $Quantity_Base = null;

    /**
     * @var string $Item_Category_Code
     */
    protected $Item_Category_Code = null;

    /**
     * @var date $Order_Date
     */
    protected $Order_Date = null;

    /**
     * @var date $Expected_Receipt_Date
     */
    protected $Expected_Receipt_Date = null;

    /**
     * @var string $Collection
     */
    protected $Collection = null;

    /**
     * @var string $Color
     */
    protected $Color = null;

    /**
     * @var date $Planned_Receipt_Date
     */
    protected $Planned_Receipt_Date = null;

    
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
     * @return \Rosetta\NAV\WS\PendingPurchaseOrders\Pending_Purchase_Orders
     */
    public function setKey($Key)
    {
      $this->Key = $Key;
      return $this;
    }

    /**
     * @return string
     */
    public function getDocument_No()
    {
      return $this->Document_No;
    }

    /**
     * @param string $Document_No
     * @return \Rosetta\NAV\WS\PendingPurchaseOrders\Pending_Purchase_Orders
     */
    public function setDocument_No($Document_No)
    {
      $this->Document_No = $Document_No;
      return $this;
    }

    /**
     * @return int
     */
    public function getLine_No()
    {
      return $this->Line_No;
    }

    /**
     * @param int $Line_No
     * @return \Rosetta\NAV\WS\PendingPurchaseOrders\Pending_Purchase_Orders
     */
    public function setLine_No($Line_No)
    {
      $this->Line_No = $Line_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getItem_No()
    {
      return $this->Item_No;
    }

    /**
     * @param string $Item_No
     * @return \Rosetta\NAV\WS\PendingPurchaseOrders\Pending_Purchase_Orders
     */
    public function setItem_No($Item_No)
    {
      $this->Item_No = $Item_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getLocation_Code()
    {
      return $this->Location_Code;
    }

    /**
     * @param string $Location_Code
     * @return \Rosetta\NAV\WS\PendingPurchaseOrders\Pending_Purchase_Orders
     */
    public function setLocation_Code($Location_Code)
    {
      $this->Location_Code = $Location_Code;
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
     * @return \Rosetta\NAV\WS\PendingPurchaseOrders\Pending_Purchase_Orders
     */
    public function setDescription($Description)
    {
      $this->Description = $Description;
      return $this;
    }

    /**
     * @return float
     */
    public function getReserved_Qty()
    {
      return $this->Reserved_Qty;
    }

    /**
     * @param float $Reserved_Qty
     * @return \Rosetta\NAV\WS\PendingPurchaseOrders\Pending_Purchase_Orders
     */
    public function setReserved_Qty($Reserved_Qty)
    {
      $this->Reserved_Qty = $Reserved_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getOutstanding_Qty()
    {
      return $this->Outstanding_Qty;
    }

    /**
     * @param float $Outstanding_Qty
     * @return \Rosetta\NAV\WS\PendingPurchaseOrders\Pending_Purchase_Orders
     */
    public function setOutstanding_Qty($Outstanding_Qty)
    {
      $this->Outstanding_Qty = $Outstanding_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getQuantity_Base()
    {
      return $this->Quantity_Base;
    }

    /**
     * @param float $Quantity_Base
     * @return \Rosetta\NAV\WS\PendingPurchaseOrders\Pending_Purchase_Orders
     */
    public function setQuantity_Base($Quantity_Base)
    {
      $this->Quantity_Base = $Quantity_Base;
      return $this;
    }

    /**
     * @return string
     */
    public function getItem_Category_Code()
    {
      return $this->Item_Category_Code;
    }

    /**
     * @param string $Item_Category_Code
     * @return \Rosetta\NAV\WS\PendingPurchaseOrders\Pending_Purchase_Orders
     */
    public function setItem_Category_Code($Item_Category_Code)
    {
      $this->Item_Category_Code = $Item_Category_Code;
      return $this;
    }

    /**
     * @return date
     */
    public function getOrder_Date()
    {
      return $this->Order_Date;
    }

    /**
     * @param date $Order_Date
     * @return \Rosetta\NAV\WS\PendingPurchaseOrders\Pending_Purchase_Orders
     */
    public function setOrder_Date($Order_Date)
    {
      $this->Order_Date = $Order_Date;
      return $this;
    }

    /**
     * @return date
     */
    public function getExpected_Receipt_Date()
    {
      return $this->Expected_Receipt_Date;
    }

    /**
     * @param date $Expected_Receipt_Date
     * @return \Rosetta\NAV\WS\PendingPurchaseOrders\Pending_Purchase_Orders
     */
    public function setExpected_Receipt_Date($Expected_Receipt_Date)
    {
      $this->Expected_Receipt_Date = $Expected_Receipt_Date;
      return $this;
    }

    /**
     * @return string
     */
    public function getCollection()
    {
      return $this->Collection;
    }

    /**
     * @param string $Collection
     * @return \Rosetta\NAV\WS\PendingPurchaseOrders\Pending_Purchase_Orders
     */
    public function setCollection($Collection)
    {
      $this->Collection = $Collection;
      return $this;
    }

    /**
     * @return string
     */
    public function getColor()
    {
      return $this->Color;
    }

    /**
     * @param string $Color
     * @return \Rosetta\NAV\WS\PendingPurchaseOrders\Pending_Purchase_Orders
     */
    public function setColor($Color)
    {
      $this->Color = $Color;
      return $this;
    }

    /**
     * @return date
     */
    public function getPlanned_Receipt_Date()
    {
      return $this->Planned_Receipt_Date;
    }

    /**
     * @param date $Planned_Receipt_Date
     * @return \Rosetta\NAV\WS\PendingPurchaseOrders\Pending_Purchase_Orders
     */
    public function setPlanned_Receipt_Date($Planned_Receipt_Date)
    {
      $this->Planned_Receipt_Date = $Planned_Receipt_Date;
      return $this;
    }

}
