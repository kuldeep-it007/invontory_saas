<?php

namespace Rosetta\NAV\WS\SalesLines;

class SalesLines
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
     * @var string $Sell_to_Customer_No
     */
    protected $Sell_to_Customer_No = null;

    /**
     * @var int $Line_No
     */
    protected $Line_No = null;

    /**
     * @var Type $Type
     */
    protected $Type = null;

    /**
     * @var string $No
     */
    protected $No = null;

    /**
     * @var string $Variant_Code
     */
    protected $Variant_Code = null;

    /**
     * @var string $Description
     */
    protected $Description = null;

    /**
     * @var string $Location_Code
     */
    protected $Location_Code = null;

    /**
     * @var Reserve $Reserve
     */
    protected $Reserve = null;

    /**
     * @var float $Quantity
     */
    protected $Quantity = null;

    /**
     * @var float $Reserved_Qty_Base
     */
    protected $Reserved_Qty_Base = null;

    /**
     * @var string $Unit_of_Measure_Code
     */
    protected $Unit_of_Measure_Code = null;

    /**
     * @var float $Line_Amount
     */
    protected $Line_Amount = null;

    /**
     * @var string $PO_No
     */
    protected $PO_No = null;

    /**
     * @var string $Shortcut_Dimension_1_Code
     */
    protected $Shortcut_Dimension_1_Code = null;

    /**
     * @var string $Shortcut_Dimension_2_Code
     */
    protected $Shortcut_Dimension_2_Code = null;

    /**
     * @var string $ShortcutDimCode_x005B_3_x005D_
     */
    protected $ShortcutDimCode_x005B_3_x005D_ = null;

    /**
     * @var string $ShortcutDimCode_x005B_4_x005D_
     */
    protected $ShortcutDimCode_x005B_4_x005D_ = null;

    /**
     * @var string $ShortcutDimCode_x005B_5_x005D_
     */
    protected $ShortcutDimCode_x005B_5_x005D_ = null;

    /**
     * @var string $ShortcutDimCode_x005B_6_x005D_
     */
    protected $ShortcutDimCode_x005B_6_x005D_ = null;

    /**
     * @var string $ShortcutDimCode_x005B_7_x005D_
     */
    protected $ShortcutDimCode_x005B_7_x005D_ = null;

    /**
     * @var string $ShortcutDimCode_x005B_8_x005D_
     */
    protected $ShortcutDimCode_x005B_8_x005D_ = null;

    /**
     * @var date $Shipment_Date
     */
    protected $Shipment_Date = null;

    /**
     * @var float $Outstanding_Quantity
     */
    protected $Outstanding_Quantity = null;

    /**
     * @var boolean $Send_for_approval
     */
    protected $Send_for_approval = null;

    /**
     * @var boolean $Reopen
     */
    protected $Reopen = null;

    /**
     * @var boolean $Open_Item_Tracking_Lines
     */
    protected $Open_Item_Tracking_Lines = null;

    
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
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines
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
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines
     */
    public function setDocument_No($Document_No)
    {
      $this->Document_No = $Document_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getSell_to_Customer_No()
    {
      return $this->Sell_to_Customer_No;
    }

    /**
     * @param string $Sell_to_Customer_No
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines
     */
    public function setSell_to_Customer_No($Sell_to_Customer_No)
    {
      $this->Sell_to_Customer_No = $Sell_to_Customer_No;
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
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines
     */
    public function setLine_No($Line_No)
    {
      $this->Line_No = $Line_No;
      return $this;
    }

    /**
     * @return Type
     */
    public function getType()
    {
      return $this->Type;
    }

    /**
     * @param Type $Type
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines
     */
    public function setType($Type)
    {
      $this->Type = $Type;
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
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines
     */
    public function setNo($No)
    {
      $this->No = $No;
      return $this;
    }

    /**
     * @return string
     */
    public function getVariant_Code()
    {
      return $this->Variant_Code;
    }

    /**
     * @param string $Variant_Code
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines
     */
    public function setVariant_Code($Variant_Code)
    {
      $this->Variant_Code = $Variant_Code;
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
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines
     */
    public function setDescription($Description)
    {
      $this->Description = $Description;
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
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines
     */
    public function setLocation_Code($Location_Code)
    {
      $this->Location_Code = $Location_Code;
      return $this;
    }

    /**
     * @return Reserve
     */
    public function getReserve()
    {
      return $this->Reserve;
    }

    /**
     * @param Reserve $Reserve
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines
     */
    public function setReserve($Reserve)
    {
      $this->Reserve = $Reserve;
      return $this;
    }

    /**
     * @return float
     */
    public function getQuantity()
    {
      return $this->Quantity;
    }

    /**
     * @param float $Quantity
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines
     */
    public function setQuantity($Quantity)
    {
      $this->Quantity = $Quantity;
      return $this;
    }

    /**
     * @return float
     */
    public function getReserved_Qty_Base()
    {
      return $this->Reserved_Qty_Base;
    }

    /**
     * @param float $Reserved_Qty_Base
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines
     */
    public function setReserved_Qty_Base($Reserved_Qty_Base)
    {
      $this->Reserved_Qty_Base = $Reserved_Qty_Base;
      return $this;
    }

    /**
     * @return string
     */
    public function getUnit_of_Measure_Code()
    {
      return $this->Unit_of_Measure_Code;
    }

    /**
     * @param string $Unit_of_Measure_Code
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines
     */
    public function setUnit_of_Measure_Code($Unit_of_Measure_Code)
    {
      $this->Unit_of_Measure_Code = $Unit_of_Measure_Code;
      return $this;
    }

    /**
     * @return float
     */
    public function getLine_Amount()
    {
      return $this->Line_Amount;
    }

    /**
     * @param float $Line_Amount
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines
     */
    public function setLine_Amount($Line_Amount)
    {
      $this->Line_Amount = $Line_Amount;
      return $this;
    }

    /**
     * @return string
     */
    public function getPO_No()
    {
      return $this->PO_No;
    }

    /**
     * @param string $PO_No
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines
     */
    public function setPO_No($PO_No)
    {
      $this->PO_No = $PO_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getShortcut_Dimension_1_Code()
    {
      return $this->Shortcut_Dimension_1_Code;
    }

    /**
     * @param string $Shortcut_Dimension_1_Code
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines
     */
    public function setShortcut_Dimension_1_Code($Shortcut_Dimension_1_Code)
    {
      $this->Shortcut_Dimension_1_Code = $Shortcut_Dimension_1_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getShortcut_Dimension_2_Code()
    {
      return $this->Shortcut_Dimension_2_Code;
    }

    /**
     * @param string $Shortcut_Dimension_2_Code
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines
     */
    public function setShortcut_Dimension_2_Code($Shortcut_Dimension_2_Code)
    {
      $this->Shortcut_Dimension_2_Code = $Shortcut_Dimension_2_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getShortcutDimCode_x005B_3_x005D_()
    {
      return $this->ShortcutDimCode_x005B_3_x005D_;
    }

    /**
     * @param string $ShortcutDimCode_x005B_3_x005D_
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines
     */
    public function setShortcutDimCode_x005B_3_x005D_($ShortcutDimCode_x005B_3_x005D_)
    {
      $this->ShortcutDimCode_x005B_3_x005D_ = $ShortcutDimCode_x005B_3_x005D_;
      return $this;
    }

    /**
     * @return string
     */
    public function getShortcutDimCode_x005B_4_x005D_()
    {
      return $this->ShortcutDimCode_x005B_4_x005D_;
    }

    /**
     * @param string $ShortcutDimCode_x005B_4_x005D_
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines
     */
    public function setShortcutDimCode_x005B_4_x005D_($ShortcutDimCode_x005B_4_x005D_)
    {
      $this->ShortcutDimCode_x005B_4_x005D_ = $ShortcutDimCode_x005B_4_x005D_;
      return $this;
    }

    /**
     * @return string
     */
    public function getShortcutDimCode_x005B_5_x005D_()
    {
      return $this->ShortcutDimCode_x005B_5_x005D_;
    }

    /**
     * @param string $ShortcutDimCode_x005B_5_x005D_
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines
     */
    public function setShortcutDimCode_x005B_5_x005D_($ShortcutDimCode_x005B_5_x005D_)
    {
      $this->ShortcutDimCode_x005B_5_x005D_ = $ShortcutDimCode_x005B_5_x005D_;
      return $this;
    }

    /**
     * @return string
     */
    public function getShortcutDimCode_x005B_6_x005D_()
    {
      return $this->ShortcutDimCode_x005B_6_x005D_;
    }

    /**
     * @param string $ShortcutDimCode_x005B_6_x005D_
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines
     */
    public function setShortcutDimCode_x005B_6_x005D_($ShortcutDimCode_x005B_6_x005D_)
    {
      $this->ShortcutDimCode_x005B_6_x005D_ = $ShortcutDimCode_x005B_6_x005D_;
      return $this;
    }

    /**
     * @return string
     */
    public function getShortcutDimCode_x005B_7_x005D_()
    {
      return $this->ShortcutDimCode_x005B_7_x005D_;
    }

    /**
     * @param string $ShortcutDimCode_x005B_7_x005D_
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines
     */
    public function setShortcutDimCode_x005B_7_x005D_($ShortcutDimCode_x005B_7_x005D_)
    {
      $this->ShortcutDimCode_x005B_7_x005D_ = $ShortcutDimCode_x005B_7_x005D_;
      return $this;
    }

    /**
     * @return string
     */
    public function getShortcutDimCode_x005B_8_x005D_()
    {
      return $this->ShortcutDimCode_x005B_8_x005D_;
    }

    /**
     * @param string $ShortcutDimCode_x005B_8_x005D_
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines
     */
    public function setShortcutDimCode_x005B_8_x005D_($ShortcutDimCode_x005B_8_x005D_)
    {
      $this->ShortcutDimCode_x005B_8_x005D_ = $ShortcutDimCode_x005B_8_x005D_;
      return $this;
    }

    /**
     * @return date
     */
    public function getShipment_Date()
    {
      return $this->Shipment_Date;
    }

    /**
     * @param date $Shipment_Date
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines
     */
    public function setShipment_Date($Shipment_Date)
    {
      $this->Shipment_Date = $Shipment_Date;
      return $this;
    }

    /**
     * @return float
     */
    public function getOutstanding_Quantity()
    {
      return $this->Outstanding_Quantity;
    }

    /**
     * @param float $Outstanding_Quantity
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines
     */
    public function setOutstanding_Quantity($Outstanding_Quantity)
    {
      $this->Outstanding_Quantity = $Outstanding_Quantity;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getSend_for_approval()
    {
      return $this->Send_for_approval;
    }

    /**
     * @param boolean $Send_for_approval
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines
     */
    public function setSend_for_approval($Send_for_approval)
    {
      $this->Send_for_approval = $Send_for_approval;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getReopen()
    {
      return $this->Reopen;
    }

    /**
     * @param boolean $Reopen
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines
     */
    public function setReopen($Reopen)
    {
      $this->Reopen = $Reopen;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getOpen_Item_Tracking_Lines()
    {
      return $this->Open_Item_Tracking_Lines;
    }

    /**
     * @param boolean $Open_Item_Tracking_Lines
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines
     */
    public function setOpen_Item_Tracking_Lines($Open_Item_Tracking_Lines)
    {
      $this->Open_Item_Tracking_Lines = $Open_Item_Tracking_Lines;
      return $this;
    }

}
