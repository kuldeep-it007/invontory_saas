<?php

namespace Rosetta\NAV\WS\ILE;

class ILE
{

    /**
     * @var string $Key
     */
    protected $Key = null;

    /**
     * @var date $Posting_Date
     */
    protected $Posting_Date = null;

    /**
     * @var Entry_Type $Entry_Type
     */
    protected $Entry_Type = null;

    /**
     * @var Document_Type $Document_Type
     */
    protected $Document_Type = null;

    /**
     * @var string $Document_No
     */
    protected $Document_No = null;

    /**
     * @var string $Description3
     */
    protected $Description3 = null;

    /**
     * @var string $Name
     */
    protected $Name = null;

    /**
     * @var int $Document_Line_No
     */
    protected $Document_Line_No = null;

    /**
     * @var string $Item_No
     */
    protected $Item_No = null;

    /**
     * @var string $Variant_Code
     */
    protected $Variant_Code = null;

    /**
     * @var string $Description
     */
    protected $Description = null;

    /**
     * @var string $Reason_Code
     */
    protected $Reason_Code = null;

    /**
     * @var string $Return_Reason_Code
     */
    protected $Return_Reason_Code = null;

    /**
     * @var string $Global_Dimension_1_Code
     */
    protected $Global_Dimension_1_Code = null;

    /**
     * @var string $Global_Dimension_2_Code
     */
    protected $Global_Dimension_2_Code = null;

    /**
     * @var string $Department_Name
     */
    protected $Department_Name = null;

    /**
     * @var date $Expiration_Date
     */
    protected $Expiration_Date = null;

    /**
     * @var string $Serial_No
     */
    protected $Serial_No = null;

    /**
     * @var string $Lot_No
     */
    protected $Lot_No = null;

    /**
     * @var string $Location_Code
     */
    protected $Location_Code = null;

    /**
     * @var float $Quantity
     */
    protected $Quantity = null;

    /**
     * @var float $No_of_Box
     */
    protected $No_of_Box = null;

    /**
     * @var float $No_of_Pallet
     */
    protected $No_of_Pallet = null;

    /**
     * @var float $No_of_Roll
     */
    protected $No_of_Roll = null;

    /**
     * @var float $Invoiced_Quantity
     */
    protected $Invoiced_Quantity = null;

    /**
     * @var float $Remaining_Quantity
     */
    protected $Remaining_Quantity = null;

    /**
     * @var float $Shipped_Qty_Not_Returned
     */
    protected $Shipped_Qty_Not_Returned = null;

    /**
     * @var float $Reserved_Quantity
     */
    protected $Reserved_Quantity = null;

    /**
     * @var float $Qty_per_Unit_of_Measure
     */
    protected $Qty_per_Unit_of_Measure = null;

    /**
     * @var float $Sales_Amount_Expected
     */
    protected $Sales_Amount_Expected = null;

    /**
     * @var float $Sales_Amount_Actual
     */
    protected $Sales_Amount_Actual = null;

    /**
     * @var float $Cost_Amount_Expected
     */
    protected $Cost_Amount_Expected = null;

    /**
     * @var float $Cost_Amount_Actual
     */
    protected $Cost_Amount_Actual = null;

    /**
     * @var float $Cost_Amount_Non_Invtbl
     */
    protected $Cost_Amount_Non_Invtbl = null;

    /**
     * @var string $Product_Group_Code
     */
    protected $Product_Group_Code = null;

    /**
     * @var float $Cost_Amount_Expected_ACY
     */
    protected $Cost_Amount_Expected_ACY = null;

    /**
     * @var float $Cost_Amount_Actual_ACY
     */
    protected $Cost_Amount_Actual_ACY = null;

    /**
     * @var float $Cost_Amount_Non_Invtbl_ACY
     */
    protected $Cost_Amount_Non_Invtbl_ACY = null;

    /**
     * @var boolean $Completely_Invoiced
     */
    protected $Completely_Invoiced = null;

    /**
     * @var boolean $Open
     */
    protected $Open = null;

    /**
     * @var boolean $Drop_Shipment
     */
    protected $Drop_Shipment = null;

    /**
     * @var boolean $Assemble_to_Order
     */
    protected $Assemble_to_Order = null;

    /**
     * @var boolean $Applied_Entry_to_Adjust
     */
    protected $Applied_Entry_to_Adjust = null;

    /**
     * @var Order_Type $Order_Type
     */
    protected $Order_Type = null;

    /**
     * @var string $Order_No
     */
    protected $Order_No = null;

    /**
     * @var int $Order_Line_No
     */
    protected $Order_Line_No = null;

    /**
     * @var int $Prod_Order_Comp_Line_No
     */
    protected $Prod_Order_Comp_Line_No = null;

    /**
     * @var int $Entry_No
     */
    protected $Entry_No = null;

    /**
     * @var string $Job_No
     */
    protected $Job_No = null;

    /**
     * @var string $Job_Task_No
     */
    protected $Job_Task_No = null;

    /**
     * @var Other_Usage $Other_Usage
     */
    protected $Other_Usage = null;

    /**
     * @var string $Nature_of_Disposal
     */
    protected $Nature_of_Disposal = null;

    /**
     * @var Type_of_Disposal $Type_of_Disposal
     */
    protected $Type_of_Disposal = null;

    /**
     * @var boolean $Laboratory_Test
     */
    protected $Laboratory_Test = null;

    /**
     * @var string $_x003C_Collection_x003E_
     */
    protected $_x003C_Collection_x003E_ = null;

    /**
     * @var Source_Type $Source_Type
     */
    protected $Source_Type = null;

    /**
     * @var float $Reserved_Qty
     */
    protected $Reserved_Qty = null;

    /**
     * @var string $Source_No
     */
    protected $Source_No = null;
    protected $Location_Source = null;
    protected $Location_Name = null;
    protected $CoLocation = null;
    protected $Location_SourceId = null;    

    
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
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setKey($Key)
    {
      $this->Key = $Key;
      return $this;
    }

    /**
     * @return date
     */
    public function getPosting_Date()
    {
      return $this->Posting_Date;
    }

    /**
     * @param date $Posting_Date
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setPosting_Date($Posting_Date)
    {
      $this->Posting_Date = $Posting_Date;
      return $this;
    }

    /**
     * @return Entry_Type
     */
    public function getEntry_Type()
    {
      return $this->Entry_Type;
    }

    /**
     * @param Entry_Type $Entry_Type
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setEntry_Type($Entry_Type)
    {
      $this->Entry_Type = $Entry_Type;
      return $this;
    }

    /**
     * @return Document_Type
     */
    public function getDocument_Type()
    {
      return $this->Document_Type;
    }

    /**
     * @param Document_Type $Document_Type
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setDocument_Type($Document_Type)
    {
      $this->Document_Type = $Document_Type;
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
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setDocument_No($Document_No)
    {
      $this->Document_No = $Document_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getDescription3()
    {
      return $this->Description3;
    }

    /**
     * @param string $Description3
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setDescription3($Description3)
    {
      $this->Description3 = $Description3;
      return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
      return $this->Name;
    }

    /**
     * @param string $Name
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setName($Name)
    {
      $this->Name = $Name;
      return $this;
    }

    /**
     * @return int
     */
    public function getDocument_Line_No()
    {
      return $this->Document_Line_No;
    }

    /**
     * @param int $Document_Line_No
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setDocument_Line_No($Document_Line_No)
    {
      $this->Document_Line_No = $Document_Line_No;
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
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setItem_No($Item_No)
    {
      $this->Item_No = $Item_No;
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
     * @return \Rosetta\NAV\WS\ILE\ILE
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
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setDescription($Description)
    {
      $this->Description = $Description;
      return $this;
    }

    /**
     * @return string
     */
    public function getReason_Code()
    {
      return $this->Reason_Code;
    }

    /**
     * @param string $Reason_Code
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setReason_Code($Reason_Code)
    {
      $this->Reason_Code = $Reason_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getReturn_Reason_Code()
    {
      return $this->Return_Reason_Code;
    }

    /**
     * @param string $Return_Reason_Code
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setReturn_Reason_Code($Return_Reason_Code)
    {
      $this->Return_Reason_Code = $Return_Reason_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getGlobal_Dimension_1_Code()
    {
      return $this->Global_Dimension_1_Code;
    }

    /**
     * @param string $Global_Dimension_1_Code
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setGlobal_Dimension_1_Code($Global_Dimension_1_Code)
    {
      $this->Global_Dimension_1_Code = $Global_Dimension_1_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getGlobal_Dimension_2_Code()
    {
      return $this->Global_Dimension_2_Code;
    }

    /**
     * @param string $Global_Dimension_2_Code
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setGlobal_Dimension_2_Code($Global_Dimension_2_Code)
    {
      $this->Global_Dimension_2_Code = $Global_Dimension_2_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getDepartment_Name()
    {
      return $this->Department_Name;
    }

    /**
     * @param string $Department_Name
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setDepartment_Name($Department_Name)
    {
      $this->Department_Name = $Department_Name;
      return $this;
    }

    /**
     * @return date
     */
    public function getExpiration_Date()
    {
      return $this->Expiration_Date;
    }

    /**
     * @param date $Expiration_Date
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setExpiration_Date($Expiration_Date)
    {
      $this->Expiration_Date = $Expiration_Date;
      return $this;
    }

    /**
     * @return string
     */
    public function getSerial_No()
    {
      return $this->Serial_No;
    }

    /**
     * @param string $Serial_No
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setSerial_No($Serial_No)
    {
      $this->Serial_No = $Serial_No;
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
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setLot_No($Lot_No)
    {
      $this->Lot_No = $Lot_No;
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
     * @return \Rosetta\NAV\WS\ILE\ILE
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
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setQuantity($Quantity)
    {
      $this->Quantity = $Quantity;
      return $this;
    }

    /**
     * @return float
     */
    public function getNo_of_Box()
    {
      return $this->No_of_Box;
    }

    /**
     * @param float $No_of_Box
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setNo_of_Box($No_of_Box)
    {
      $this->No_of_Box = $No_of_Box;
      return $this;
    }

    /**
     * @return float
     */
    public function getNo_of_Pallet()
    {
      return $this->No_of_Pallet;
    }

    /**
     * @param float $No_of_Pallet
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setNo_of_Pallet($No_of_Pallet)
    {
      $this->No_of_Pallet = $No_of_Pallet;
      return $this;
    }

    /**
     * @return float
     */
    public function getNo_of_Roll()
    {
      return $this->No_of_Roll;
    }

    /**
     * @param float $No_of_Roll
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setNo_of_Roll($No_of_Roll)
    {
      $this->No_of_Roll = $No_of_Roll;
      return $this;
    }

    /**
     * @return float
     */
    public function getInvoiced_Quantity()
    {
      return $this->Invoiced_Quantity;
    }

    /**
     * @param float $Invoiced_Quantity
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setInvoiced_Quantity($Invoiced_Quantity)
    {
      $this->Invoiced_Quantity = $Invoiced_Quantity;
      return $this;
    }

    /**
     * @return float
     */
    public function getRemaining_Quantity()
    {
      return $this->Remaining_Quantity;
    }

    /**
     * @param float $Remaining_Quantity
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setRemaining_Quantity($Remaining_Quantity)
    {
      $this->Remaining_Quantity = $Remaining_Quantity;
      return $this;
    }

    /**
     * @return float
     */
    public function getShipped_Qty_Not_Returned()
    {
      return $this->Shipped_Qty_Not_Returned;
    }

    /**
     * @param float $Shipped_Qty_Not_Returned
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setShipped_Qty_Not_Returned($Shipped_Qty_Not_Returned)
    {
      $this->Shipped_Qty_Not_Returned = $Shipped_Qty_Not_Returned;
      return $this;
    }

    /**
     * @return float
     */
    public function getReserved_Quantity()
    {
      return $this->Reserved_Quantity;
    }

    /**
     * @param float $Reserved_Quantity
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setReserved_Quantity($Reserved_Quantity)
    {
      $this->Reserved_Quantity = $Reserved_Quantity;
      return $this;
    }

    /**
     * @return float
     */
    public function getQty_per_Unit_of_Measure()
    {
      return $this->Qty_per_Unit_of_Measure;
    }

    /**
     * @param float $Qty_per_Unit_of_Measure
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setQty_per_Unit_of_Measure($Qty_per_Unit_of_Measure)
    {
      $this->Qty_per_Unit_of_Measure = $Qty_per_Unit_of_Measure;
      return $this;
    }

    /**
     * @return float
     */
    public function getSales_Amount_Expected()
    {
      return $this->Sales_Amount_Expected;
    }

    /**
     * @param float $Sales_Amount_Expected
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setSales_Amount_Expected($Sales_Amount_Expected)
    {
      $this->Sales_Amount_Expected = $Sales_Amount_Expected;
      return $this;
    }

    /**
     * @return float
     */
    public function getSales_Amount_Actual()
    {
      return $this->Sales_Amount_Actual;
    }

    /**
     * @param float $Sales_Amount_Actual
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setSales_Amount_Actual($Sales_Amount_Actual)
    {
      $this->Sales_Amount_Actual = $Sales_Amount_Actual;
      return $this;
    }

    /**
     * @return float
     */
    public function getCost_Amount_Expected()
    {
      return $this->Cost_Amount_Expected;
    }

    /**
     * @param float $Cost_Amount_Expected
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setCost_Amount_Expected($Cost_Amount_Expected)
    {
      $this->Cost_Amount_Expected = $Cost_Amount_Expected;
      return $this;
    }

    /**
     * @return float
     */
    public function getCost_Amount_Actual()
    {
      return $this->Cost_Amount_Actual;
    }

    /**
     * @param float $Cost_Amount_Actual
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setCost_Amount_Actual($Cost_Amount_Actual)
    {
      $this->Cost_Amount_Actual = $Cost_Amount_Actual;
      return $this;
    }

    /**
     * @return float
     */
    public function getCost_Amount_Non_Invtbl()
    {
      return $this->Cost_Amount_Non_Invtbl;
    }

    /**
     * @param float $Cost_Amount_Non_Invtbl
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setCost_Amount_Non_Invtbl($Cost_Amount_Non_Invtbl)
    {
      $this->Cost_Amount_Non_Invtbl = $Cost_Amount_Non_Invtbl;
      return $this;
    }

    /**
     * @return string
     */
    public function getProduct_Group_Code()
    {
      return $this->Product_Group_Code;
    }

    /**
     * @param string $Product_Group_Code
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setProduct_Group_Code($Product_Group_Code)
    {
      $this->Product_Group_Code = $Product_Group_Code;
      return $this;
    }

    /**
     * @return float
     */
    public function getCost_Amount_Expected_ACY()
    {
      return $this->Cost_Amount_Expected_ACY;
    }

    /**
     * @param float $Cost_Amount_Expected_ACY
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setCost_Amount_Expected_ACY($Cost_Amount_Expected_ACY)
    {
      $this->Cost_Amount_Expected_ACY = $Cost_Amount_Expected_ACY;
      return $this;
    }

    /**
     * @return float
     */
    public function getCost_Amount_Actual_ACY()
    {
      return $this->Cost_Amount_Actual_ACY;
    }

    /**
     * @param float $Cost_Amount_Actual_ACY
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setCost_Amount_Actual_ACY($Cost_Amount_Actual_ACY)
    {
      $this->Cost_Amount_Actual_ACY = $Cost_Amount_Actual_ACY;
      return $this;
    }

    /**
     * @return float
     */
    public function getCost_Amount_Non_Invtbl_ACY()
    {
      return $this->Cost_Amount_Non_Invtbl_ACY;
    }

    /**
     * @param float $Cost_Amount_Non_Invtbl_ACY
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setCost_Amount_Non_Invtbl_ACY($Cost_Amount_Non_Invtbl_ACY)
    {
      $this->Cost_Amount_Non_Invtbl_ACY = $Cost_Amount_Non_Invtbl_ACY;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getCompletely_Invoiced()
    {
      return $this->Completely_Invoiced;
    }

    /**
     * @param boolean $Completely_Invoiced
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setCompletely_Invoiced($Completely_Invoiced)
    {
      $this->Completely_Invoiced = $Completely_Invoiced;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getOpen()
    {
      return $this->Open;
    }

    /**
     * @param boolean $Open
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setOpen($Open)
    {
      $this->Open = $Open;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getDrop_Shipment()
    {
      return $this->Drop_Shipment;
    }

    /**
     * @param boolean $Drop_Shipment
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setDrop_Shipment($Drop_Shipment)
    {
      $this->Drop_Shipment = $Drop_Shipment;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getAssemble_to_Order()
    {
      return $this->Assemble_to_Order;
    }

    /**
     * @param boolean $Assemble_to_Order
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setAssemble_to_Order($Assemble_to_Order)
    {
      $this->Assemble_to_Order = $Assemble_to_Order;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getApplied_Entry_to_Adjust()
    {
      return $this->Applied_Entry_to_Adjust;
    }

    /**
     * @param boolean $Applied_Entry_to_Adjust
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setApplied_Entry_to_Adjust($Applied_Entry_to_Adjust)
    {
      $this->Applied_Entry_to_Adjust = $Applied_Entry_to_Adjust;
      return $this;
    }

    /**
     * @return Order_Type
     */
    public function getOrder_Type()
    {
      return $this->Order_Type;
    }

    /**
     * @param Order_Type $Order_Type
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setOrder_Type($Order_Type)
    {
      $this->Order_Type = $Order_Type;
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
     * @return \Rosetta\NAV\WS\ILE\ILE
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
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setOrder_Line_No($Order_Line_No)
    {
      $this->Order_Line_No = $Order_Line_No;
      return $this;
    }

    /**
     * @return int
     */
    public function getProd_Order_Comp_Line_No()
    {
      return $this->Prod_Order_Comp_Line_No;
    }

    /**
     * @param int $Prod_Order_Comp_Line_No
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setProd_Order_Comp_Line_No($Prod_Order_Comp_Line_No)
    {
      $this->Prod_Order_Comp_Line_No = $Prod_Order_Comp_Line_No;
      return $this;
    }

    /**
     * @return int
     */
    public function getEntry_No()
    {
      return $this->Entry_No;
    }

    /**
     * @param int $Entry_No
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setEntry_No($Entry_No)
    {
      $this->Entry_No = $Entry_No;
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
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setJob_No($Job_No)
    {
      $this->Job_No = $Job_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getJob_Task_No()
    {
      return $this->Job_Task_No;
    }

    /**
     * @param string $Job_Task_No
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setJob_Task_No($Job_Task_No)
    {
      $this->Job_Task_No = $Job_Task_No;
      return $this;
    }

    /**
     * @return Other_Usage
     */
    public function getOther_Usage()
    {
      return $this->Other_Usage;
    }

    /**
     * @param Other_Usage $Other_Usage
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setOther_Usage($Other_Usage)
    {
      $this->Other_Usage = $Other_Usage;
      return $this;
    }

    /**
     * @return string
     */
    public function getNature_of_Disposal()
    {
      return $this->Nature_of_Disposal;
    }

    /**
     * @param string $Nature_of_Disposal
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setNature_of_Disposal($Nature_of_Disposal)
    {
      $this->Nature_of_Disposal = $Nature_of_Disposal;
      return $this;
    }

    /**
     * @return Type_of_Disposal
     */
    public function getType_of_Disposal()
    {
      return $this->Type_of_Disposal;
    }

    /**
     * @param Type_of_Disposal $Type_of_Disposal
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setType_of_Disposal($Type_of_Disposal)
    {
      $this->Type_of_Disposal = $Type_of_Disposal;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getLaboratory_Test()
    {
      return $this->Laboratory_Test;
    }

    /**
     * @param boolean $Laboratory_Test
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setLaboratory_Test($Laboratory_Test)
    {
      $this->Laboratory_Test = $Laboratory_Test;
      return $this;
    }

    /**
     * @return string
     */
    public function get_x003C_Collection_x003E_()
    {
      return $this->_x003C_Collection_x003E_;
    }

    /**
     * @param string $_x003C_Collection_x003E_
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function set_x003C_Collection_x003E_($_x003C_Collection_x003E_)
    {
      $this->_x003C_Collection_x003E_ = $_x003C_Collection_x003E_;
      return $this;
    }

    /**
     * @return Source_Type
     */
    public function getSource_Type()
    {
      return $this->Source_Type;
    }

    /**
     * @param Source_Type $Source_Type
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setSource_Type($Source_Type)
    {
      $this->Source_Type = $Source_Type;
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
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setReserved_Qty($Reserved_Qty)
    {
      $this->Reserved_Qty = $Reserved_Qty;
      return $this;
    }

    /**
     * @return string
     */
    public function getSource_No()
    {
      return $this->Source_No;
    }

    /**
     * @param string $Source_No
     * @return \Rosetta\NAV\WS\ILE\ILE
     */
    public function setSource_No($Source_No)
    {
      $this->Source_No = $Source_No;
      return $this;
    }
    public function setLocation_Source($source)
    {
        $this->Location_Source = $source; 
    }
    public function getLocation_Source()
    {
        return $this->Location_Source;
    }
    public function getLocation_Name()
    {
        return $this->Location_Name;
    }
    public function setLocation_Name($name)
    {
        $this->Location_Name = $name;
    }

    public function getCoLocation()
    {
        return $this->CoLocation;
    }
    public function setCoLocation($coLocation)
    {
        $this->CoLocation = $coLocation;
    }

    public function setLocation_SourceId($source)
    {
        $this->Location_SourceId = $source; 
    }
    public function getLocation_SourceId()
    {
        return $this->Location_SourceId;
    }
}
