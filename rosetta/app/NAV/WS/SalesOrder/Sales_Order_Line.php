<?php

namespace Rosetta\NAV\WS\SalesOrder;

class Sales_Order_Line
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
     * @var Excise_Accounting_Type $Excise_Accounting_Type
     */
    protected $Excise_Accounting_Type = null;

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
     * @var string $Service_Tax_Group
     */
    protected $Service_Tax_Group = null;

    /**
     * @var boolean $Claim_Deferred_Excise
     */
    protected $Claim_Deferred_Excise = null;

    /**
     * @var string $Item_Category_Code
     */
    protected $Item_Category_Code = null;

    /**
     * @var string $Product_Group_Code
     */
    protected $Product_Group_Code = null;

    /**
     * @var string $PO_No
     */
    protected $PO_No = null;

    /**
     * @var boolean $Direct_Debit_To_PLA_RG
     */
    protected $Direct_Debit_To_PLA_RG = null;

    /**
     * @var string $Service_Tax_Registration_No
     */
    protected $Service_Tax_Registration_No = null;

    /**
     * @var IC_Partner_Ref_Type $IC_Partner_Ref_Type
     */
    protected $IC_Partner_Ref_Type = null;

    /**
     * @var string $IC_Partner_Reference
     */
    protected $IC_Partner_Reference = null;

    /**
     * @var string $Variant_Code
     */
    protected $Variant_Code = null;

    /**
     * @var boolean $Substitution_Available
     */
    protected $Substitution_Available = null;

    /**
     * @var string $Purchasing_Code
     */
    protected $Purchasing_Code = null;

    /**
     * @var boolean $Nonstock
     */
    protected $Nonstock = null;

    /**
     * @var string $VAT_Prod_Posting_Group
     */
    protected $VAT_Prod_Posting_Group = null;

    /**
     * @var string $Description
     */
    protected $Description = null;

    /**
     * @var Rate_Type $Rate_Type
     */
    protected $Rate_Type = null;

    /**
     * @var boolean $Drop_Shipment
     */
    protected $Drop_Shipment = null;

    /**
     * @var boolean $Tax_Liable
     */
    protected $Tax_Liable = null;

    /**
     * @var boolean $MRP
     */
    protected $MRP = null;

    /**
     * @var float $No_of_Box
     */
    protected $No_of_Box = null;

    /**
     * @var float $MRP_Price
     */
    protected $MRP_Price = null;

    /**
     * @var float $Abatement_Percent
     */
    protected $Abatement_Percent = null;

    /**
     * @var string $PIT_Structure
     */
    protected $PIT_Structure = null;

    /**
     * @var boolean $Price_Inclusive_of_Tax
     */
    protected $Price_Inclusive_of_Tax = null;

    /**
     * @var string $Tax_Group_Code
     */
    protected $Tax_Group_Code = null;

    /**
     * @var float $Unit_Price_Incl_of_Tax
     */
    protected $Unit_Price_Incl_of_Tax = null;

    /**
     * @var float $VAT_Percent
     */
    protected $VAT_Percent = null;

    /**
     * @var boolean $Special_Order
     */
    protected $Special_Order = null;

    /**
     * @var string $Special_Order_Purchase_No
     */
    protected $Special_Order_Purchase_No = null;

    /**
     * @var string $Return_Reason_Code
     */
    protected $Return_Reason_Code = null;

    /**
     * @var string $Location_Code
     */
    protected $Location_Code = null;

    /**
     * @var float $Amount_To_Customer
     */
    protected $Amount_To_Customer = null;

    /**
     * @var float $Amount_Including_VAT
     */
    protected $Amount_Including_VAT = null;

    /**
     * @var float $Amount_Including_Tax
     */
    protected $Amount_Including_Tax = null;

    /**
     * @var string $Bin_Code
     */
    protected $Bin_Code = null;

    /**
     * @var string $TCS_Nature_of_Collection
     */
    protected $TCS_Nature_of_Collection = null;

    /**
     * @var Reserve $Reserve
     */
    protected $Reserve = null;

    /**
     * @var float $Quantity
     */
    protected $Quantity = null;

    /**
     * @var float $Qty_to_Assemble_to_Order
     */
    protected $Qty_to_Assemble_to_Order = null;

    /**
     * @var string $Purchase_Order_No
     */
    protected $Purchase_Order_No = null;

    /**
     * @var float $Reserved_Quantity
     */
    protected $Reserved_Quantity = null;

    /**
     * @var string $Unit_of_Measure_Code
     */
    protected $Unit_of_Measure_Code = null;

    /**
     * @var string $Unit_of_Measure
     */
    protected $Unit_of_Measure = null;

    /**
     * @var float $Unit_Cost_LCY
     */
    protected $Unit_Cost_LCY = null;

    /**
     * @var boolean $SalesPriceExist
     */
    protected $SalesPriceExist = null;

    /**
     * @var float $Unit_Price
     */
    protected $Unit_Price = null;

    /**
     * @var float $Line_Amount
     */
    protected $Line_Amount = null;

    /**
     * @var boolean $SalesLineDiscExists
     */
    protected $SalesLineDiscExists = null;

    /**
     * @var float $Line_Discount_Percent
     */
    protected $Line_Discount_Percent = null;

    /**
     * @var float $Line_Discount_Amount
     */
    protected $Line_Discount_Amount = null;

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
     * @var boolean $Allow_Invoice_Disc
     */
    protected $Allow_Invoice_Disc = null;

    /**
     * @var float $Inv_Discount_Amount
     */
    protected $Inv_Discount_Amount = null;

    /**
     * @var float $Qty_to_Ship
     */
    protected $Qty_to_Ship = null;

    /**
     * @var float $Quantity_Shipped
     */
    protected $Quantity_Shipped = null;

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
     * @var boolean $_x003C_Free_Qty_x003E_
     */
    protected $_x003C_Free_Qty_x003E_ = null;

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
     * @var date $Requested_Delivery_Date
     */
    protected $Requested_Delivery_Date = null;

    /**
     * @var date $Promised_Delivery_Date
     */
    protected $Promised_Delivery_Date = null;

    /**
     * @var date $Planned_Delivery_Date
     */
    protected $Planned_Delivery_Date = null;

    /**
     * @var date $Planned_Shipment_Date
     */
    protected $Planned_Shipment_Date = null;

    /**
     * @var date $Shipment_Date
     */
    protected $Shipment_Date = null;

    /**
     * @var string $Shipping_Agent_Code
     */
    protected $Shipping_Agent_Code = null;

    /**
     * @var string $Shipping_Agent_Service_Code
     */
    protected $Shipping_Agent_Service_Code = null;

    /**
     * @var string $Shipping_Time
     */
    protected $Shipping_Time = null;

    /**
     * @var string $Work_Type_Code
     */
    protected $Work_Type_Code = null;

    /**
     * @var float $Whse_Outstanding_Qty
     */
    protected $Whse_Outstanding_Qty = null;

    /**
     * @var float $Whse_Outstanding_Qty_Base
     */
    protected $Whse_Outstanding_Qty_Base = null;

    /**
     * @var float $ATO_Whse_Outstanding_Qty
     */
    protected $ATO_Whse_Outstanding_Qty = null;

    /**
     * @var float $ATO_Whse_Outstd_Qty_Base
     */
    protected $ATO_Whse_Outstd_Qty_Base = null;

    /**
     * @var string $Outbound_Whse_Handling_Time
     */
    protected $Outbound_Whse_Handling_Time = null;

    /**
     * @var string $Blanket_Order_No
     */
    protected $Blanket_Order_No = null;

    /**
     * @var int $Blanket_Order_Line_No
     */
    protected $Blanket_Order_Line_No = null;

    /**
     * @var date $FA_Posting_Date
     */
    protected $FA_Posting_Date = null;

    /**
     * @var boolean $Depr_until_FA_Posting_Date
     */
    protected $Depr_until_FA_Posting_Date = null;

    /**
     * @var string $Depreciation_Book_Code
     */
    protected $Depreciation_Book_Code = null;

    /**
     * @var boolean $Use_Duplication_List
     */
    protected $Use_Duplication_List = null;

    /**
     * @var string $Duplicate_in_Depreciation_Book
     */
    protected $Duplicate_in_Depreciation_Book = null;

    /**
     * @var int $Appl_from_Item_Entry
     */
    protected $Appl_from_Item_Entry = null;

    /**
     * @var int $Appl_to_Item_Entry
     */
    protected $Appl_to_Item_Entry = null;

    /**
     * @var string $Deferral_Code
     */
    protected $Deferral_Code = null;

    /**
     * @var GST_Place_of_Supply $GST_Place_of_Supply
     */
    protected $GST_Place_of_Supply = null;

    /**
     * @var string $GST_Group_Code
     */
    protected $GST_Group_Code = null;

    /**
     * @var GST_Group_Type $GST_Group_Type
     */
    protected $GST_Group_Type = null;

    /**
     * @var float $GST_Base_Amount
     */
    protected $GST_Base_Amount = null;

    /**
     * @var float $Total_GST_Amount
     */
    protected $Total_GST_Amount = null;

    /**
     * @var string $HSN_SAC_Code
     */
    protected $HSN_SAC_Code = null;

    /**
     * @var GST_Jurisdiction_Type $GST_Jurisdiction_Type
     */
    protected $GST_Jurisdiction_Type = null;

    /**
     * @var Invoice_Type_16609 $Invoice_Type
     */
    protected $Invoice_Type = null;

    /**
     * @var boolean $Exempted
     */
    protected $Exempted = null;

    /**
     * @var string $Shortcut_Dimension_1_Code
     */
    protected $Shortcut_Dimension_1_Code = null;

    /**
     * @var string $Process_Carried_Out
     */
    protected $Process_Carried_Out = null;

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
     * @var string $Document_No
     */
    protected $Document_No = null;

    /**
     * @var boolean $Send_for_approval
     */
    protected $Send_for_approval = null;

    /**
     * @var int $Line_No
     */
    protected $Line_No = null;

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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setType($Type)
    {
      $this->Type = $Type;
      return $this;
    }

    /**
     * @return Excise_Accounting_Type
     */
    public function getExcise_Accounting_Type()
    {
      return $this->Excise_Accounting_Type;
    }

    /**
     * @param Excise_Accounting_Type $Excise_Accounting_Type
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setExcise_Accounting_Type($Excise_Accounting_Type)
    {
      $this->Excise_Accounting_Type = $Excise_Accounting_Type;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setIC_Partner_Code($IC_Partner_Code)
    {
      $this->IC_Partner_Code = $IC_Partner_Code;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setService_Tax_Group($Service_Tax_Group)
    {
      $this->Service_Tax_Group = $Service_Tax_Group;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getClaim_Deferred_Excise()
    {
      return $this->Claim_Deferred_Excise;
    }

    /**
     * @param boolean $Claim_Deferred_Excise
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setClaim_Deferred_Excise($Claim_Deferred_Excise)
    {
      $this->Claim_Deferred_Excise = $Claim_Deferred_Excise;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setItem_Category_Code($Item_Category_Code)
    {
      $this->Item_Category_Code = $Item_Category_Code;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setProduct_Group_Code($Product_Group_Code)
    {
      $this->Product_Group_Code = $Product_Group_Code;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setPO_No($PO_No)
    {
      $this->PO_No = $PO_No;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getDirect_Debit_To_PLA_RG()
    {
      return $this->Direct_Debit_To_PLA_RG;
    }

    /**
     * @param boolean $Direct_Debit_To_PLA_RG
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setDirect_Debit_To_PLA_RG($Direct_Debit_To_PLA_RG)
    {
      $this->Direct_Debit_To_PLA_RG = $Direct_Debit_To_PLA_RG;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setService_Tax_Registration_No($Service_Tax_Registration_No)
    {
      $this->Service_Tax_Registration_No = $Service_Tax_Registration_No;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setIC_Partner_Ref_Type($IC_Partner_Ref_Type)
    {
      $this->IC_Partner_Ref_Type = $IC_Partner_Ref_Type;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setVariant_Code($Variant_Code)
    {
      $this->Variant_Code = $Variant_Code;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getSubstitution_Available()
    {
      return $this->Substitution_Available;
    }

    /**
     * @param boolean $Substitution_Available
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setSubstitution_Available($Substitution_Available)
    {
      $this->Substitution_Available = $Substitution_Available;
      return $this;
    }

    /**
     * @return string
     */
    public function getPurchasing_Code()
    {
      return $this->Purchasing_Code;
    }

    /**
     * @param string $Purchasing_Code
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setPurchasing_Code($Purchasing_Code)
    {
      $this->Purchasing_Code = $Purchasing_Code;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setNonstock($Nonstock)
    {
      $this->Nonstock = $Nonstock;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setDescription($Description)
    {
      $this->Description = $Description;
      return $this;
    }

    /**
     * @return Rate_Type
     */
    public function getRate_Type()
    {
      return $this->Rate_Type;
    }

    /**
     * @param Rate_Type $Rate_Type
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setRate_Type($Rate_Type)
    {
      $this->Rate_Type = $Rate_Type;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setDrop_Shipment($Drop_Shipment)
    {
      $this->Drop_Shipment = $Drop_Shipment;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getTax_Liable()
    {
      return $this->Tax_Liable;
    }

    /**
     * @param boolean $Tax_Liable
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setTax_Liable($Tax_Liable)
    {
      $this->Tax_Liable = $Tax_Liable;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getMRP()
    {
      return $this->MRP;
    }

    /**
     * @param boolean $MRP
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setMRP($MRP)
    {
      $this->MRP = $MRP;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setNo_of_Box($No_of_Box)
    {
      $this->No_of_Box = $No_of_Box;
      return $this;
    }

    /**
     * @return float
     */
    public function getMRP_Price()
    {
      return $this->MRP_Price;
    }

    /**
     * @param float $MRP_Price
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setMRP_Price($MRP_Price)
    {
      $this->MRP_Price = $MRP_Price;
      return $this;
    }

    /**
     * @return float
     */
    public function getAbatement_Percent()
    {
      return $this->Abatement_Percent;
    }

    /**
     * @param float $Abatement_Percent
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setAbatement_Percent($Abatement_Percent)
    {
      $this->Abatement_Percent = $Abatement_Percent;
      return $this;
    }

    /**
     * @return string
     */
    public function getPIT_Structure()
    {
      return $this->PIT_Structure;
    }

    /**
     * @param string $PIT_Structure
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setPIT_Structure($PIT_Structure)
    {
      $this->PIT_Structure = $PIT_Structure;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getPrice_Inclusive_of_Tax()
    {
      return $this->Price_Inclusive_of_Tax;
    }

    /**
     * @param boolean $Price_Inclusive_of_Tax
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setPrice_Inclusive_of_Tax($Price_Inclusive_of_Tax)
    {
      $this->Price_Inclusive_of_Tax = $Price_Inclusive_of_Tax;
      return $this;
    }

    /**
     * @return string
     */
    public function getTax_Group_Code()
    {
      return $this->Tax_Group_Code;
    }

    /**
     * @param string $Tax_Group_Code
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setTax_Group_Code($Tax_Group_Code)
    {
      $this->Tax_Group_Code = $Tax_Group_Code;
      return $this;
    }

    /**
     * @return float
     */
    public function getUnit_Price_Incl_of_Tax()
    {
      return $this->Unit_Price_Incl_of_Tax;
    }

    /**
     * @param float $Unit_Price_Incl_of_Tax
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setUnit_Price_Incl_of_Tax($Unit_Price_Incl_of_Tax)
    {
      $this->Unit_Price_Incl_of_Tax = $Unit_Price_Incl_of_Tax;
      return $this;
    }

    /**
     * @return float
     */
    public function getVAT_Percent()
    {
      return $this->VAT_Percent;
    }

    /**
     * @param float $VAT_Percent
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setVAT_Percent($VAT_Percent)
    {
      $this->VAT_Percent = $VAT_Percent;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getSpecial_Order()
    {
      return $this->Special_Order;
    }

    /**
     * @param boolean $Special_Order
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setSpecial_Order($Special_Order)
    {
      $this->Special_Order = $Special_Order;
      return $this;
    }

    /**
     * @return string
     */
    public function getSpecial_Order_Purchase_No()
    {
      return $this->Special_Order_Purchase_No;
    }

    /**
     * @param string $Special_Order_Purchase_No
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setSpecial_Order_Purchase_No($Special_Order_Purchase_No)
    {
      $this->Special_Order_Purchase_No = $Special_Order_Purchase_No;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setLocation_Code($Location_Code)
    {
      $this->Location_Code = $Location_Code;
      return $this;
    }

    /**
     * @return float
     */
    public function getAmount_To_Customer()
    {
      return $this->Amount_To_Customer;
    }

    /**
     * @param float $Amount_To_Customer
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setAmount_To_Customer($Amount_To_Customer)
    {
      $this->Amount_To_Customer = $Amount_To_Customer;
      return $this;
    }

    /**
     * @return float
     */
    public function getAmount_Including_VAT()
    {
      return $this->Amount_Including_VAT;
    }

    /**
     * @param float $Amount_Including_VAT
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setAmount_Including_VAT($Amount_Including_VAT)
    {
      $this->Amount_Including_VAT = $Amount_Including_VAT;
      return $this;
    }

    /**
     * @return float
     */
    public function getAmount_Including_Tax()
    {
      return $this->Amount_Including_Tax;
    }

    /**
     * @param float $Amount_Including_Tax
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setAmount_Including_Tax($Amount_Including_Tax)
    {
      $this->Amount_Including_Tax = $Amount_Including_Tax;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setBin_Code($Bin_Code)
    {
      $this->Bin_Code = $Bin_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getTCS_Nature_of_Collection()
    {
      return $this->TCS_Nature_of_Collection;
    }

    /**
     * @param string $TCS_Nature_of_Collection
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setTCS_Nature_of_Collection($TCS_Nature_of_Collection)
    {
      $this->TCS_Nature_of_Collection = $TCS_Nature_of_Collection;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setQuantity($Quantity)
    {
      $this->Quantity = $Quantity;
      return $this;
    }

    /**
     * @return float
     */
    public function getQty_to_Assemble_to_Order()
    {
      return $this->Qty_to_Assemble_to_Order;
    }

    /**
     * @param float $Qty_to_Assemble_to_Order
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setQty_to_Assemble_to_Order($Qty_to_Assemble_to_Order)
    {
      $this->Qty_to_Assemble_to_Order = $Qty_to_Assemble_to_Order;
      return $this;
    }

    /**
     * @return string
     */
    public function getPurchase_Order_No()
    {
      return $this->Purchase_Order_No;
    }

    /**
     * @param string $Purchase_Order_No
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setPurchase_Order_No($Purchase_Order_No)
    {
      $this->Purchase_Order_No = $Purchase_Order_No;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setReserved_Quantity($Reserved_Quantity)
    {
      $this->Reserved_Quantity = $Reserved_Quantity;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setUnit_of_Measure($Unit_of_Measure)
    {
      $this->Unit_of_Measure = $Unit_of_Measure;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setUnit_Cost_LCY($Unit_Cost_LCY)
    {
      $this->Unit_Cost_LCY = $Unit_Cost_LCY;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getSalesPriceExist()
    {
      return $this->SalesPriceExist;
    }

    /**
     * @param boolean $SalesPriceExist
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setSalesPriceExist($SalesPriceExist)
    {
      $this->SalesPriceExist = $SalesPriceExist;
      return $this;
    }

    /**
     * @return float
     */
    public function getUnit_Price()
    {
      return $this->Unit_Price;
    }

    /**
     * @param float $Unit_Price
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setUnit_Price($Unit_Price)
    {
      $this->Unit_Price = $Unit_Price;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setLine_Amount($Line_Amount)
    {
      $this->Line_Amount = $Line_Amount;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getSalesLineDiscExists()
    {
      return $this->SalesLineDiscExists;
    }

    /**
     * @param boolean $SalesLineDiscExists
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setSalesLineDiscExists($SalesLineDiscExists)
    {
      $this->SalesLineDiscExists = $SalesLineDiscExists;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setLine_Discount_Amount($Line_Discount_Amount)
    {
      $this->Line_Discount_Amount = $Line_Discount_Amount;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setSource_Document_No($Source_Document_No)
    {
      $this->Source_Document_No = $Source_Document_No;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setInv_Discount_Amount($Inv_Discount_Amount)
    {
      $this->Inv_Discount_Amount = $Inv_Discount_Amount;
      return $this;
    }

    /**
     * @return float
     */
    public function getQty_to_Ship()
    {
      return $this->Qty_to_Ship;
    }

    /**
     * @param float $Qty_to_Ship
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setQty_to_Ship($Qty_to_Ship)
    {
      $this->Qty_to_Ship = $Qty_to_Ship;
      return $this;
    }

    /**
     * @return float
     */
    public function getQuantity_Shipped()
    {
      return $this->Quantity_Shipped;
    }

    /**
     * @param float $Quantity_Shipped
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setQuantity_Shipped($Quantity_Shipped)
    {
      $this->Quantity_Shipped = $Quantity_Shipped;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setPrepmt_Amt_Deducted($Prepmt_Amt_Deducted)
    {
      $this->Prepmt_Amt_Deducted = $Prepmt_Amt_Deducted;
      return $this;
    }

    /**
     * @return boolean
     */
    public function get_x003C_Free_Qty_x003E_()
    {
      return $this->_x003C_Free_Qty_x003E_;
    }

    /**
     * @param boolean $_x003C_Free_Qty_x003E_
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function set_x003C_Free_Qty_x003E_($_x003C_Free_Qty_x003E_)
    {
      $this->_x003C_Free_Qty_x003E_ = $_x003C_Free_Qty_x003E_;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setQty_Assigned($Qty_Assigned)
    {
      $this->Qty_Assigned = $Qty_Assigned;
      return $this;
    }

    /**
     * @return date
     */
    public function getRequested_Delivery_Date()
    {
      return $this->Requested_Delivery_Date;
    }

    /**
     * @param date $Requested_Delivery_Date
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setRequested_Delivery_Date($Requested_Delivery_Date)
    {
      $this->Requested_Delivery_Date = $Requested_Delivery_Date;
      return $this;
    }

    /**
     * @return date
     */
    public function getPromised_Delivery_Date()
    {
      return $this->Promised_Delivery_Date;
    }

    /**
     * @param date $Promised_Delivery_Date
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setPromised_Delivery_Date($Promised_Delivery_Date)
    {
      $this->Promised_Delivery_Date = $Promised_Delivery_Date;
      return $this;
    }

    /**
     * @return date
     */
    public function getPlanned_Delivery_Date()
    {
      return $this->Planned_Delivery_Date;
    }

    /**
     * @param date $Planned_Delivery_Date
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setPlanned_Delivery_Date($Planned_Delivery_Date)
    {
      $this->Planned_Delivery_Date = $Planned_Delivery_Date;
      return $this;
    }

    /**
     * @return date
     */
    public function getPlanned_Shipment_Date()
    {
      return $this->Planned_Shipment_Date;
    }

    /**
     * @param date $Planned_Shipment_Date
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setPlanned_Shipment_Date($Planned_Shipment_Date)
    {
      $this->Planned_Shipment_Date = $Planned_Shipment_Date;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setShipment_Date($Shipment_Date)
    {
      $this->Shipment_Date = $Shipment_Date;
      return $this;
    }

    /**
     * @return string
     */
    public function getShipping_Agent_Code()
    {
      return $this->Shipping_Agent_Code;
    }

    /**
     * @param string $Shipping_Agent_Code
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setShipping_Agent_Code($Shipping_Agent_Code)
    {
      $this->Shipping_Agent_Code = $Shipping_Agent_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getShipping_Agent_Service_Code()
    {
      return $this->Shipping_Agent_Service_Code;
    }

    /**
     * @param string $Shipping_Agent_Service_Code
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setShipping_Agent_Service_Code($Shipping_Agent_Service_Code)
    {
      $this->Shipping_Agent_Service_Code = $Shipping_Agent_Service_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getShipping_Time()
    {
      return $this->Shipping_Time;
    }

    /**
     * @param string $Shipping_Time
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setShipping_Time($Shipping_Time)
    {
      $this->Shipping_Time = $Shipping_Time;
      return $this;
    }

    /**
     * @return string
     */
    public function getWork_Type_Code()
    {
      return $this->Work_Type_Code;
    }

    /**
     * @param string $Work_Type_Code
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setWork_Type_Code($Work_Type_Code)
    {
      $this->Work_Type_Code = $Work_Type_Code;
      return $this;
    }

    /**
     * @return float
     */
    public function getWhse_Outstanding_Qty()
    {
      return $this->Whse_Outstanding_Qty;
    }

    /**
     * @param float $Whse_Outstanding_Qty
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setWhse_Outstanding_Qty($Whse_Outstanding_Qty)
    {
      $this->Whse_Outstanding_Qty = $Whse_Outstanding_Qty;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setWhse_Outstanding_Qty_Base($Whse_Outstanding_Qty_Base)
    {
      $this->Whse_Outstanding_Qty_Base = $Whse_Outstanding_Qty_Base;
      return $this;
    }

    /**
     * @return float
     */
    public function getATO_Whse_Outstanding_Qty()
    {
      return $this->ATO_Whse_Outstanding_Qty;
    }

    /**
     * @param float $ATO_Whse_Outstanding_Qty
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setATO_Whse_Outstanding_Qty($ATO_Whse_Outstanding_Qty)
    {
      $this->ATO_Whse_Outstanding_Qty = $ATO_Whse_Outstanding_Qty;
      return $this;
    }

    /**
     * @return float
     */
    public function getATO_Whse_Outstd_Qty_Base()
    {
      return $this->ATO_Whse_Outstd_Qty_Base;
    }

    /**
     * @param float $ATO_Whse_Outstd_Qty_Base
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setATO_Whse_Outstd_Qty_Base($ATO_Whse_Outstd_Qty_Base)
    {
      $this->ATO_Whse_Outstd_Qty_Base = $ATO_Whse_Outstd_Qty_Base;
      return $this;
    }

    /**
     * @return string
     */
    public function getOutbound_Whse_Handling_Time()
    {
      return $this->Outbound_Whse_Handling_Time;
    }

    /**
     * @param string $Outbound_Whse_Handling_Time
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setOutbound_Whse_Handling_Time($Outbound_Whse_Handling_Time)
    {
      $this->Outbound_Whse_Handling_Time = $Outbound_Whse_Handling_Time;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setBlanket_Order_Line_No($Blanket_Order_Line_No)
    {
      $this->Blanket_Order_Line_No = $Blanket_Order_Line_No;
      return $this;
    }

    /**
     * @return date
     */
    public function getFA_Posting_Date()
    {
      return $this->FA_Posting_Date;
    }

    /**
     * @param date $FA_Posting_Date
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setFA_Posting_Date($FA_Posting_Date)
    {
      $this->FA_Posting_Date = $FA_Posting_Date;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getDepr_until_FA_Posting_Date()
    {
      return $this->Depr_until_FA_Posting_Date;
    }

    /**
     * @param boolean $Depr_until_FA_Posting_Date
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setDepr_until_FA_Posting_Date($Depr_until_FA_Posting_Date)
    {
      $this->Depr_until_FA_Posting_Date = $Depr_until_FA_Posting_Date;
      return $this;
    }

    /**
     * @return string
     */
    public function getDepreciation_Book_Code()
    {
      return $this->Depreciation_Book_Code;
    }

    /**
     * @param string $Depreciation_Book_Code
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setDepreciation_Book_Code($Depreciation_Book_Code)
    {
      $this->Depreciation_Book_Code = $Depreciation_Book_Code;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getUse_Duplication_List()
    {
      return $this->Use_Duplication_List;
    }

    /**
     * @param boolean $Use_Duplication_List
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setUse_Duplication_List($Use_Duplication_List)
    {
      $this->Use_Duplication_List = $Use_Duplication_List;
      return $this;
    }

    /**
     * @return string
     */
    public function getDuplicate_in_Depreciation_Book()
    {
      return $this->Duplicate_in_Depreciation_Book;
    }

    /**
     * @param string $Duplicate_in_Depreciation_Book
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setDuplicate_in_Depreciation_Book($Duplicate_in_Depreciation_Book)
    {
      $this->Duplicate_in_Depreciation_Book = $Duplicate_in_Depreciation_Book;
      return $this;
    }

    /**
     * @return int
     */
    public function getAppl_from_Item_Entry()
    {
      return $this->Appl_from_Item_Entry;
    }

    /**
     * @param int $Appl_from_Item_Entry
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setAppl_from_Item_Entry($Appl_from_Item_Entry)
    {
      $this->Appl_from_Item_Entry = $Appl_from_Item_Entry;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setDeferral_Code($Deferral_Code)
    {
      $this->Deferral_Code = $Deferral_Code;
      return $this;
    }

    /**
     * @return GST_Place_of_Supply
     */
    public function getGST_Place_of_Supply()
    {
      return $this->GST_Place_of_Supply;
    }

    /**
     * @param GST_Place_of_Supply $GST_Place_of_Supply
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setGST_Place_of_Supply($GST_Place_of_Supply)
    {
      $this->GST_Place_of_Supply = $GST_Place_of_Supply;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setGST_Group_Type($GST_Group_Type)
    {
      $this->GST_Group_Type = $GST_Group_Type;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setTotal_GST_Amount($Total_GST_Amount)
    {
      $this->Total_GST_Amount = $Total_GST_Amount;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setHSN_SAC_Code($HSN_SAC_Code)
    {
      $this->HSN_SAC_Code = $HSN_SAC_Code;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setGST_Jurisdiction_Type($GST_Jurisdiction_Type)
    {
      $this->GST_Jurisdiction_Type = $GST_Jurisdiction_Type;
      return $this;
    }

    /**
     * @return Invoice_Type_16609
     */
    public function getInvoice_Type()
    {
      return $this->Invoice_Type;
    }

    /**
     * @param Invoice_Type_16609 $Invoice_Type
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setInvoice_Type($Invoice_Type)
    {
      $this->Invoice_Type = $Invoice_Type;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setExempted($Exempted)
    {
      $this->Exempted = $Exempted;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setShortcut_Dimension_1_Code($Shortcut_Dimension_1_Code)
    {
      $this->Shortcut_Dimension_1_Code = $Shortcut_Dimension_1_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getProcess_Carried_Out()
    {
      return $this->Process_Carried_Out;
    }

    /**
     * @param string $Process_Carried_Out
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setProcess_Carried_Out($Process_Carried_Out)
    {
      $this->Process_Carried_Out = $Process_Carried_Out;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setShortcutDimCode_x005B_8_x005D_($ShortcutDimCode_x005B_8_x005D_)
    {
      $this->ShortcutDimCode_x005B_8_x005D_ = $ShortcutDimCode_x005B_8_x005D_;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setDocument_No($Document_No)
    {
      $this->Document_No = $Document_No;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setSend_for_approval($Send_for_approval)
    {
      $this->Send_for_approval = $Send_for_approval;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setLine_No($Line_No)
    {
      $this->Line_No = $Line_No;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line
     */
    public function setRefreshTotals($RefreshTotals)
    {
      $this->RefreshTotals = $RefreshTotals;
      return $this;
    }

}
