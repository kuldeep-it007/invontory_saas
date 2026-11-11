<?php

namespace Rosetta\NAV\WS\PO;

class PO
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
     * @var string $Buy_from_Vendor_No
     */
    protected $Buy_from_Vendor_No = null;

    /**
     * @var string $Buy_from_Contact_No
     */
    protected $Buy_from_Contact_No = null;

    /**
     * @var string $Buy_from_Vendor_Name
     */
    protected $Buy_from_Vendor_Name = null;

    /**
     * @var string $Buy_from_Address
     */
    protected $Buy_from_Address = null;

    /**
     * @var string $Buy_from_Address_2
     */
    protected $Buy_from_Address_2 = null;

    /**
     * @var string $Buy_from_Post_Code
     */
    protected $Buy_from_Post_Code = null;

    /**
     * @var string $Buy_from_City
     */
    protected $Buy_from_City = null;

    /**
     * @var string $Buy_from_Contact
     */
    protected $Buy_from_Contact = null;

    /**
     * @var int $No_of_Archived_Versions
     */
    protected $No_of_Archived_Versions = null;

    /**
     * @var string $Structure
     */
    protected $Structure = null;

    /**
     * @var boolean $C_Form
     */
    protected $C_Form = null;

    /**
     * @var string $Form_Code
     */
    protected $Form_Code = null;

    /**
     * @var date $Posting_Date
     */
    protected $Posting_Date = null;

    /**
     * @var date $Order_Date
     */
    protected $Order_Date = null;

    /**
     * @var date $Document_Date
     */
    protected $Document_Date = null;

    /**
     * @var string $Quote_No
     */
    protected $Quote_No = null;

    /**
     * @var string $Vendor_Order_No
     */
    protected $Vendor_Order_No = null;

    /**
     * @var string $Vendor_Shipment_No
     */
    protected $Vendor_Shipment_No = null;

    /**
     * @var string $Vendor_Invoice_No
     */
    protected $Vendor_Invoice_No = null;

    /**
     * @var string $Order_Address_Code
     */
    protected $Order_Address_Code = null;

    /**
     * @var string $Purchaser_Code
     */
    protected $Purchaser_Code = null;

    /**
     * @var string $Responsibility_Center
     */
    protected $Responsibility_Center = null;

    /**
     * @var string $Assigned_User_ID
     */
    protected $Assigned_User_ID = null;

    /**
     * @var Job_Queue_Status $Job_Queue_Status
     */
    protected $Job_Queue_Status = null;

    /**
     * @var Status $Status
     */
    protected $Status = null;

    /**
     * @var string $Remarks
     */
    protected $Remarks = null;

    /**
     * @var string $Posting_No_Series
     */
    protected $Posting_No_Series = null;

    /**
     * @var string $Note
     */
    protected $Note = null;

    /**
     * @var float $Payment_AdvancePercent
     */
    protected $Payment_AdvancePercent = null;

    /**
     * @var float $Pre_DeliveryPercent
     */
    protected $Pre_DeliveryPercent = null;

    /**
     * @var float $Post_DeliveryPercent
     */
    protected $Post_DeliveryPercent = null;

    /**
     * @var float $Advance_to_Made
     */
    protected $Advance_to_Made = null;

    /**
     * @var float $Pre_Delivery_Payment_Made
     */
    protected $Pre_Delivery_Payment_Made = null;

    /**
     * @var string $Currency_Code
     */
    protected $Currency_Code = null;

    /**
     * @var string $Payment_Terms_Code
     */
    protected $Payment_Terms_Code = null;

    /**
     * @var string $Shipment_Method_Code
     */
    protected $Shipment_Method_Code = null;

    /**
     * @var string $Transport_Method
     */
    protected $Transport_Method = null;

    /**
     * @var string $Pay_to_Vendor_No
     */
    protected $Pay_to_Vendor_No = null;

    /**
     * @var string $Pay_to_Contact_No
     */
    protected $Pay_to_Contact_No = null;

    /**
     * @var string $Pay_to_Name
     */
    protected $Pay_to_Name = null;

    /**
     * @var string $Pay_to_Address
     */
    protected $Pay_to_Address = null;

    /**
     * @var string $Pay_to_Address_2
     */
    protected $Pay_to_Address_2 = null;

    /**
     * @var string $Pay_to_Post_Code
     */
    protected $Pay_to_Post_Code = null;

    /**
     * @var string $Pay_to_City
     */
    protected $Pay_to_City = null;

    /**
     * @var string $Pay_to_Contact
     */
    protected $Pay_to_Contact = null;

    /**
     * @var string $Shortcut_Dimension_1_Code
     */
    protected $Shortcut_Dimension_1_Code = null;

    /**
     * @var string $Shortcut_Dimension_2_Code
     */
    protected $Shortcut_Dimension_2_Code = null;

    /**
     * @var date $Due_Date
     */
    protected $Due_Date = null;

    /**
     * @var float $Payment_Discount_Percent
     */
    protected $Payment_Discount_Percent = null;

    /**
     * @var date $Pmt_Discount_Date
     */
    protected $Pmt_Discount_Date = null;

    /**
     * @var string $Payment_Method_Code
     */
    protected $Payment_Method_Code = null;

    /**
     * @var string $Payment_Reference
     */
    protected $Payment_Reference = null;

    /**
     * @var string $Creditor_No
     */
    protected $Creditor_No = null;

    /**
     * @var string $On_Hold
     */
    protected $On_Hold = null;

    /**
     * @var boolean $Prices_Including_VAT
     */
    protected $Prices_Including_VAT = null;

    /**
     * @var string $VAT_Bus_Posting_Group
     */
    protected $VAT_Bus_Posting_Group = null;

    /**
     * @var string $Ship_to_Name
     */
    protected $Ship_to_Name = null;

    /**
     * @var string $Ship_to_Address
     */
    protected $Ship_to_Address = null;

    /**
     * @var string $Ship_to_Address_2
     */
    protected $Ship_to_Address_2 = null;

    /**
     * @var string $Ship_to_Post_Code
     */
    protected $Ship_to_Post_Code = null;

    /**
     * @var string $Ship_to_Contact
     */
    protected $Ship_to_Contact = null;

    /**
     * @var string $Ship_to_City
     */
    protected $Ship_to_City = null;

    /**
     * @var string $Location_Code
     */
    protected $Location_Code = null;

    /**
     * @var string $Inbound_Whse_Handling_Time
     */
    protected $Inbound_Whse_Handling_Time = null;

    /**
     * @var string $Lead_Time_Calculation
     */
    protected $Lead_Time_Calculation = null;

    /**
     * @var date $Requested_Receipt_Date
     */
    protected $Requested_Receipt_Date = null;

    /**
     * @var date $Promised_Receipt_Date
     */
    protected $Promised_Receipt_Date = null;

    /**
     * @var date $Expected_Receipt_Date
     */
    protected $Expected_Receipt_Date = null;

    /**
     * @var string $Sell_to_Customer_No
     */
    protected $Sell_to_Customer_No = null;

    /**
     * @var string $Ship_to_Code
     */
    protected $Ship_to_Code = null;

    /**
     * @var string $Transaction_Type
     */
    protected $Transaction_Type = null;

    /**
     * @var string $Transaction_Specification
     */
    protected $Transaction_Specification = null;

    /**
     * @var string $Entry_Point
     */
    protected $Entry_Point = null;

    /**
     * @var string $Area
     */
    protected $Area = null;

    /**
     * @var boolean $Send_Order_To_Partner
     */
    protected $Send_Order_To_Partner = null;

    /**
     * @var float $Prepayment_Percent
     */
    protected $Prepayment_Percent = null;

    /**
     * @var boolean $Compress_Prepayment
     */
    protected $Compress_Prepayment = null;

    /**
     * @var string $Prepmt_Payment_Terms_Code
     */
    protected $Prepmt_Payment_Terms_Code = null;

    /**
     * @var date $Prepayment_Due_Date
     */
    protected $Prepayment_Due_Date = null;

    /**
     * @var float $Prepmt_Payment_Discount_Percent
     */
    protected $Prepmt_Payment_Discount_Percent = null;

    /**
     * @var date $Prepmt_Pmt_Discount_Date
     */
    protected $Prepmt_Pmt_Discount_Date = null;

    /**
     * @var string $Vendor_Cr_Memo_No
     */
    protected $Vendor_Cr_Memo_No = null;

    /**
     * @var Applies_to_Doc_Type $Applies_to_Doc_Type
     */
    protected $Applies_to_Doc_Type = null;

    /**
     * @var string $Applies_to_Doc_No
     */
    protected $Applies_to_Doc_No = null;

    /**
     * @var string $Applies_to_ID
     */
    protected $Applies_to_ID = null;

    /**
     * @var Service_Type_Rev_Chrg $Service_Type_Rev_Chrg
     */
    protected $Service_Type_Rev_Chrg = null;

    /**
     * @var string $Consignment_Note_No
     */
    protected $Consignment_Note_No = null;

    /**
     * @var boolean $Declaration_Form_GTA
     */
    protected $Declaration_Form_GTA = null;

    /**
     * @var boolean $Input_Service_Distribution
     */
    protected $Input_Service_Distribution = null;

    /**
     * @var boolean $Transit_Document
     */
    protected $Transit_Document = null;

    /**
     * @var boolean $Trading
     */
    protected $Trading = null;

    /**
     * @var string $Form_No
     */
    protected $Form_No = null;

    /**
     * @var string $LC_No
     */
    protected $LC_No = null;

    /**
     * @var float $Service_Tax_Rounding_Precision
     */
    protected $Service_Tax_Rounding_Precision = null;

    /**
     * @var Service_Tax_Rounding_Type $Service_Tax_Rounding_Type
     */
    protected $Service_Tax_Rounding_Type = null;

    /**
     * @var boolean $PoT
     */
    protected $PoT = null;

    /**
     * @var Nature_of_Supply $Nature_of_Supply
     */
    protected $Nature_of_Supply = null;

    /**
     * @var GST_Vendor_Type $GST_Vendor_Type
     */
    protected $GST_Vendor_Type = null;

    /**
     * @var Invoice_Type $Invoice_Type
     */
    protected $Invoice_Type = null;

    /**
     * @var boolean $GST_Input_Service_Distribution
     */
    protected $GST_Input_Service_Distribution = null;

    /**
     * @var boolean $Associated_Enterprises
     */
    protected $Associated_Enterprises = null;

    /**
     * @var string $Bill_of_Entry_No
     */
    protected $Bill_of_Entry_No = null;

    /**
     * @var date $Bill_of_Entry_Date
     */
    protected $Bill_of_Entry_Date = null;

    /**
     * @var float $Bill_of_Entry_Value
     */
    protected $Bill_of_Entry_Value = null;

    /**
     * @var string $Manufacturer_E_C_C_No
     */
    protected $Manufacturer_E_C_C_No = null;

    /**
     * @var string $Manufacturer_Name
     */
    protected $Manufacturer_Name = null;

    /**
     * @var string $Manufacturer_Address
     */
    protected $Manufacturer_Address = null;

    /**
     * @var Purchase_Order_Line_List $PurchLines
     */
    protected $PurchLines = null;

    
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
     * @return \Rosetta\NAV\WS\PO\PO
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
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setNo($No)
    {
      $this->No = $No;
      return $this;
    }

    /**
     * @return string
     */
    public function getBuy_from_Vendor_No()
    {
      return $this->Buy_from_Vendor_No;
    }

    /**
     * @param string $Buy_from_Vendor_No
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setBuy_from_Vendor_No($Buy_from_Vendor_No)
    {
      $this->Buy_from_Vendor_No = $Buy_from_Vendor_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getBuy_from_Contact_No()
    {
      return $this->Buy_from_Contact_No;
    }

    /**
     * @param string $Buy_from_Contact_No
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setBuy_from_Contact_No($Buy_from_Contact_No)
    {
      $this->Buy_from_Contact_No = $Buy_from_Contact_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getBuy_from_Vendor_Name()
    {
      return $this->Buy_from_Vendor_Name;
    }

    /**
     * @param string $Buy_from_Vendor_Name
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setBuy_from_Vendor_Name($Buy_from_Vendor_Name)
    {
      $this->Buy_from_Vendor_Name = $Buy_from_Vendor_Name;
      return $this;
    }

    /**
     * @return string
     */
    public function getBuy_from_Address()
    {
      return $this->Buy_from_Address;
    }

    /**
     * @param string $Buy_from_Address
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setBuy_from_Address($Buy_from_Address)
    {
      $this->Buy_from_Address = $Buy_from_Address;
      return $this;
    }

    /**
     * @return string
     */
    public function getBuy_from_Address_2()
    {
      return $this->Buy_from_Address_2;
    }

    /**
     * @param string $Buy_from_Address_2
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setBuy_from_Address_2($Buy_from_Address_2)
    {
      $this->Buy_from_Address_2 = $Buy_from_Address_2;
      return $this;
    }

    /**
     * @return string
     */
    public function getBuy_from_Post_Code()
    {
      return $this->Buy_from_Post_Code;
    }

    /**
     * @param string $Buy_from_Post_Code
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setBuy_from_Post_Code($Buy_from_Post_Code)
    {
      $this->Buy_from_Post_Code = $Buy_from_Post_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getBuy_from_City()
    {
      return $this->Buy_from_City;
    }

    /**
     * @param string $Buy_from_City
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setBuy_from_City($Buy_from_City)
    {
      $this->Buy_from_City = $Buy_from_City;
      return $this;
    }

    /**
     * @return string
     */
    public function getBuy_from_Contact()
    {
      return $this->Buy_from_Contact;
    }

    /**
     * @param string $Buy_from_Contact
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setBuy_from_Contact($Buy_from_Contact)
    {
      $this->Buy_from_Contact = $Buy_from_Contact;
      return $this;
    }

    /**
     * @return int
     */
    public function getNo_of_Archived_Versions()
    {
      return $this->No_of_Archived_Versions;
    }

    /**
     * @param int $No_of_Archived_Versions
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setNo_of_Archived_Versions($No_of_Archived_Versions)
    {
      $this->No_of_Archived_Versions = $No_of_Archived_Versions;
      return $this;
    }

    /**
     * @return string
     */
    public function getStructure()
    {
      return $this->Structure;
    }

    /**
     * @param string $Structure
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setStructure($Structure)
    {
      $this->Structure = $Structure;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getC_Form()
    {
      return $this->C_Form;
    }

    /**
     * @param boolean $C_Form
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setC_Form($C_Form)
    {
      $this->C_Form = $C_Form;
      return $this;
    }

    /**
     * @return string
     */
    public function getForm_Code()
    {
      return $this->Form_Code;
    }

    /**
     * @param string $Form_Code
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setForm_Code($Form_Code)
    {
      $this->Form_Code = $Form_Code;
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
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setPosting_Date($Posting_Date)
    {
      $this->Posting_Date = $Posting_Date;
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
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setOrder_Date($Order_Date)
    {
      $this->Order_Date = $Order_Date;
      return $this;
    }

    /**
     * @return date
     */
    public function getDocument_Date()
    {
      return $this->Document_Date;
    }

    /**
     * @param date $Document_Date
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setDocument_Date($Document_Date)
    {
      $this->Document_Date = $Document_Date;
      return $this;
    }

    /**
     * @return string
     */
    public function getQuote_No()
    {
      return $this->Quote_No;
    }

    /**
     * @param string $Quote_No
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setQuote_No($Quote_No)
    {
      $this->Quote_No = $Quote_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getVendor_Order_No()
    {
      return $this->Vendor_Order_No;
    }

    /**
     * @param string $Vendor_Order_No
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setVendor_Order_No($Vendor_Order_No)
    {
      $this->Vendor_Order_No = $Vendor_Order_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getVendor_Shipment_No()
    {
      return $this->Vendor_Shipment_No;
    }

    /**
     * @param string $Vendor_Shipment_No
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setVendor_Shipment_No($Vendor_Shipment_No)
    {
      $this->Vendor_Shipment_No = $Vendor_Shipment_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getVendor_Invoice_No()
    {
      return $this->Vendor_Invoice_No;
    }

    /**
     * @param string $Vendor_Invoice_No
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setVendor_Invoice_No($Vendor_Invoice_No)
    {
      $this->Vendor_Invoice_No = $Vendor_Invoice_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getOrder_Address_Code()
    {
      return $this->Order_Address_Code;
    }

    /**
     * @param string $Order_Address_Code
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setOrder_Address_Code($Order_Address_Code)
    {
      $this->Order_Address_Code = $Order_Address_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getPurchaser_Code()
    {
      return $this->Purchaser_Code;
    }

    /**
     * @param string $Purchaser_Code
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setPurchaser_Code($Purchaser_Code)
    {
      $this->Purchaser_Code = $Purchaser_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getResponsibility_Center()
    {
      return $this->Responsibility_Center;
    }

    /**
     * @param string $Responsibility_Center
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setResponsibility_Center($Responsibility_Center)
    {
      $this->Responsibility_Center = $Responsibility_Center;
      return $this;
    }

    /**
     * @return string
     */
    public function getAssigned_User_ID()
    {
      return $this->Assigned_User_ID;
    }

    /**
     * @param string $Assigned_User_ID
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setAssigned_User_ID($Assigned_User_ID)
    {
      $this->Assigned_User_ID = $Assigned_User_ID;
      return $this;
    }

    /**
     * @return Job_Queue_Status
     */
    public function getJob_Queue_Status()
    {
      return $this->Job_Queue_Status;
    }

    /**
     * @param Job_Queue_Status $Job_Queue_Status
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setJob_Queue_Status($Job_Queue_Status)
    {
      $this->Job_Queue_Status = $Job_Queue_Status;
      return $this;
    }

    /**
     * @return Status
     */
    public function getStatus()
    {
      return $this->Status;
    }

    /**
     * @param Status $Status
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setStatus($Status)
    {
      $this->Status = $Status;
      return $this;
    }

    /**
     * @return string
     */
    public function getRemarks()
    {
      return $this->Remarks;
    }

    /**
     * @param string $Remarks
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setRemarks($Remarks)
    {
      $this->Remarks = $Remarks;
      return $this;
    }

    /**
     * @return string
     */
    public function getPosting_No_Series()
    {
      return $this->Posting_No_Series;
    }

    /**
     * @param string $Posting_No_Series
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setPosting_No_Series($Posting_No_Series)
    {
      $this->Posting_No_Series = $Posting_No_Series;
      return $this;
    }

    /**
     * @return string
     */
    public function getNote()
    {
      return $this->Note;
    }

    /**
     * @param string $Note
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setNote($Note)
    {
      $this->Note = $Note;
      return $this;
    }

    /**
     * @return float
     */
    public function getPayment_AdvancePercent()
    {
      return $this->Payment_AdvancePercent;
    }

    /**
     * @param float $Payment_AdvancePercent
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setPayment_AdvancePercent($Payment_AdvancePercent)
    {
      $this->Payment_AdvancePercent = $Payment_AdvancePercent;
      return $this;
    }

    /**
     * @return float
     */
    public function getPre_DeliveryPercent()
    {
      return $this->Pre_DeliveryPercent;
    }

    /**
     * @param float $Pre_DeliveryPercent
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setPre_DeliveryPercent($Pre_DeliveryPercent)
    {
      $this->Pre_DeliveryPercent = $Pre_DeliveryPercent;
      return $this;
    }

    /**
     * @return float
     */
    public function getPost_DeliveryPercent()
    {
      return $this->Post_DeliveryPercent;
    }

    /**
     * @param float $Post_DeliveryPercent
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setPost_DeliveryPercent($Post_DeliveryPercent)
    {
      $this->Post_DeliveryPercent = $Post_DeliveryPercent;
      return $this;
    }

    /**
     * @return float
     */
    public function getAdvance_to_Made()
    {
      return $this->Advance_to_Made;
    }

    /**
     * @param float $Advance_to_Made
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setAdvance_to_Made($Advance_to_Made)
    {
      $this->Advance_to_Made = $Advance_to_Made;
      return $this;
    }

    /**
     * @return float
     */
    public function getPre_Delivery_Payment_Made()
    {
      return $this->Pre_Delivery_Payment_Made;
    }

    /**
     * @param float $Pre_Delivery_Payment_Made
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setPre_Delivery_Payment_Made($Pre_Delivery_Payment_Made)
    {
      $this->Pre_Delivery_Payment_Made = $Pre_Delivery_Payment_Made;
      return $this;
    }

    /**
     * @return string
     */
    public function getCurrency_Code()
    {
      return $this->Currency_Code;
    }

    /**
     * @param string $Currency_Code
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setCurrency_Code($Currency_Code)
    {
      $this->Currency_Code = $Currency_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getPayment_Terms_Code()
    {
      return $this->Payment_Terms_Code;
    }

    /**
     * @param string $Payment_Terms_Code
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setPayment_Terms_Code($Payment_Terms_Code)
    {
      $this->Payment_Terms_Code = $Payment_Terms_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getShipment_Method_Code()
    {
      return $this->Shipment_Method_Code;
    }

    /**
     * @param string $Shipment_Method_Code
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setShipment_Method_Code($Shipment_Method_Code)
    {
      $this->Shipment_Method_Code = $Shipment_Method_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getTransport_Method()
    {
      return $this->Transport_Method;
    }

    /**
     * @param string $Transport_Method
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setTransport_Method($Transport_Method)
    {
      $this->Transport_Method = $Transport_Method;
      return $this;
    }

    /**
     * @return string
     */
    public function getPay_to_Vendor_No()
    {
      return $this->Pay_to_Vendor_No;
    }

    /**
     * @param string $Pay_to_Vendor_No
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setPay_to_Vendor_No($Pay_to_Vendor_No)
    {
      $this->Pay_to_Vendor_No = $Pay_to_Vendor_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getPay_to_Contact_No()
    {
      return $this->Pay_to_Contact_No;
    }

    /**
     * @param string $Pay_to_Contact_No
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setPay_to_Contact_No($Pay_to_Contact_No)
    {
      $this->Pay_to_Contact_No = $Pay_to_Contact_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getPay_to_Name()
    {
      return $this->Pay_to_Name;
    }

    /**
     * @param string $Pay_to_Name
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setPay_to_Name($Pay_to_Name)
    {
      $this->Pay_to_Name = $Pay_to_Name;
      return $this;
    }

    /**
     * @return string
     */
    public function getPay_to_Address()
    {
      return $this->Pay_to_Address;
    }

    /**
     * @param string $Pay_to_Address
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setPay_to_Address($Pay_to_Address)
    {
      $this->Pay_to_Address = $Pay_to_Address;
      return $this;
    }

    /**
     * @return string
     */
    public function getPay_to_Address_2()
    {
      return $this->Pay_to_Address_2;
    }

    /**
     * @param string $Pay_to_Address_2
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setPay_to_Address_2($Pay_to_Address_2)
    {
      $this->Pay_to_Address_2 = $Pay_to_Address_2;
      return $this;
    }

    /**
     * @return string
     */
    public function getPay_to_Post_Code()
    {
      return $this->Pay_to_Post_Code;
    }

    /**
     * @param string $Pay_to_Post_Code
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setPay_to_Post_Code($Pay_to_Post_Code)
    {
      $this->Pay_to_Post_Code = $Pay_to_Post_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getPay_to_City()
    {
      return $this->Pay_to_City;
    }

    /**
     * @param string $Pay_to_City
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setPay_to_City($Pay_to_City)
    {
      $this->Pay_to_City = $Pay_to_City;
      return $this;
    }

    /**
     * @return string
     */
    public function getPay_to_Contact()
    {
      return $this->Pay_to_Contact;
    }

    /**
     * @param string $Pay_to_Contact
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setPay_to_Contact($Pay_to_Contact)
    {
      $this->Pay_to_Contact = $Pay_to_Contact;
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
     * @return \Rosetta\NAV\WS\PO\PO
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
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setShortcut_Dimension_2_Code($Shortcut_Dimension_2_Code)
    {
      $this->Shortcut_Dimension_2_Code = $Shortcut_Dimension_2_Code;
      return $this;
    }

    /**
     * @return date
     */
    public function getDue_Date()
    {
      return $this->Due_Date;
    }

    /**
     * @param date $Due_Date
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setDue_Date($Due_Date)
    {
      $this->Due_Date = $Due_Date;
      return $this;
    }

    /**
     * @return float
     */
    public function getPayment_Discount_Percent()
    {
      return $this->Payment_Discount_Percent;
    }

    /**
     * @param float $Payment_Discount_Percent
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setPayment_Discount_Percent($Payment_Discount_Percent)
    {
      $this->Payment_Discount_Percent = $Payment_Discount_Percent;
      return $this;
    }

    /**
     * @return date
     */
    public function getPmt_Discount_Date()
    {
      return $this->Pmt_Discount_Date;
    }

    /**
     * @param date $Pmt_Discount_Date
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setPmt_Discount_Date($Pmt_Discount_Date)
    {
      $this->Pmt_Discount_Date = $Pmt_Discount_Date;
      return $this;
    }

    /**
     * @return string
     */
    public function getPayment_Method_Code()
    {
      return $this->Payment_Method_Code;
    }

    /**
     * @param string $Payment_Method_Code
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setPayment_Method_Code($Payment_Method_Code)
    {
      $this->Payment_Method_Code = $Payment_Method_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getPayment_Reference()
    {
      return $this->Payment_Reference;
    }

    /**
     * @param string $Payment_Reference
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setPayment_Reference($Payment_Reference)
    {
      $this->Payment_Reference = $Payment_Reference;
      return $this;
    }

    /**
     * @return string
     */
    public function getCreditor_No()
    {
      return $this->Creditor_No;
    }

    /**
     * @param string $Creditor_No
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setCreditor_No($Creditor_No)
    {
      $this->Creditor_No = $Creditor_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getOn_Hold()
    {
      return $this->On_Hold;
    }

    /**
     * @param string $On_Hold
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setOn_Hold($On_Hold)
    {
      $this->On_Hold = $On_Hold;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getPrices_Including_VAT()
    {
      return $this->Prices_Including_VAT;
    }

    /**
     * @param boolean $Prices_Including_VAT
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setPrices_Including_VAT($Prices_Including_VAT)
    {
      $this->Prices_Including_VAT = $Prices_Including_VAT;
      return $this;
    }

    /**
     * @return string
     */
    public function getVAT_Bus_Posting_Group()
    {
      return $this->VAT_Bus_Posting_Group;
    }

    /**
     * @param string $VAT_Bus_Posting_Group
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setVAT_Bus_Posting_Group($VAT_Bus_Posting_Group)
    {
      $this->VAT_Bus_Posting_Group = $VAT_Bus_Posting_Group;
      return $this;
    }

    /**
     * @return string
     */
    public function getShip_to_Name()
    {
      return $this->Ship_to_Name;
    }

    /**
     * @param string $Ship_to_Name
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setShip_to_Name($Ship_to_Name)
    {
      $this->Ship_to_Name = $Ship_to_Name;
      return $this;
    }

    /**
     * @return string
     */
    public function getShip_to_Address()
    {
      return $this->Ship_to_Address;
    }

    /**
     * @param string $Ship_to_Address
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setShip_to_Address($Ship_to_Address)
    {
      $this->Ship_to_Address = $Ship_to_Address;
      return $this;
    }

    /**
     * @return string
     */
    public function getShip_to_Address_2()
    {
      return $this->Ship_to_Address_2;
    }

    /**
     * @param string $Ship_to_Address_2
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setShip_to_Address_2($Ship_to_Address_2)
    {
      $this->Ship_to_Address_2 = $Ship_to_Address_2;
      return $this;
    }

    /**
     * @return string
     */
    public function getShip_to_Post_Code()
    {
      return $this->Ship_to_Post_Code;
    }

    /**
     * @param string $Ship_to_Post_Code
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setShip_to_Post_Code($Ship_to_Post_Code)
    {
      $this->Ship_to_Post_Code = $Ship_to_Post_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getShip_to_Contact()
    {
      return $this->Ship_to_Contact;
    }

    /**
     * @param string $Ship_to_Contact
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setShip_to_Contact($Ship_to_Contact)
    {
      $this->Ship_to_Contact = $Ship_to_Contact;
      return $this;
    }

    /**
     * @return string
     */
    public function getShip_to_City()
    {
      return $this->Ship_to_City;
    }

    /**
     * @param string $Ship_to_City
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setShip_to_City($Ship_to_City)
    {
      $this->Ship_to_City = $Ship_to_City;
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
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setLocation_Code($Location_Code)
    {
      $this->Location_Code = $Location_Code;
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
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setInbound_Whse_Handling_Time($Inbound_Whse_Handling_Time)
    {
      $this->Inbound_Whse_Handling_Time = $Inbound_Whse_Handling_Time;
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
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setLead_Time_Calculation($Lead_Time_Calculation)
    {
      $this->Lead_Time_Calculation = $Lead_Time_Calculation;
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
     * @return \Rosetta\NAV\WS\PO\PO
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
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setPromised_Receipt_Date($Promised_Receipt_Date)
    {
      $this->Promised_Receipt_Date = $Promised_Receipt_Date;
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
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setExpected_Receipt_Date($Expected_Receipt_Date)
    {
      $this->Expected_Receipt_Date = $Expected_Receipt_Date;
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
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setSell_to_Customer_No($Sell_to_Customer_No)
    {
      $this->Sell_to_Customer_No = $Sell_to_Customer_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getShip_to_Code()
    {
      return $this->Ship_to_Code;
    }

    /**
     * @param string $Ship_to_Code
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setShip_to_Code($Ship_to_Code)
    {
      $this->Ship_to_Code = $Ship_to_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getTransaction_Type()
    {
      return $this->Transaction_Type;
    }

    /**
     * @param string $Transaction_Type
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setTransaction_Type($Transaction_Type)
    {
      $this->Transaction_Type = $Transaction_Type;
      return $this;
    }

    /**
     * @return string
     */
    public function getTransaction_Specification()
    {
      return $this->Transaction_Specification;
    }

    /**
     * @param string $Transaction_Specification
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setTransaction_Specification($Transaction_Specification)
    {
      $this->Transaction_Specification = $Transaction_Specification;
      return $this;
    }

    /**
     * @return string
     */
    public function getEntry_Point()
    {
      return $this->Entry_Point;
    }

    /**
     * @param string $Entry_Point
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setEntry_Point($Entry_Point)
    {
      $this->Entry_Point = $Entry_Point;
      return $this;
    }

    /**
     * @return string
     */
    public function getArea()
    {
      return $this->Area;
    }

    /**
     * @param string $Area
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setArea($Area)
    {
      $this->Area = $Area;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getSend_Order_To_Partner()
    {
      return $this->Send_Order_To_Partner;
    }

    /**
     * @param boolean $Send_Order_To_Partner
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setSend_Order_To_Partner($Send_Order_To_Partner)
    {
      $this->Send_Order_To_Partner = $Send_Order_To_Partner;
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
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setPrepayment_Percent($Prepayment_Percent)
    {
      $this->Prepayment_Percent = $Prepayment_Percent;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getCompress_Prepayment()
    {
      return $this->Compress_Prepayment;
    }

    /**
     * @param boolean $Compress_Prepayment
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setCompress_Prepayment($Compress_Prepayment)
    {
      $this->Compress_Prepayment = $Compress_Prepayment;
      return $this;
    }

    /**
     * @return string
     */
    public function getPrepmt_Payment_Terms_Code()
    {
      return $this->Prepmt_Payment_Terms_Code;
    }

    /**
     * @param string $Prepmt_Payment_Terms_Code
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setPrepmt_Payment_Terms_Code($Prepmt_Payment_Terms_Code)
    {
      $this->Prepmt_Payment_Terms_Code = $Prepmt_Payment_Terms_Code;
      return $this;
    }

    /**
     * @return date
     */
    public function getPrepayment_Due_Date()
    {
      return $this->Prepayment_Due_Date;
    }

    /**
     * @param date $Prepayment_Due_Date
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setPrepayment_Due_Date($Prepayment_Due_Date)
    {
      $this->Prepayment_Due_Date = $Prepayment_Due_Date;
      return $this;
    }

    /**
     * @return float
     */
    public function getPrepmt_Payment_Discount_Percent()
    {
      return $this->Prepmt_Payment_Discount_Percent;
    }

    /**
     * @param float $Prepmt_Payment_Discount_Percent
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setPrepmt_Payment_Discount_Percent($Prepmt_Payment_Discount_Percent)
    {
      $this->Prepmt_Payment_Discount_Percent = $Prepmt_Payment_Discount_Percent;
      return $this;
    }

    /**
     * @return date
     */
    public function getPrepmt_Pmt_Discount_Date()
    {
      return $this->Prepmt_Pmt_Discount_Date;
    }

    /**
     * @param date $Prepmt_Pmt_Discount_Date
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setPrepmt_Pmt_Discount_Date($Prepmt_Pmt_Discount_Date)
    {
      $this->Prepmt_Pmt_Discount_Date = $Prepmt_Pmt_Discount_Date;
      return $this;
    }

    /**
     * @return string
     */
    public function getVendor_Cr_Memo_No()
    {
      return $this->Vendor_Cr_Memo_No;
    }

    /**
     * @param string $Vendor_Cr_Memo_No
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setVendor_Cr_Memo_No($Vendor_Cr_Memo_No)
    {
      $this->Vendor_Cr_Memo_No = $Vendor_Cr_Memo_No;
      return $this;
    }

    /**
     * @return Applies_to_Doc_Type
     */
    public function getApplies_to_Doc_Type()
    {
      return $this->Applies_to_Doc_Type;
    }

    /**
     * @param Applies_to_Doc_Type $Applies_to_Doc_Type
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setApplies_to_Doc_Type($Applies_to_Doc_Type)
    {
      $this->Applies_to_Doc_Type = $Applies_to_Doc_Type;
      return $this;
    }

    /**
     * @return string
     */
    public function getApplies_to_Doc_No()
    {
      return $this->Applies_to_Doc_No;
    }

    /**
     * @param string $Applies_to_Doc_No
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setApplies_to_Doc_No($Applies_to_Doc_No)
    {
      $this->Applies_to_Doc_No = $Applies_to_Doc_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getApplies_to_ID()
    {
      return $this->Applies_to_ID;
    }

    /**
     * @param string $Applies_to_ID
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setApplies_to_ID($Applies_to_ID)
    {
      $this->Applies_to_ID = $Applies_to_ID;
      return $this;
    }

    /**
     * @return Service_Type_Rev_Chrg
     */
    public function getService_Type_Rev_Chrg()
    {
      return $this->Service_Type_Rev_Chrg;
    }

    /**
     * @param Service_Type_Rev_Chrg $Service_Type_Rev_Chrg
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setService_Type_Rev_Chrg($Service_Type_Rev_Chrg)
    {
      $this->Service_Type_Rev_Chrg = $Service_Type_Rev_Chrg;
      return $this;
    }

    /**
     * @return string
     */
    public function getConsignment_Note_No()
    {
      return $this->Consignment_Note_No;
    }

    /**
     * @param string $Consignment_Note_No
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setConsignment_Note_No($Consignment_Note_No)
    {
      $this->Consignment_Note_No = $Consignment_Note_No;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getDeclaration_Form_GTA()
    {
      return $this->Declaration_Form_GTA;
    }

    /**
     * @param boolean $Declaration_Form_GTA
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setDeclaration_Form_GTA($Declaration_Form_GTA)
    {
      $this->Declaration_Form_GTA = $Declaration_Form_GTA;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getInput_Service_Distribution()
    {
      return $this->Input_Service_Distribution;
    }

    /**
     * @param boolean $Input_Service_Distribution
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setInput_Service_Distribution($Input_Service_Distribution)
    {
      $this->Input_Service_Distribution = $Input_Service_Distribution;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getTransit_Document()
    {
      return $this->Transit_Document;
    }

    /**
     * @param boolean $Transit_Document
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setTransit_Document($Transit_Document)
    {
      $this->Transit_Document = $Transit_Document;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getTrading()
    {
      return $this->Trading;
    }

    /**
     * @param boolean $Trading
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setTrading($Trading)
    {
      $this->Trading = $Trading;
      return $this;
    }

    /**
     * @return string
     */
    public function getForm_No()
    {
      return $this->Form_No;
    }

    /**
     * @param string $Form_No
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setForm_No($Form_No)
    {
      $this->Form_No = $Form_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getLC_No()
    {
      return $this->LC_No;
    }

    /**
     * @param string $LC_No
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setLC_No($LC_No)
    {
      $this->LC_No = $LC_No;
      return $this;
    }

    /**
     * @return float
     */
    public function getService_Tax_Rounding_Precision()
    {
      return $this->Service_Tax_Rounding_Precision;
    }

    /**
     * @param float $Service_Tax_Rounding_Precision
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setService_Tax_Rounding_Precision($Service_Tax_Rounding_Precision)
    {
      $this->Service_Tax_Rounding_Precision = $Service_Tax_Rounding_Precision;
      return $this;
    }

    /**
     * @return Service_Tax_Rounding_Type
     */
    public function getService_Tax_Rounding_Type()
    {
      return $this->Service_Tax_Rounding_Type;
    }

    /**
     * @param Service_Tax_Rounding_Type $Service_Tax_Rounding_Type
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setService_Tax_Rounding_Type($Service_Tax_Rounding_Type)
    {
      $this->Service_Tax_Rounding_Type = $Service_Tax_Rounding_Type;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getPoT()
    {
      return $this->PoT;
    }

    /**
     * @param boolean $PoT
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setPoT($PoT)
    {
      $this->PoT = $PoT;
      return $this;
    }

    /**
     * @return Nature_of_Supply
     */
    public function getNature_of_Supply()
    {
      return $this->Nature_of_Supply;
    }

    /**
     * @param Nature_of_Supply $Nature_of_Supply
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setNature_of_Supply($Nature_of_Supply)
    {
      $this->Nature_of_Supply = $Nature_of_Supply;
      return $this;
    }

    /**
     * @return GST_Vendor_Type
     */
    public function getGST_Vendor_Type()
    {
      return $this->GST_Vendor_Type;
    }

    /**
     * @param GST_Vendor_Type $GST_Vendor_Type
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setGST_Vendor_Type($GST_Vendor_Type)
    {
      $this->GST_Vendor_Type = $GST_Vendor_Type;
      return $this;
    }

    /**
     * @return Invoice_Type
     */
    public function getInvoice_Type()
    {
      return $this->Invoice_Type;
    }

    /**
     * @param Invoice_Type $Invoice_Type
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setInvoice_Type($Invoice_Type)
    {
      $this->Invoice_Type = $Invoice_Type;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getGST_Input_Service_Distribution()
    {
      return $this->GST_Input_Service_Distribution;
    }

    /**
     * @param boolean $GST_Input_Service_Distribution
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setGST_Input_Service_Distribution($GST_Input_Service_Distribution)
    {
      $this->GST_Input_Service_Distribution = $GST_Input_Service_Distribution;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getAssociated_Enterprises()
    {
      return $this->Associated_Enterprises;
    }

    /**
     * @param boolean $Associated_Enterprises
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setAssociated_Enterprises($Associated_Enterprises)
    {
      $this->Associated_Enterprises = $Associated_Enterprises;
      return $this;
    }

    /**
     * @return string
     */
    public function getBill_of_Entry_No()
    {
      return $this->Bill_of_Entry_No;
    }

    /**
     * @param string $Bill_of_Entry_No
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setBill_of_Entry_No($Bill_of_Entry_No)
    {
      $this->Bill_of_Entry_No = $Bill_of_Entry_No;
      return $this;
    }

    /**
     * @return date
     */
    public function getBill_of_Entry_Date()
    {
      return $this->Bill_of_Entry_Date;
    }

    /**
     * @param date $Bill_of_Entry_Date
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setBill_of_Entry_Date($Bill_of_Entry_Date)
    {
      $this->Bill_of_Entry_Date = $Bill_of_Entry_Date;
      return $this;
    }

    /**
     * @return float
     */
    public function getBill_of_Entry_Value()
    {
      return $this->Bill_of_Entry_Value;
    }

    /**
     * @param float $Bill_of_Entry_Value
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setBill_of_Entry_Value($Bill_of_Entry_Value)
    {
      $this->Bill_of_Entry_Value = $Bill_of_Entry_Value;
      return $this;
    }

    /**
     * @return string
     */
    public function getManufacturer_E_C_C_No()
    {
      return $this->Manufacturer_E_C_C_No;
    }

    /**
     * @param string $Manufacturer_E_C_C_No
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setManufacturer_E_C_C_No($Manufacturer_E_C_C_No)
    {
      $this->Manufacturer_E_C_C_No = $Manufacturer_E_C_C_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getManufacturer_Name()
    {
      return $this->Manufacturer_Name;
    }

    /**
     * @param string $Manufacturer_Name
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setManufacturer_Name($Manufacturer_Name)
    {
      $this->Manufacturer_Name = $Manufacturer_Name;
      return $this;
    }

    /**
     * @return string
     */
    public function getManufacturer_Address()
    {
      return $this->Manufacturer_Address;
    }

    /**
     * @param string $Manufacturer_Address
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setManufacturer_Address($Manufacturer_Address)
    {
      $this->Manufacturer_Address = $Manufacturer_Address;
      return $this;
    }

    /**
     * @return Purchase_Order_Line_List
     */
    public function getPurchLines()
    {
      return $this->PurchLines;
    }

    /**
     * @param Purchase_Order_Line_List $PurchLines
     * @return \Rosetta\NAV\WS\PO\PO
     */
    public function setPurchLines($PurchLines)
    {
      $this->PurchLines = $PurchLines;
      return $this;
    }

}
