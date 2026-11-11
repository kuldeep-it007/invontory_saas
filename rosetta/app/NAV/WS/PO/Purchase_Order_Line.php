<?php

namespace Rosetta\NAV\WS\PO;

class Purchase_Order_Line
{

    /**
     * @var string $Key
     */
    protected $Key = null;

    /**
     * @var Type $Type
     */
    protected $Type = null;

    /**
     * @var string $No
     */
    protected $No = null;

    /**
     * @var string $Cross_Reference_No
     */
    protected $Cross_Reference_No = null;

    /**
     * @var string $IC_Partner_Code
     */
    protected $IC_Partner_Code = null;

    /**
     * @var IC_Partner_Ref_Type $IC_Partner_Ref_Type
     */
    protected $IC_Partner_Ref_Type = null;

    /**
     * @var CWIP_G_L_Type $CWIP_G_L_Type
     */
    protected $CWIP_G_L_Type = null;

    /**
     * @var string $IC_Partner_Reference
     */
    protected $IC_Partner_Reference = null;

    /**
     * @var string $Variant_Code
     */
    protected $Variant_Code = null;

    /**
     * @var boolean $Nonstock
     */
    protected $Nonstock = null;

    /**
     * @var string $Service_Tax_Registration_No
     */
    protected $Service_Tax_Registration_No = null;

    /**
     * @var string $Service_Tax_Group
     */
    protected $Service_Tax_Group = null;

    /**
     * @var string $VAT_Prod_Posting_Group
     */
    protected $VAT_Prod_Posting_Group = null;

    /**
     * @var string $Description
     */
    protected $Description = null;

    /**
     * @var boolean $Drop_Shipment
     */
    protected $Drop_Shipment = null;

    /**
     * @var string $Return_Reason_Code
     */
    protected $Return_Reason_Code = null;

    /**
     * @var string $Location_Code
     */
    protected $Location_Code = null;

    /**
     * @var string $Bin_Code
     */
    protected $Bin_Code = null;

    /**
     * @var float $Quantity
     */
    protected $Quantity = null;

    /**
     * @var float $Reserved_Quantity
     */
    protected $Reserved_Quantity = null;

    /**
     * @var float $Job_Remaining_Qty
     */
    protected $Job_Remaining_Qty = null;

    /**
     * @var string $Unit_of_Measure_Code
     */
    protected $Unit_of_Measure_Code = null;

    /**
     * @var string $Unit_of_Measure
     */
    protected $Unit_of_Measure = null;

    /**
     * @var float $Direct_Unit_Cost
     */
    protected $Direct_Unit_Cost = null;

    /**
     * @var float $Indirect_Cost_Percent
     */
    protected $Indirect_Cost_Percent = null;

    /**
     * @var float $Unit_Cost_LCY
     */
    protected $Unit_Cost_LCY = null;

    /**
     * @var float $Unit_Price_LCY
     */
    protected $Unit_Price_LCY = null;

    /**
     * @var float $Line_Amount
     */
    protected $Line_Amount = null;

    /**
     * @var float $Line_Discount_Percent
     */
    protected $Line_Discount_Percent = null;

    /**
     * @var float $Line_Discount_Amount
     */
    protected $Line_Discount_Amount = null;

    /**
     * @var float $Prepayment_Percent
     */
    protected $Prepayment_Percent = null;

    /**
     * @var float $Prepmt_Line_Amount
     */
    protected $Prepmt_Line_Amount = null;

    /**
     * @var float $Prepmt_Amt_Inv
     */
    protected $Prepmt_Amt_Inv = null;

    /**
     * @var boolean $Allow_Invoice_Disc
     */
    protected $Allow_Invoice_Disc = null;

    /**
     * @var float $Inv_Discount_Amount
     */
    protected $Inv_Discount_Amount = null;

    /**
     * @var float $Qty_to_Receive
     */
    protected $Qty_to_Receive = null;

    /**
     * @var float $Quantity_Received
     */
    protected $Quantity_Received = null;

    /**
     * @var float $Qty_to_Invoice
     */
    protected $Qty_to_Invoice = null;

    /**
     * @var float $Quantity_Invoiced
     */
    protected $Quantity_Invoiced = null;

    /**
     * @var float $Prepmt_Amt_to_Deduct
     */
    protected $Prepmt_Amt_to_Deduct = null;

    /**
     * @var float $Prepmt_Amt_Deducted
     */
    protected $Prepmt_Amt_Deducted = null;

    /**
     * @var boolean $Allow_Item_Charge_Assignment
     */
    protected $Allow_Item_Charge_Assignment = null;

    /**
     * @var float $Qty_to_Assign
     */
    protected $Qty_to_Assign = null;

    /**
     * @var float $Qty_Assigned
     */
    protected $Qty_Assigned = null;

    /**
     * @var float $Custom_Duty_Amount
     */
    protected $Custom_Duty_Amount = null;

    /**
     * @var GST_Credit $GST_Credit
     */
    protected $GST_Credit = null;

    /**
     * @var string $GST_Group_Code
     */
    protected $GST_Group_Code = null;

    /**
     * @var GST_Group_Type $GST_Group_Type
     */
    protected $GST_Group_Type = null;

    /**
     * @var string $HSN_SAC_Code
     */
    protected $HSN_SAC_Code = null;

    /**
     * @var boolean $Exempted
     */
    protected $Exempted = null;

    /**
     * @var float $GST_Base_Amount
     */
    protected $GST_Base_Amount = null;

    /**
     * @var float $Total_GST_Amount
     */
    protected $Total_GST_Amount = null;

    /**
     * @var GST_Jurisdiction_Type $GST_Jurisdiction_Type
     */
    protected $GST_Jurisdiction_Type = null;

    /**
     * @var float $BCD_Amount
     */
    protected $BCD_Amount = null;

    /**
     * @var float $CIF_Amount
     */
    protected $CIF_Amount = null;

    /**
     * @var boolean $Supplementary
     */
    protected $Supplementary = null;

    /**
     * @var Source_Document_Type $Source_Document_Type
     */
    protected $Source_Document_Type = null;

    /**
     * @var string $Source_Document_No
     */
    protected $Source_Document_No = null;

    /**
     * @var string $Job_No
     */
    protected $Job_No = null;

    /**
     * @var string $Job_Task_No
     */
    protected $Job_Task_No = null;

    /**
     * @var int $Job_Planning_Line_No
     */
    protected $Job_Planning_Line_No = null;

    /**
     * @var Job_Line_Type $Job_Line_Type
     */
    protected $Job_Line_Type = null;

    /**
     * @var float $Job_Unit_Price
     */
    protected $Job_Unit_Price = null;

    /**
     * @var float $Job_Line_Amount
     */
    protected $Job_Line_Amount = null;

    /**
     * @var float $Job_Line_Discount_Amount
     */
    protected $Job_Line_Discount_Amount = null;

    /**
     * @var float $Job_Line_Discount_Percent
     */
    protected $Job_Line_Discount_Percent = null;

    /**
     * @var float $Job_Total_Price
     */
    protected $Job_Total_Price = null;

    /**
     * @var float $Job_Unit_Price_LCY
     */
    protected $Job_Unit_Price_LCY = null;

    /**
     * @var float $Job_Total_Price_LCY
     */
    protected $Job_Total_Price_LCY = null;

    /**
     * @var float $Job_Line_Amount_LCY
     */
    protected $Job_Line_Amount_LCY = null;

    /**
     * @var float $Job_Line_Disc_Amount_LCY
     */
    protected $Job_Line_Disc_Amount_LCY = null;

    /**
     * @var date $Requested_Receipt_Date
     */
    protected $Requested_Receipt_Date = null;

    /**
     * @var date $Promised_Receipt_Date
     */
    protected $Promised_Receipt_Date = null;

    /**
     * @var date $Planned_Receipt_Date
     */
    protected $Planned_Receipt_Date = null;

    /**
     * @var date $Expected_Receipt_Date
     */
    protected $Expected_Receipt_Date = null;

    /**
     * @var date $Order_Date
     */
    protected $Order_Date = null;

    /**
     * @var string $Lead_Time_Calculation
     */
    protected $Lead_Time_Calculation = null;

    /**
     * @var Planning_Flexibility $Planning_Flexibility
     */
    protected $Planning_Flexibility = null;

    /**
     * @var string $Prod_Order_No
     */
    protected $Prod_Order_No = null;

    /**
     * @var int $Prod_Order_Line_No
     */
    protected $Prod_Order_Line_No = null;

    /**
     * @var string $Operation_No
     */
    protected $Operation_No = null;

    /**
     * @var string $Work_Center_No
     */
    protected $Work_Center_No = null;

    /**
     * @var boolean $Finished
     */
    protected $Finished = null;

    /**
     * @var float $Whse_Outstanding_Qty_Base
     */
    protected $Whse_Outstanding_Qty_Base = null;

    /**
     * @var string $Inbound_Whse_Handling_Time
     */
    protected $Inbound_Whse_Handling_Time = null;

    /**
     * @var string $Blanket_Order_No
     */
    protected $Blanket_Order_No = null;

    /**
     * @var int $Blanket_Order_Line_No
     */
    protected $Blanket_Order_Line_No = null;

    /**
     * @var int $Appl_to_Item_Entry
     */
    protected $Appl_to_Item_Entry = null;

    /**
     * @var string $Deferral_Code
     */
    protected $Deferral_Code = null;

    /**
     * @var string $Reason_Code
     */
    protected $Reason_Code = null;

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
     * @var float $Invoice_Discount_Amount
     */
    protected $Invoice_Discount_Amount = null;

    /**
     * @var float $Invoice_Disc_Pct
     */
    protected $Invoice_Disc_Pct = null;

    /**
     * @var float $Total_Amount_Excl_VAT
     */
    protected $Total_Amount_Excl_VAT = null;

    /**
     * @var float $Total_VAT_Amount
     */
    protected $Total_VAT_Amount = null;

    /**
     * @var float $Total_Amount_Incl_VAT
     */
    protected $Total_Amount_Incl_VAT = null;

    /**
     * @var string $RefreshTotals
     */
    protected $RefreshTotals = null;

    
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
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setKey($Key)
    {
      $this->Key = $Key;
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
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
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
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setNo($No)
    {
      $this->No = $No;
      return $this;
    }

    /**
     * @return string
     */
    public function getCross_Reference_No()
    {
      return $this->Cross_Reference_No;
    }

    /**
     * @param string $Cross_Reference_No
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setCross_Reference_No($Cross_Reference_No)
    {
      $this->Cross_Reference_No = $Cross_Reference_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getIC_Partner_Code()
    {
      return $this->IC_Partner_Code;
    }

    /**
     * @param string $IC_Partner_Code
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setIC_Partner_Code($IC_Partner_Code)
    {
      $this->IC_Partner_Code = $IC_Partner_Code;
      return $this;
    }

    /**
     * @return IC_Partner_Ref_Type
     */
    public function getIC_Partner_Ref_Type()
    {
      return $this->IC_Partner_Ref_Type;
    }

    /**
     * @param IC_Partner_Ref_Type $IC_Partner_Ref_Type
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setIC_Partner_Ref_Type($IC_Partner_Ref_Type)
    {
      $this->IC_Partner_Ref_Type = $IC_Partner_Ref_Type;
      return $this;
    }

    /**
     * @return CWIP_G_L_Type
     */
    public function getCWIP_G_L_Type()
    {
      return $this->CWIP_G_L_Type;
    }

    /**
     * @param CWIP_G_L_Type $CWIP_G_L_Type
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setCWIP_G_L_Type($CWIP_G_L_Type)
    {
      $this->CWIP_G_L_Type = $CWIP_G_L_Type;
      return $this;
    }

    /**
     * @return string
     */
    public function getIC_Partner_Reference()
    {
      return $this->IC_Partner_Reference;
    }

    /**
     * @param string $IC_Partner_Reference
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setIC_Partner_Reference($IC_Partner_Reference)
    {
      $this->IC_Partner_Reference = $IC_Partner_Reference;
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
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setVariant_Code($Variant_Code)
    {
      $this->Variant_Code = $Variant_Code;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getNonstock()
    {
      return $this->Nonstock;
    }

    /**
     * @param boolean $Nonstock
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setNonstock($Nonstock)
    {
      $this->Nonstock = $Nonstock;
      return $this;
    }

    /**
     * @return string
     */
    public function getService_Tax_Registration_No()
    {
      return $this->Service_Tax_Registration_No;
    }

    /**
     * @param string $Service_Tax_Registration_No
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setService_Tax_Registration_No($Service_Tax_Registration_No)
    {
      $this->Service_Tax_Registration_No = $Service_Tax_Registration_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getService_Tax_Group()
    {
      return $this->Service_Tax_Group;
    }

    /**
     * @param string $Service_Tax_Group
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setService_Tax_Group($Service_Tax_Group)
    {
      $this->Service_Tax_Group = $Service_Tax_Group;
      return $this;
    }

    /**
     * @return string
     */
    public function getVAT_Prod_Posting_Group()
    {
      return $this->VAT_Prod_Posting_Group;
    }

    /**
     * @param string $VAT_Prod_Posting_Group
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setVAT_Prod_Posting_Group($VAT_Prod_Posting_Group)
    {
      $this->VAT_Prod_Posting_Group = $VAT_Prod_Posting_Group;
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
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setDescription($Description)
    {
      $this->Description = $Description;
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
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setDrop_Shipment($Drop_Shipment)
    {
      $this->Drop_Shipment = $Drop_Shipment;
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
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setReturn_Reason_Code($Return_Reason_Code)
    {
      $this->Return_Reason_Code = $Return_Reason_Code;
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
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setLocation_Code($Location_Code)
    {
      $this->Location_Code = $Location_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getBin_Code()
    {
      return $this->Bin_Code;
    }

    /**
     * @param string $Bin_Code
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setBin_Code($Bin_Code)
    {
      $this->Bin_Code = $Bin_Code;
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
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setQuantity($Quantity)
    {
      $this->Quantity = $Quantity;
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
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setReserved_Quantity($Reserved_Quantity)
    {
      $this->Reserved_Quantity = $Reserved_Quantity;
      return $this;
    }

    /**
     * @return float
     */
    public function getJob_Remaining_Qty()
    {
      return $this->Job_Remaining_Qty;
    }

    /**
     * @param float $Job_Remaining_Qty
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setJob_Remaining_Qty($Job_Remaining_Qty)
    {
      $this->Job_Remaining_Qty = $Job_Remaining_Qty;
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
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
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
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setUnit_of_Measure($Unit_of_Measure)
    {
      $this->Unit_of_Measure = $Unit_of_Measure;
      return $this;
    }

    /**
     * @return float
     */
    public function getDirect_Unit_Cost()
    {
      return $this->Direct_Unit_Cost;
    }

    /**
     * @param float $Direct_Unit_Cost
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setDirect_Unit_Cost($Direct_Unit_Cost)
    {
      $this->Direct_Unit_Cost = $Direct_Unit_Cost;
      return $this;
    }

    /**
     * @return float
     */
    public function getIndirect_Cost_Percent()
    {
      return $this->Indirect_Cost_Percent;
    }

    /**
     * @param float $Indirect_Cost_Percent
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setIndirect_Cost_Percent($Indirect_Cost_Percent)
    {
      $this->Indirect_Cost_Percent = $Indirect_Cost_Percent;
      return $this;
    }

    /**
     * @return float
     */
    public function getUnit_Cost_LCY()
    {
      return $this->Unit_Cost_LCY;
    }

    /**
     * @param float $Unit_Cost_LCY
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setUnit_Cost_LCY($Unit_Cost_LCY)
    {
      $this->Unit_Cost_LCY = $Unit_Cost_LCY;
      return $this;
    }

    /**
     * @return float
     */
    public function getUnit_Price_LCY()
    {
      return $this->Unit_Price_LCY;
    }

    /**
     * @param float $Unit_Price_LCY
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setUnit_Price_LCY($Unit_Price_LCY)
    {
      $this->Unit_Price_LCY = $Unit_Price_LCY;
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
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setLine_Amount($Line_Amount)
    {
      $this->Line_Amount = $Line_Amount;
      return $this;
    }

    /**
     * @return float
     */
    public function getLine_Discount_Percent()
    {
      return $this->Line_Discount_Percent;
    }

    /**
     * @param float $Line_Discount_Percent
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setLine_Discount_Percent($Line_Discount_Percent)
    {
      $this->Line_Discount_Percent = $Line_Discount_Percent;
      return $this;
    }

    /**
     * @return float
     */
    public function getLine_Discount_Amount()
    {
      return $this->Line_Discount_Amount;
    }

    /**
     * @param float $Line_Discount_Amount
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setLine_Discount_Amount($Line_Discount_Amount)
    {
      $this->Line_Discount_Amount = $Line_Discount_Amount;
      return $this;
    }

    /**
     * @return float
     */
    public function getPrepayment_Percent()
    {
      return $this->Prepayment_Percent;
    }

    /**
     * @param float $Prepayment_Percent
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setPrepayment_Percent($Prepayment_Percent)
    {
      $this->Prepayment_Percent = $Prepayment_Percent;
      return $this;
    }

    /**
     * @return float
     */
    public function getPrepmt_Line_Amount()
    {
      return $this->Prepmt_Line_Amount;
    }

    /**
     * @param float $Prepmt_Line_Amount
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setPrepmt_Line_Amount($Prepmt_Line_Amount)
    {
      $this->Prepmt_Line_Amount = $Prepmt_Line_Amount;
      return $this;
    }

    /**
     * @return float
     */
    public function getPrepmt_Amt_Inv()
    {
      return $this->Prepmt_Amt_Inv;
    }

    /**
     * @param float $Prepmt_Amt_Inv
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setPrepmt_Amt_Inv($Prepmt_Amt_Inv)
    {
      $this->Prepmt_Amt_Inv = $Prepmt_Amt_Inv;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getAllow_Invoice_Disc()
    {
      return $this->Allow_Invoice_Disc;
    }

    /**
     * @param boolean $Allow_Invoice_Disc
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setAllow_Invoice_Disc($Allow_Invoice_Disc)
    {
      $this->Allow_Invoice_Disc = $Allow_Invoice_Disc;
      return $this;
    }

    /**
     * @return float
     */
    public function getInv_Discount_Amount()
    {
      return $this->Inv_Discount_Amount;
    }

    /**
     * @param float $Inv_Discount_Amount
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setInv_Discount_Amount($Inv_Discount_Amount)
    {
      $this->Inv_Discount_Amount = $Inv_Discount_Amount;
      return $this;
    }

    /**
     * @return float
     */
    public function getQty_to_Receive()
    {
      return $this->Qty_to_Receive;
    }

    /**
     * @param float $Qty_to_Receive
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setQty_to_Receive($Qty_to_Receive)
    {
      $this->Qty_to_Receive = $Qty_to_Receive;
      return $this;
    }

    /**
     * @return float
     */
    public function getQuantity_Received()
    {
      return $this->Quantity_Received;
    }

    /**
     * @param float $Quantity_Received
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setQuantity_Received($Quantity_Received)
    {
      $this->Quantity_Received = $Quantity_Received;
      return $this;
    }

    /**
     * @return float
     */
    public function getQty_to_Invoice()
    {
      return $this->Qty_to_Invoice;
    }

    /**
     * @param float $Qty_to_Invoice
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setQty_to_Invoice($Qty_to_Invoice)
    {
      $this->Qty_to_Invoice = $Qty_to_Invoice;
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
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setQuantity_Invoiced($Quantity_Invoiced)
    {
      $this->Quantity_Invoiced = $Quantity_Invoiced;
      return $this;
    }

    /**
     * @return float
     */
    public function getPrepmt_Amt_to_Deduct()
    {
      return $this->Prepmt_Amt_to_Deduct;
    }

    /**
     * @param float $Prepmt_Amt_to_Deduct
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setPrepmt_Amt_to_Deduct($Prepmt_Amt_to_Deduct)
    {
      $this->Prepmt_Amt_to_Deduct = $Prepmt_Amt_to_Deduct;
      return $this;
    }

    /**
     * @return float
     */
    public function getPrepmt_Amt_Deducted()
    {
      return $this->Prepmt_Amt_Deducted;
    }

    /**
     * @param float $Prepmt_Amt_Deducted
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setPrepmt_Amt_Deducted($Prepmt_Amt_Deducted)
    {
      $this->Prepmt_Amt_Deducted = $Prepmt_Amt_Deducted;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getAllow_Item_Charge_Assignment()
    {
      return $this->Allow_Item_Charge_Assignment;
    }

    /**
     * @param boolean $Allow_Item_Charge_Assignment
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setAllow_Item_Charge_Assignment($Allow_Item_Charge_Assignment)
    {
      $this->Allow_Item_Charge_Assignment = $Allow_Item_Charge_Assignment;
      return $this;
    }

    /**
     * @return float
     */
    public function getQty_to_Assign()
    {
      return $this->Qty_to_Assign;
    }

    /**
     * @param float $Qty_to_Assign
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setQty_to_Assign($Qty_to_Assign)
    {
      $this->Qty_to_Assign = $Qty_to_Assign;
      return $this;
    }

    /**
     * @return float
     */
    public function getQty_Assigned()
    {
      return $this->Qty_Assigned;
    }

    /**
     * @param float $Qty_Assigned
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setQty_Assigned($Qty_Assigned)
    {
      $this->Qty_Assigned = $Qty_Assigned;
      return $this;
    }

    /**
     * @return float
     */
    public function getCustom_Duty_Amount()
    {
      return $this->Custom_Duty_Amount;
    }

    /**
     * @param float $Custom_Duty_Amount
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setCustom_Duty_Amount($Custom_Duty_Amount)
    {
      $this->Custom_Duty_Amount = $Custom_Duty_Amount;
      return $this;
    }

    /**
     * @return GST_Credit
     */
    public function getGST_Credit()
    {
      return $this->GST_Credit;
    }

    /**
     * @param GST_Credit $GST_Credit
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setGST_Credit($GST_Credit)
    {
      $this->GST_Credit = $GST_Credit;
      return $this;
    }

    /**
     * @return string
     */
    public function getGST_Group_Code()
    {
      return $this->GST_Group_Code;
    }

    /**
     * @param string $GST_Group_Code
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setGST_Group_Code($GST_Group_Code)
    {
      $this->GST_Group_Code = $GST_Group_Code;
      return $this;
    }

    /**
     * @return GST_Group_Type
     */
    public function getGST_Group_Type()
    {
      return $this->GST_Group_Type;
    }

    /**
     * @param GST_Group_Type $GST_Group_Type
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setGST_Group_Type($GST_Group_Type)
    {
      $this->GST_Group_Type = $GST_Group_Type;
      return $this;
    }

    /**
     * @return string
     */
    public function getHSN_SAC_Code()
    {
      return $this->HSN_SAC_Code;
    }

    /**
     * @param string $HSN_SAC_Code
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setHSN_SAC_Code($HSN_SAC_Code)
    {
      $this->HSN_SAC_Code = $HSN_SAC_Code;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getExempted()
    {
      return $this->Exempted;
    }

    /**
     * @param boolean $Exempted
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setExempted($Exempted)
    {
      $this->Exempted = $Exempted;
      return $this;
    }

    /**
     * @return float
     */
    public function getGST_Base_Amount()
    {
      return $this->GST_Base_Amount;
    }

    /**
     * @param float $GST_Base_Amount
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setGST_Base_Amount($GST_Base_Amount)
    {
      $this->GST_Base_Amount = $GST_Base_Amount;
      return $this;
    }

    /**
     * @return float
     */
    public function getTotal_GST_Amount()
    {
      return $this->Total_GST_Amount;
    }

    /**
     * @param float $Total_GST_Amount
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setTotal_GST_Amount($Total_GST_Amount)
    {
      $this->Total_GST_Amount = $Total_GST_Amount;
      return $this;
    }

    /**
     * @return GST_Jurisdiction_Type
     */
    public function getGST_Jurisdiction_Type()
    {
      return $this->GST_Jurisdiction_Type;
    }

    /**
     * @param GST_Jurisdiction_Type $GST_Jurisdiction_Type
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setGST_Jurisdiction_Type($GST_Jurisdiction_Type)
    {
      $this->GST_Jurisdiction_Type = $GST_Jurisdiction_Type;
      return $this;
    }

    /**
     * @return float
     */
    public function getBCD_Amount()
    {
      return $this->BCD_Amount;
    }

    /**
     * @param float $BCD_Amount
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setBCD_Amount($BCD_Amount)
    {
      $this->BCD_Amount = $BCD_Amount;
      return $this;
    }

    /**
     * @return float
     */
    public function getCIF_Amount()
    {
      return $this->CIF_Amount;
    }

    /**
     * @param float $CIF_Amount
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setCIF_Amount($CIF_Amount)
    {
      $this->CIF_Amount = $CIF_Amount;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getSupplementary()
    {
      return $this->Supplementary;
    }

    /**
     * @param boolean $Supplementary
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setSupplementary($Supplementary)
    {
      $this->Supplementary = $Supplementary;
      return $this;
    }

    /**
     * @return Source_Document_Type
     */
    public function getSource_Document_Type()
    {
      return $this->Source_Document_Type;
    }

    /**
     * @param Source_Document_Type $Source_Document_Type
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setSource_Document_Type($Source_Document_Type)
    {
      $this->Source_Document_Type = $Source_Document_Type;
      return $this;
    }

    /**
     * @return string
     */
    public function getSource_Document_No()
    {
      return $this->Source_Document_No;
    }

    /**
     * @param string $Source_Document_No
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setSource_Document_No($Source_Document_No)
    {
      $this->Source_Document_No = $Source_Document_No;
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
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
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
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setJob_Task_No($Job_Task_No)
    {
      $this->Job_Task_No = $Job_Task_No;
      return $this;
    }

    /**
     * @return int
     */
    public function getJob_Planning_Line_No()
    {
      return $this->Job_Planning_Line_No;
    }

    /**
     * @param int $Job_Planning_Line_No
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setJob_Planning_Line_No($Job_Planning_Line_No)
    {
      $this->Job_Planning_Line_No = $Job_Planning_Line_No;
      return $this;
    }

    /**
     * @return Job_Line_Type
     */
    public function getJob_Line_Type()
    {
      return $this->Job_Line_Type;
    }

    /**
     * @param Job_Line_Type $Job_Line_Type
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setJob_Line_Type($Job_Line_Type)
    {
      $this->Job_Line_Type = $Job_Line_Type;
      return $this;
    }

    /**
     * @return float
     */
    public function getJob_Unit_Price()
    {
      return $this->Job_Unit_Price;
    }

    /**
     * @param float $Job_Unit_Price
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setJob_Unit_Price($Job_Unit_Price)
    {
      $this->Job_Unit_Price = $Job_Unit_Price;
      return $this;
    }

    /**
     * @return float
     */
    public function getJob_Line_Amount()
    {
      return $this->Job_Line_Amount;
    }

    /**
     * @param float $Job_Line_Amount
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setJob_Line_Amount($Job_Line_Amount)
    {
      $this->Job_Line_Amount = $Job_Line_Amount;
      return $this;
    }

    /**
     * @return float
     */
    public function getJob_Line_Discount_Amount()
    {
      return $this->Job_Line_Discount_Amount;
    }

    /**
     * @param float $Job_Line_Discount_Amount
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setJob_Line_Discount_Amount($Job_Line_Discount_Amount)
    {
      $this->Job_Line_Discount_Amount = $Job_Line_Discount_Amount;
      return $this;
    }

    /**
     * @return float
     */
    public function getJob_Line_Discount_Percent()
    {
      return $this->Job_Line_Discount_Percent;
    }

    /**
     * @param float $Job_Line_Discount_Percent
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setJob_Line_Discount_Percent($Job_Line_Discount_Percent)
    {
      $this->Job_Line_Discount_Percent = $Job_Line_Discount_Percent;
      return $this;
    }

    /**
     * @return float
     */
    public function getJob_Total_Price()
    {
      return $this->Job_Total_Price;
    }

    /**
     * @param float $Job_Total_Price
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setJob_Total_Price($Job_Total_Price)
    {
      $this->Job_Total_Price = $Job_Total_Price;
      return $this;
    }

    /**
     * @return float
     */
    public function getJob_Unit_Price_LCY()
    {
      return $this->Job_Unit_Price_LCY;
    }

    /**
     * @param float $Job_Unit_Price_LCY
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setJob_Unit_Price_LCY($Job_Unit_Price_LCY)
    {
      $this->Job_Unit_Price_LCY = $Job_Unit_Price_LCY;
      return $this;
    }

    /**
     * @return float
     */
    public function getJob_Total_Price_LCY()
    {
      return $this->Job_Total_Price_LCY;
    }

    /**
     * @param float $Job_Total_Price_LCY
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setJob_Total_Price_LCY($Job_Total_Price_LCY)
    {
      $this->Job_Total_Price_LCY = $Job_Total_Price_LCY;
      return $this;
    }

    /**
     * @return float
     */
    public function getJob_Line_Amount_LCY()
    {
      return $this->Job_Line_Amount_LCY;
    }

    /**
     * @param float $Job_Line_Amount_LCY
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setJob_Line_Amount_LCY($Job_Line_Amount_LCY)
    {
      $this->Job_Line_Amount_LCY = $Job_Line_Amount_LCY;
      return $this;
    }

    /**
     * @return float
     */
    public function getJob_Line_Disc_Amount_LCY()
    {
      return $this->Job_Line_Disc_Amount_LCY;
    }

    /**
     * @param float $Job_Line_Disc_Amount_LCY
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setJob_Line_Disc_Amount_LCY($Job_Line_Disc_Amount_LCY)
    {
      $this->Job_Line_Disc_Amount_LCY = $Job_Line_Disc_Amount_LCY;
      return $this;
    }

    /**
     * @return date
     */
    public function getRequested_Receipt_Date()
    {
      return $this->Requested_Receipt_Date;
    }

    /**
     * @param date $Requested_Receipt_Date
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setRequested_Receipt_Date($Requested_Receipt_Date)
    {
      $this->Requested_Receipt_Date = $Requested_Receipt_Date;
      return $this;
    }

    /**
     * @return date
     */
    public function getPromised_Receipt_Date()
    {
      return $this->Promised_Receipt_Date;
    }

    /**
     * @param date $Promised_Receipt_Date
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setPromised_Receipt_Date($Promised_Receipt_Date)
    {
      $this->Promised_Receipt_Date = $Promised_Receipt_Date;
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
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setPlanned_Receipt_Date($Planned_Receipt_Date)
    {
      $this->Planned_Receipt_Date = $Planned_Receipt_Date;
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
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setExpected_Receipt_Date($Expected_Receipt_Date)
    {
      $this->Expected_Receipt_Date = $Expected_Receipt_Date;
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
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setOrder_Date($Order_Date)
    {
      $this->Order_Date = $Order_Date;
      return $this;
    }

    /**
     * @return string
     */
    public function getLead_Time_Calculation()
    {
      return $this->Lead_Time_Calculation;
    }

    /**
     * @param string $Lead_Time_Calculation
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setLead_Time_Calculation($Lead_Time_Calculation)
    {
      $this->Lead_Time_Calculation = $Lead_Time_Calculation;
      return $this;
    }

    /**
     * @return Planning_Flexibility
     */
    public function getPlanning_Flexibility()
    {
      return $this->Planning_Flexibility;
    }

    /**
     * @param Planning_Flexibility $Planning_Flexibility
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setPlanning_Flexibility($Planning_Flexibility)
    {
      $this->Planning_Flexibility = $Planning_Flexibility;
      return $this;
    }

    /**
     * @return string
     */
    public function getProd_Order_No()
    {
      return $this->Prod_Order_No;
    }

    /**
     * @param string $Prod_Order_No
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setProd_Order_No($Prod_Order_No)
    {
      $this->Prod_Order_No = $Prod_Order_No;
      return $this;
    }

    /**
     * @return int
     */
    public function getProd_Order_Line_No()
    {
      return $this->Prod_Order_Line_No;
    }

    /**
     * @param int $Prod_Order_Line_No
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setProd_Order_Line_No($Prod_Order_Line_No)
    {
      $this->Prod_Order_Line_No = $Prod_Order_Line_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getOperation_No()
    {
      return $this->Operation_No;
    }

    /**
     * @param string $Operation_No
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setOperation_No($Operation_No)
    {
      $this->Operation_No = $Operation_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getWork_Center_No()
    {
      return $this->Work_Center_No;
    }

    /**
     * @param string $Work_Center_No
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setWork_Center_No($Work_Center_No)
    {
      $this->Work_Center_No = $Work_Center_No;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getFinished()
    {
      return $this->Finished;
    }

    /**
     * @param boolean $Finished
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setFinished($Finished)
    {
      $this->Finished = $Finished;
      return $this;
    }

    /**
     * @return float
     */
    public function getWhse_Outstanding_Qty_Base()
    {
      return $this->Whse_Outstanding_Qty_Base;
    }

    /**
     * @param float $Whse_Outstanding_Qty_Base
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setWhse_Outstanding_Qty_Base($Whse_Outstanding_Qty_Base)
    {
      $this->Whse_Outstanding_Qty_Base = $Whse_Outstanding_Qty_Base;
      return $this;
    }

    /**
     * @return string
     */
    public function getInbound_Whse_Handling_Time()
    {
      return $this->Inbound_Whse_Handling_Time;
    }

    /**
     * @param string $Inbound_Whse_Handling_Time
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setInbound_Whse_Handling_Time($Inbound_Whse_Handling_Time)
    {
      $this->Inbound_Whse_Handling_Time = $Inbound_Whse_Handling_Time;
      return $this;
    }

    /**
     * @return string
     */
    public function getBlanket_Order_No()
    {
      return $this->Blanket_Order_No;
    }

    /**
     * @param string $Blanket_Order_No
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setBlanket_Order_No($Blanket_Order_No)
    {
      $this->Blanket_Order_No = $Blanket_Order_No;
      return $this;
    }

    /**
     * @return int
     */
    public function getBlanket_Order_Line_No()
    {
      return $this->Blanket_Order_Line_No;
    }

    /**
     * @param int $Blanket_Order_Line_No
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setBlanket_Order_Line_No($Blanket_Order_Line_No)
    {
      $this->Blanket_Order_Line_No = $Blanket_Order_Line_No;
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
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setAppl_to_Item_Entry($Appl_to_Item_Entry)
    {
      $this->Appl_to_Item_Entry = $Appl_to_Item_Entry;
      return $this;
    }

    /**
     * @return string
     */
    public function getDeferral_Code()
    {
      return $this->Deferral_Code;
    }

    /**
     * @param string $Deferral_Code
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setDeferral_Code($Deferral_Code)
    {
      $this->Deferral_Code = $Deferral_Code;
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
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setReason_Code($Reason_Code)
    {
      $this->Reason_Code = $Reason_Code;
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
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
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
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
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
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
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
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
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
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
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
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
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
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
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
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setShortcutDimCode_x005B_8_x005D_($ShortcutDimCode_x005B_8_x005D_)
    {
      $this->ShortcutDimCode_x005B_8_x005D_ = $ShortcutDimCode_x005B_8_x005D_;
      return $this;
    }

    /**
     * @return float
     */
    public function getInvoice_Discount_Amount()
    {
      return $this->Invoice_Discount_Amount;
    }

    /**
     * @param float $Invoice_Discount_Amount
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setInvoice_Discount_Amount($Invoice_Discount_Amount)
    {
      $this->Invoice_Discount_Amount = $Invoice_Discount_Amount;
      return $this;
    }

    /**
     * @return float
     */
    public function getInvoice_Disc_Pct()
    {
      return $this->Invoice_Disc_Pct;
    }

    /**
     * @param float $Invoice_Disc_Pct
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setInvoice_Disc_Pct($Invoice_Disc_Pct)
    {
      $this->Invoice_Disc_Pct = $Invoice_Disc_Pct;
      return $this;
    }

    /**
     * @return float
     */
    public function getTotal_Amount_Excl_VAT()
    {
      return $this->Total_Amount_Excl_VAT;
    }

    /**
     * @param float $Total_Amount_Excl_VAT
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setTotal_Amount_Excl_VAT($Total_Amount_Excl_VAT)
    {
      $this->Total_Amount_Excl_VAT = $Total_Amount_Excl_VAT;
      return $this;
    }

    /**
     * @return float
     */
    public function getTotal_VAT_Amount()
    {
      return $this->Total_VAT_Amount;
    }

    /**
     * @param float $Total_VAT_Amount
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setTotal_VAT_Amount($Total_VAT_Amount)
    {
      $this->Total_VAT_Amount = $Total_VAT_Amount;
      return $this;
    }

    /**
     * @return float
     */
    public function getTotal_Amount_Incl_VAT()
    {
      return $this->Total_Amount_Incl_VAT;
    }

    /**
     * @param float $Total_Amount_Incl_VAT
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setTotal_Amount_Incl_VAT($Total_Amount_Incl_VAT)
    {
      $this->Total_Amount_Incl_VAT = $Total_Amount_Incl_VAT;
      return $this;
    }

    /**
     * @return string
     */
    public function getRefreshTotals()
    {
      return $this->RefreshTotals;
    }

    /**
     * @param string $RefreshTotals
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line
     */
    public function setRefreshTotals($RefreshTotals)
    {
      $this->RefreshTotals = $RefreshTotals;
      return $this;
    }

}
