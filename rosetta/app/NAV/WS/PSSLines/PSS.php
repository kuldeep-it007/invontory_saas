<?php

namespace Rosetta\NAV\WS\PSSLines;

class PSS
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
     * @var string $Shortcut_Dimension_1_Code
     */
    protected $Shortcut_Dimension_1_Code = null;

    /**
     * @var string $Shortcut_Dimension_2_Code
     */
    protected $Shortcut_Dimension_2_Code = null;

    /**
     * @var string $Location_Code
     */
    protected $Location_Code = null;

    /**
     * @var float $Quantity
     */
    protected $Quantity = null;

    /**
     * @var string $Unit_of_Measure_Code
     */
    protected $Unit_of_Measure_Code = null;

    /**
     * @var string $Unit_of_Measure
     */
    protected $Unit_of_Measure = null;

    /**
     * @var int $Appl_to_Item_Entry
     */
    protected $Appl_to_Item_Entry = null;

    /**
     * @var string $Job_No
     */
    protected $Job_No = null;

    /**
     * @var string $Lot_Numbers
     */
    protected $Lot_Numbers = null;

    /**
     * @var string $Multiple_Quantities
     */
    protected $Multiple_Quantities = null;

    /**
     * @var date $Shipment_Date
     */
    protected $Shipment_Date = null;

    /**
     * @var string $Order_No
     */
    protected $Order_No = null;

    /**
     * @var int $Order_Line_No
     */
    protected $Order_Line_No = null;

    /**
     * @var string $Lot_No
     */
    protected $Lot_No = null;

    /**
     * @var float $Quantity_Invoiced
     */
    protected $Quantity_Invoiced = null;

    
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
     * @return \Rosetta\NAV\WS\PSSLines\PSS
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
     * @return \Rosetta\NAV\WS\PSSLines\PSS
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
     * @return \Rosetta\NAV\WS\PSSLines\PSS
     */
    public function setSell_to_Customer_No($Sell_to_Customer_No)
    {
      $this->Sell_to_Customer_No = $Sell_to_Customer_No;
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
     * @return \Rosetta\NAV\WS\PSSLines\PSS
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
     * @return \Rosetta\NAV\WS\PSSLines\PSS
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
     * @return \Rosetta\NAV\WS\PSSLines\PSS
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
     * @return \Rosetta\NAV\WS\PSSLines\PSS
     */
    public function setDescription($Description)
    {
      $this->Description = $Description;
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
     * @return \Rosetta\NAV\WS\PSSLines\PSS
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
     * @return \Rosetta\NAV\WS\PSSLines\PSS
     */
    public function setShortcut_Dimension_2_Code($Shortcut_Dimension_2_Code)
    {
      $this->Shortcut_Dimension_2_Code = $Shortcut_Dimension_2_Code;
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
     * @return \Rosetta\NAV\WS\PSSLines\PSS
     */
    public function setLocation_Code($Location_Code)
    {
      $this->Location_Code = $Location_Code;
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
     * @return \Rosetta\NAV\WS\PSSLines\PSS
     */
    public function setQuantity($Quantity)
    {
      $this->Quantity = $Quantity;
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
     * @return \Rosetta\NAV\WS\PSSLines\PSS
     */
    public function setUnit_of_Measure_Code($Unit_of_Measure_Code)
    {
      $this->Unit_of_Measure_Code = $Unit_of_Measure_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getUnit_of_Measure()
    {
      return $this->Unit_of_Measure;
    }

    /**
     * @param string $Unit_of_Measure
     * @return \Rosetta\NAV\WS\PSSLines\PSS
     */
    public function setUnit_of_Measure($Unit_of_Measure)
    {
      $this->Unit_of_Measure = $Unit_of_Measure;
      return $this;
    }

    /**
     * @return int
     */
    public function getAppl_to_Item_Entry()
    {
      return $this->Appl_to_Item_Entry;
    }

    /**
     * @param int $Appl_to_Item_Entry
     * @return \Rosetta\NAV\WS\PSSLines\PSS
     */
    public function setAppl_to_Item_Entry($Appl_to_Item_Entry)
    {
      $this->Appl_to_Item_Entry = $Appl_to_Item_Entry;
      return $this;
    }

    /**
     * @return string
     */
    public function getJob_No()
    {
      return $this->Job_No;
    }

    /**
     * @param string $Job_No
     * @return \Rosetta\NAV\WS\PSSLines\PSS
     */
    public function setJob_No($Job_No)
    {
      $this->Job_No = $Job_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getLot_Numbers()
    {
      return $this->Lot_Numbers;
    }

    /**
     * @param string $Lot_Numbers
     * @return \Rosetta\NAV\WS\PSSLines\PSS
     */
    public function setLot_Numbers($Lot_Numbers)
    {
      $this->Lot_Numbers = $Lot_Numbers;
      return $this;
    }

    /**
     * @return string
     */
    public function getMultiple_Quantities()
    {
      return $this->Multiple_Quantities;
    }

    /**
     * @param string $Multiple_Quantities
     * @return \Rosetta\NAV\WS\PSSLines\PSS
     */
    public function setMultiple_Quantities($Multiple_Quantities)
    {
      $this->Multiple_Quantities = $Multiple_Quantities;
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
     * @return \Rosetta\NAV\WS\PSSLines\PSS
     */
    public function setShipment_Date($Shipment_Date)
    {
      $this->Shipment_Date = $Shipment_Date;
      return $this;
    }

    /**
     * @return string
     */
    public function getOrder_No()
    {
      return $this->Order_No;
    }

    /**
     * @param string $Order_No
     * @return \Rosetta\NAV\WS\PSSLines\PSS
     */
    public function setOrder_No($Order_No)
    {
      $this->Order_No = $Order_No;
      return $this;
    }

    /**
     * @return int
     */
    public function getOrder_Line_No()
    {
      return $this->Order_Line_No;
    }

    /**
     * @param int $Order_Line_No
     * @return \Rosetta\NAV\WS\PSSLines\PSS
     */
    public function setOrder_Line_No($Order_Line_No)
    {
      $this->Order_Line_No = $Order_Line_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getLot_No()
    {
      return $this->Lot_No;
    }

    /**
     * @param string $Lot_No
     * @return \Rosetta\NAV\WS\PSSLines\PSS
     */
    public function setLot_No($Lot_No)
    {
      $this->Lot_No = $Lot_No;
      return $this;
    }

    /**
     * @return float
     */
    public function getQuantity_Invoiced()
    {
      return $this->Quantity_Invoiced;
    }

    /**
     * @param float $Quantity_Invoiced
     * @return \Rosetta\NAV\WS\PSSLines\PSS
     */
    public function setQuantity_Invoiced($Quantity_Invoiced)
    {
      $this->Quantity_Invoiced = $Quantity_Invoiced;
      return $this;
    }

}
