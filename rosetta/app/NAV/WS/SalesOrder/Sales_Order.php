<?php

namespace Rosetta\NAV\WS\SalesOrder;

class Sales_Order
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
     * @var string $Sell_to_Customer_No
     */
    protected $Sell_to_Customer_No = null;

    /**
     * @var string $Sell_to_Contact_No
     */
    protected $Sell_to_Contact_No = null;

    /**
     * @var string $Sell_to_Customer_Name
     */
    protected $Sell_to_Customer_Name = null;

    /**
     * @var string $Sell_to_Address
     */
    protected $Sell_to_Address = null;

    /**
     * @var string $Sell_to_Address_2
     */
    protected $Sell_to_Address_2 = null;

    /**
     * @var string $Sell_to_Post_Code
     */
    protected $Sell_to_Post_Code = null;

    /**
     * @var string $Sell_to_City
     */
    protected $Sell_to_City = null;

    /**
     * @var string $Sell_to_Contact
     */
    protected $Sell_to_Contact = null;

    /**
     * @var int $No_of_Archived_Versions
     */
    protected $No_of_Archived_Versions = null;

    /**
     * @var string $TRN_No
     */
    protected $TRN_No = null;

    /**
     * @var string $Customer_Disc_Group
     */
    protected $Customer_Disc_Group = null;

    /**
     * @var string $E_Mail
     */
    protected $E_Mail = null;

    /**
     * @var string $Structure
     */
    protected $Structure = null;

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
     * @var date $Due_Date
     */
    protected $Due_Date = null;

    /**
     * @var date $Requested_Delivery_Date
     */
    protected $Requested_Delivery_Date = null;

    /**
     * @var date $Promised_Delivery_Date
     */
    protected $Promised_Delivery_Date = null;

    /**
     * @var string $Quote_No
     */
    protected $Quote_No = null;

    /**
     * @var string $External_Document_No
     */
    protected $External_Document_No = null;

    /**
     * @var string $Salesperson_Code
     */
    protected $Salesperson_Code = null;

    /**
     * @var string $Location_Code
     */
    protected $Location_Code = null;

    /**
     * @var boolean $Send_for_approval
     */
    protected $Send_for_approval = null;

    /**
     * @var string $Campaign_No
     */
    protected $Campaign_No = null;

    /**
     * @var string $Opportunity_No
     */
    protected $Opportunity_No = null;

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
     * @var boolean $Order_On_Credit_days_Hold
     */
    protected $Order_On_Credit_days_Hold = null;

    /**
     * @var boolean $Order_On_Credit_Hold
     */
    protected $Order_On_Credit_Hold = null;

    /**
     * @var boolean $Price_Hold_Flag
     */
    protected $Price_Hold_Flag = null;

    /**
     * @var Rejected $Rejected
     */
    protected $Rejected = null;

    /**
     * @var boolean $Order_On_Over_Due_Hold
     */
    protected $Order_On_Over_Due_Hold = null;

    /**
     * @var boolean $Over_Due_Hold_Mail_Sent
     */
    protected $Over_Due_Hold_Mail_Sent = null;

    /**
     * @var string $Remarks
     */
    protected $Remarks = null;

    /**
     * @var string $Posting_No_Series
     */
    protected $Posting_No_Series = null;

    /**
     * @var string $GST_No
     */
    protected $GST_No = null;

    /**
     * @var GST_Customer_Type $GST_Customer_Type
     */
    protected $GST_Customer_Type = null;

    /**
     * @var string $GST_Bill_to_State_Code
     */
    protected $GST_Bill_to_State_Code = null;

    /**
     * @var string $GST_Ship_to_State_Code
     */
    protected $GST_Ship_to_State_Code = null;

    /**
     * @var string $Location_State_Code
     */
    protected $Location_State_Code = null;

    /**
     * @var Invoice_Type $Invoice_Type
     */
    protected $Invoice_Type = null;

    /**
     * @var string $LR_RR_No
     */
    protected $LR_RR_No = null;

    /**
     * @var date $LR_RR_Date
     */
    protected $LR_RR_Date = null;

    /**
     * @var string $Vehicle_No
     */
    protected $Vehicle_No = null;

    /**
     * @var Nature_of_Supply $Nature_of_Supply
     */
    protected $Nature_of_Supply = null;

    /**
     * @var float $Amount
     */
    protected $Amount = null;

    /**
     * @var string $Posting_No
     */
    protected $Posting_No = null;

    /**
     * @var float $Amount_to_Customer
     */
    protected $Amount_to_Customer = null;

    /**
     * @var string $Area_Code
     */
    protected $Area_Code = null;

    /**
     * @var string $Department_Code
     */
    protected $Department_Code = null;

    /**
     * @var boolean $Drop_Shipment
     */
    protected $Drop_Shipment = null;

    /**
     * @var string $Bill_to_Customer_No
     */
    protected $Bill_to_Customer_No = null;

    /**
     * @var string $Bill_to_Contact_No
     */
    protected $Bill_to_Contact_No = null;

    /**
     * @var string $Bill_to_Name
     */
    protected $Bill_to_Name = null;

    /**
     * @var string $Bill_to_Address
     */
    protected $Bill_to_Address = null;

    /**
     * @var string $Bill_to_Address_2
     */
    protected $Bill_to_Address_2 = null;

    /**
     * @var string $Bill_to_Post_Code
     */
    protected $Bill_to_Post_Code = null;

    /**
     * @var string $Bill_to_City
     */
    protected $Bill_to_City = null;

    /**
     * @var string $Bill_to_Contact
     */
    protected $Bill_to_Contact = null;

    /**
     * @var string $Cust_No_ORC
     */
    protected $Cust_No_ORC = null;

    /**
     * @var float $Amount_ORC
     */
    protected $Amount_ORC = null;

    /**
     * @var float $Commission_Percent_ORC
     */
    protected $Commission_Percent_ORC = null;

    /**
     * @var string $Payment_Terms_Code
     */
    protected $Payment_Terms_Code = null;

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
     * @var string $Direct_Debit_Mandate_ID
     */
    protected $Direct_Debit_Mandate_ID = null;

    /**
     * @var boolean $Prices_Including_VAT
     */
    protected $Prices_Including_VAT = null;

    /**
     * @var string $VAT_Bus_Posting_Group
     */
    protected $VAT_Bus_Posting_Group = null;

    /**
     * @var string $Credit_Card_No
     */
    protected $Credit_Card_No = null;

    /**
     * @var string $GetCreditcardNumber
     */
    protected $GetCreditcardNumber = null;

    /**
     * @var string $Ship_to_Code
     */
    protected $Ship_to_Code = null;

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
     * @var string $Ship_to_City
     */
    protected $Ship_to_City = null;

    /**
     * @var string $State
     */
    protected $State = null;

    /**
     * @var string $Tax_Area_Code
     */
    protected $Tax_Area_Code = null;

    /**
     * @var float $GST_Rounding_Precision
     */
    protected $GST_Rounding_Precision = null;

    /**
     * @var GST_Rounding_Type $GST_Rounding_Type
     */
    protected $GST_Rounding_Type = null;

    /**
     * @var boolean $Tax_Liable
     */
    protected $Tax_Liable = null;

    /**
     * @var string $Ship_to_Contact
     */
    protected $Ship_to_Contact = null;

    /**
     * @var string $Ship_to_Country_Region_Code
     */
    protected $Ship_to_Country_Region_Code = null;

    /**
     * @var string $Ship_to_County
     */
    protected $Ship_to_County = null;

    /**
     * @var string $Outbound_Whse_Handling_Time
     */
    protected $Outbound_Whse_Handling_Time = null;

    /**
     * @var string $Shipment_Method_Code
     */
    protected $Shipment_Method_Code = null;

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
     * @var boolean $Late_Order_Shipping
     */
    protected $Late_Order_Shipping = null;

    /**
     * @var string $Package_Tracking_No
     */
    protected $Package_Tracking_No = null;

    /**
     * @var date $Shipment_Date
     */
    protected $Shipment_Date = null;

    /**
     * @var Shipping_Advice $Shipping_Advice
     */
    protected $Shipping_Advice = null;

    /**
     * @var string $Currency_Code
     */
    protected $Currency_Code = null;

    /**
     * @var boolean $EU_3_Party_Trade
     */
    protected $EU_3_Party_Trade = null;

    /**
     * @var string $Transaction_Type
     */
    protected $Transaction_Type = null;

    /**
     * @var string $Transaction_Specification
     */
    protected $Transaction_Specification = null;

    /**
     * @var string $Transport_Method
     */
    protected $Transport_Method = null;

    /**
     * @var string $Exit_Point
     */
    protected $Exit_Point = null;

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
     * @var boolean $Transit_Document
     */
    protected $Transit_Document = null;

    /**
     * @var boolean $Free_Supply
     */
    protected $Free_Supply = null;

    /**
     * @var boolean $TDS_Certificate_Receivable
     */
    protected $TDS_Certificate_Receivable = null;

    /**
     * @var boolean $Calc_Inv_Discount_Percent
     */
    protected $Calc_Inv_Discount_Percent = null;

    /**
     * @var boolean $Export_or_Deemed_Export
     */
    protected $Export_or_Deemed_Export = null;

    /**
     * @var boolean $VAT_Exempted
     */
    protected $VAT_Exempted = null;

    /**
     * @var Nature_of_Services $Nature_of_Services
     */
    protected $Nature_of_Services = null;

    /**
     * @var boolean $Trading
     */
    protected $Trading = null;

    /**
     * @var boolean $ST_Pure_Agent
     */
    protected $ST_Pure_Agent = null;

    /**
     * @var boolean $Re_Dispatch
     */
    protected $Re_Dispatch = null;

    /**
     * @var boolean $PoT
     */
    protected $PoT = null;

    /**
     * @var string $ReturnOrderNo
     */
    protected $ReturnOrderNo = null;

    /**
     * @var string $LC_No
     */
    protected $LC_No = null;

    /**
     * @var string $Form_Code
     */
    protected $Form_Code = null;

    /**
     * @var string $Form_No
     */
    protected $Form_No = null;

    /**
     * @var time $Time_of_Removal
     */
    protected $Time_of_Removal = null;

    /**
     * @var string $CallbackUrl
     */
    protected $CallbackUrl = null;

    /**
     * @var string $Mode_of_Transport
     */
    protected $Mode_of_Transport = null;

    /**
     * @var float $Service_Tax_Rounding_Precision
     */
    protected $Service_Tax_Rounding_Precision = null;

    /**
     * @var Service_Tax_Rounding_Type $Service_Tax_Rounding_Type
     */
    protected $Service_Tax_Rounding_Type = null;

    /**
     * @var boolean $GST_Without_Payment_of_Duty
     */
    protected $GST_Without_Payment_of_Duty = null;

    /**
     * @var string $Bill_Of_Export_No
     */
    protected $Bill_Of_Export_No = null;

    /**
     * @var date $Bill_Of_Export_Date
     */
    protected $Bill_Of_Export_Date = null;

    /**
     * @var string $e_Commerce_Customer
     */
    protected $e_Commerce_Customer = null;

    /**
     * @var string $User_ID
     */
    protected $User_ID = null;

    /**
     * @var string $e_Commerce_Merchant_Id
     */
    protected $e_Commerce_Merchant_Id = null;

    /**
     * @var Sales_Order_Line_List $SalesLines
     */
    protected $SalesLines = null;

    
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setNo($No)
    {
      $this->No = $No;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setSell_to_Customer_No($Sell_to_Customer_No)
    {
      $this->Sell_to_Customer_No = $Sell_to_Customer_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getSell_to_Contact_No()
    {
      return $this->Sell_to_Contact_No;
    }

    /**
     * @param string $Sell_to_Contact_No
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setSell_to_Contact_No($Sell_to_Contact_No)
    {
      $this->Sell_to_Contact_No = $Sell_to_Contact_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getSell_to_Customer_Name()
    {
      return $this->Sell_to_Customer_Name;
    }

    /**
     * @param string $Sell_to_Customer_Name
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setSell_to_Customer_Name($Sell_to_Customer_Name)
    {
      $this->Sell_to_Customer_Name = $Sell_to_Customer_Name;
      return $this;
    }

    /**
     * @return string
     */
    public function getSell_to_Address()
    {
      return $this->Sell_to_Address;
    }

    /**
     * @param string $Sell_to_Address
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setSell_to_Address($Sell_to_Address)
    {
      $this->Sell_to_Address = $Sell_to_Address;
      return $this;
    }

    /**
     * @return string
     */
    public function getSell_to_Address_2()
    {
      return $this->Sell_to_Address_2;
    }

    /**
     * @param string $Sell_to_Address_2
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setSell_to_Address_2($Sell_to_Address_2)
    {
      $this->Sell_to_Address_2 = $Sell_to_Address_2;
      return $this;
    }

    /**
     * @return string
     */
    public function getSell_to_Post_Code()
    {
      return $this->Sell_to_Post_Code;
    }

    /**
     * @param string $Sell_to_Post_Code
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setSell_to_Post_Code($Sell_to_Post_Code)
    {
      $this->Sell_to_Post_Code = $Sell_to_Post_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getSell_to_City()
    {
      return $this->Sell_to_City;
    }

    /**
     * @param string $Sell_to_City
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setSell_to_City($Sell_to_City)
    {
      $this->Sell_to_City = $Sell_to_City;
      return $this;
    }

    /**
     * @return string
     */
    public function getSell_to_Contact()
    {
      return $this->Sell_to_Contact;
    }

    /**
     * @param string $Sell_to_Contact
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setSell_to_Contact($Sell_to_Contact)
    {
      $this->Sell_to_Contact = $Sell_to_Contact;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setNo_of_Archived_Versions($No_of_Archived_Versions)
    {
      $this->No_of_Archived_Versions = $No_of_Archived_Versions;
      return $this;
    }

    /**
     * @return string
     */
    public function getTRN_No()
    {
      return $this->TRN_No;
    }

    /**
     * @param string $TRN_No
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setTRN_No($TRN_No)
    {
      $this->TRN_No = $TRN_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getCustomer_Disc_Group()
    {
      return $this->Customer_Disc_Group;
    }

    /**
     * @param string $Customer_Disc_Group
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setCustomer_Disc_Group($Customer_Disc_Group)
    {
      $this->Customer_Disc_Group = $Customer_Disc_Group;
      return $this;
    }

    /**
     * @return string
     */
    public function getE_Mail()
    {
      return $this->E_Mail;
    }

    /**
     * @param string $E_Mail
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setE_Mail($E_Mail)
    {
      $this->E_Mail = $E_Mail;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setStructure($Structure)
    {
      $this->Structure = $Structure;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setApplies_to_ID($Applies_to_ID)
    {
      $this->Applies_to_ID = $Applies_to_ID;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setDocument_Date($Document_Date)
    {
      $this->Document_Date = $Document_Date;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setDue_Date($Due_Date)
    {
      $this->Due_Date = $Due_Date;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setPromised_Delivery_Date($Promised_Delivery_Date)
    {
      $this->Promised_Delivery_Date = $Promised_Delivery_Date;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setQuote_No($Quote_No)
    {
      $this->Quote_No = $Quote_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getExternal_Document_No()
    {
      return $this->External_Document_No;
    }

    /**
     * @param string $External_Document_No
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setExternal_Document_No($External_Document_No)
    {
      $this->External_Document_No = $External_Document_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getSalesperson_Code()
    {
      return $this->Salesperson_Code;
    }

    /**
     * @param string $Salesperson_Code
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setSalesperson_Code($Salesperson_Code)
    {
      $this->Salesperson_Code = $Salesperson_Code;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setLocation_Code($Location_Code)
    {
      $this->Location_Code = $Location_Code;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setSend_for_approval($Send_for_approval)
    {
      $this->Send_for_approval = $Send_for_approval;
      return $this;
    }

    /**
     * @return string
     */
    public function getCampaign_No()
    {
      return $this->Campaign_No;
    }

    /**
     * @param string $Campaign_No
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setCampaign_No($Campaign_No)
    {
      $this->Campaign_No = $Campaign_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getOpportunity_No()
    {
      return $this->Opportunity_No;
    }

    /**
     * @param string $Opportunity_No
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setOpportunity_No($Opportunity_No)
    {
      $this->Opportunity_No = $Opportunity_No;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setStatus($Status)
    {
      $this->Status = $Status;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getOrder_On_Credit_days_Hold()
    {
      return $this->Order_On_Credit_days_Hold;
    }

    /**
     * @param boolean $Order_On_Credit_days_Hold
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setOrder_On_Credit_days_Hold($Order_On_Credit_days_Hold)
    {
      $this->Order_On_Credit_days_Hold = $Order_On_Credit_days_Hold;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getOrder_On_Credit_Hold()
    {
      return $this->Order_On_Credit_Hold;
    }

    /**
     * @param boolean $Order_On_Credit_Hold
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setOrder_On_Credit_Hold($Order_On_Credit_Hold)
    {
      $this->Order_On_Credit_Hold = $Order_On_Credit_Hold;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getPrice_Hold_Flag()
    {
      return $this->Price_Hold_Flag;
    }

    /**
     * @param boolean $Price_Hold_Flag
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setPrice_Hold_Flag($Price_Hold_Flag)
    {
      $this->Price_Hold_Flag = $Price_Hold_Flag;
      return $this;
    }

    /**
     * @return Rejected
     */
    public function getRejected()
    {
      return $this->Rejected;
    }

    /**
     * @param Rejected $Rejected
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setRejected($Rejected)
    {
      $this->Rejected = $Rejected;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getOrder_On_Over_Due_Hold()
    {
      return $this->Order_On_Over_Due_Hold;
    }

    /**
     * @param boolean $Order_On_Over_Due_Hold
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setOrder_On_Over_Due_Hold($Order_On_Over_Due_Hold)
    {
      $this->Order_On_Over_Due_Hold = $Order_On_Over_Due_Hold;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getOver_Due_Hold_Mail_Sent()
    {
      return $this->Over_Due_Hold_Mail_Sent;
    }

    /**
     * @param boolean $Over_Due_Hold_Mail_Sent
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setOver_Due_Hold_Mail_Sent($Over_Due_Hold_Mail_Sent)
    {
      $this->Over_Due_Hold_Mail_Sent = $Over_Due_Hold_Mail_Sent;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setPosting_No_Series($Posting_No_Series)
    {
      $this->Posting_No_Series = $Posting_No_Series;
      return $this;
    }

    /**
     * @return string
     */
    public function getGST_No()
    {
      return $this->GST_No;
    }

    /**
     * @param string $GST_No
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setGST_No($GST_No)
    {
      $this->GST_No = $GST_No;
      return $this;
    }

    /**
     * @return GST_Customer_Type
     */
    public function getGST_Customer_Type()
    {
      return $this->GST_Customer_Type;
    }

    /**
     * @param GST_Customer_Type $GST_Customer_Type
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setGST_Customer_Type($GST_Customer_Type)
    {
      $this->GST_Customer_Type = $GST_Customer_Type;
      return $this;
    }

    /**
     * @return string
     */
    public function getGST_Bill_to_State_Code()
    {
      return $this->GST_Bill_to_State_Code;
    }

    /**
     * @param string $GST_Bill_to_State_Code
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setGST_Bill_to_State_Code($GST_Bill_to_State_Code)
    {
      $this->GST_Bill_to_State_Code = $GST_Bill_to_State_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getGST_Ship_to_State_Code()
    {
      return $this->GST_Ship_to_State_Code;
    }

    /**
     * @param string $GST_Ship_to_State_Code
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setGST_Ship_to_State_Code($GST_Ship_to_State_Code)
    {
      $this->GST_Ship_to_State_Code = $GST_Ship_to_State_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getLocation_State_Code()
    {
      return $this->Location_State_Code;
    }

    /**
     * @param string $Location_State_Code
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setLocation_State_Code($Location_State_Code)
    {
      $this->Location_State_Code = $Location_State_Code;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setInvoice_Type($Invoice_Type)
    {
      $this->Invoice_Type = $Invoice_Type;
      return $this;
    }

    /**
     * @return string
     */
    public function getLR_RR_No()
    {
      return $this->LR_RR_No;
    }

    /**
     * @param string $LR_RR_No
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setLR_RR_No($LR_RR_No)
    {
      $this->LR_RR_No = $LR_RR_No;
      return $this;
    }

    /**
     * @return date
     */
    public function getLR_RR_Date()
    {
      return $this->LR_RR_Date;
    }

    /**
     * @param date $LR_RR_Date
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setLR_RR_Date($LR_RR_Date)
    {
      $this->LR_RR_Date = $LR_RR_Date;
      return $this;
    }

    /**
     * @return string
     */
    public function getVehicle_No()
    {
      return $this->Vehicle_No;
    }

    /**
     * @param string $Vehicle_No
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setVehicle_No($Vehicle_No)
    {
      $this->Vehicle_No = $Vehicle_No;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setNature_of_Supply($Nature_of_Supply)
    {
      $this->Nature_of_Supply = $Nature_of_Supply;
      return $this;
    }

    /**
     * @return float
     */
    public function getAmount()
    {
      return $this->Amount;
    }

    /**
     * @param float $Amount
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setAmount($Amount)
    {
      $this->Amount = $Amount;
      return $this;
    }

    /**
     * @return string
     */
    public function getPosting_No()
    {
      return $this->Posting_No;
    }

    /**
     * @param string $Posting_No
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setPosting_No($Posting_No)
    {
      $this->Posting_No = $Posting_No;
      return $this;
    }

    /**
     * @return float
     */
    public function getAmount_to_Customer()
    {
      return $this->Amount_to_Customer;
    }

    /**
     * @param float $Amount_to_Customer
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setAmount_to_Customer($Amount_to_Customer)
    {
      $this->Amount_to_Customer = $Amount_to_Customer;
      return $this;
    }

    /**
     * @return string
     */
    public function getArea_Code()
    {
      return $this->Area_Code;
    }

    /**
     * @param string $Area_Code
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setArea_Code($Area_Code)
    {
      $this->Area_Code = $Area_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getDepartment_Code()
    {
      return $this->Department_Code;
    }

    /**
     * @param string $Department_Code
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setDepartment_Code($Department_Code)
    {
      $this->Department_Code = $Department_Code;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setDrop_Shipment($Drop_Shipment)
    {
      $this->Drop_Shipment = $Drop_Shipment;
      return $this;
    }

    /**
     * @return string
     */
    public function getBill_to_Customer_No()
    {
      return $this->Bill_to_Customer_No;
    }

    /**
     * @param string $Bill_to_Customer_No
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setBill_to_Customer_No($Bill_to_Customer_No)
    {
      $this->Bill_to_Customer_No = $Bill_to_Customer_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getBill_to_Contact_No()
    {
      return $this->Bill_to_Contact_No;
    }

    /**
     * @param string $Bill_to_Contact_No
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setBill_to_Contact_No($Bill_to_Contact_No)
    {
      $this->Bill_to_Contact_No = $Bill_to_Contact_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getBill_to_Name()
    {
      return $this->Bill_to_Name;
    }

    /**
     * @param string $Bill_to_Name
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setBill_to_Name($Bill_to_Name)
    {
      $this->Bill_to_Name = $Bill_to_Name;
      return $this;
    }

    /**
     * @return string
     */
    public function getBill_to_Address()
    {
      return $this->Bill_to_Address;
    }

    /**
     * @param string $Bill_to_Address
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setBill_to_Address($Bill_to_Address)
    {
      $this->Bill_to_Address = $Bill_to_Address;
      return $this;
    }

    /**
     * @return string
     */
    public function getBill_to_Address_2()
    {
      return $this->Bill_to_Address_2;
    }

    /**
     * @param string $Bill_to_Address_2
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setBill_to_Address_2($Bill_to_Address_2)
    {
      $this->Bill_to_Address_2 = $Bill_to_Address_2;
      return $this;
    }

    /**
     * @return string
     */
    public function getBill_to_Post_Code()
    {
      return $this->Bill_to_Post_Code;
    }

    /**
     * @param string $Bill_to_Post_Code
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setBill_to_Post_Code($Bill_to_Post_Code)
    {
      $this->Bill_to_Post_Code = $Bill_to_Post_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getBill_to_City()
    {
      return $this->Bill_to_City;
    }

    /**
     * @param string $Bill_to_City
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setBill_to_City($Bill_to_City)
    {
      $this->Bill_to_City = $Bill_to_City;
      return $this;
    }

    /**
     * @return string
     */
    public function getBill_to_Contact()
    {
      return $this->Bill_to_Contact;
    }

    /**
     * @param string $Bill_to_Contact
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setBill_to_Contact($Bill_to_Contact)
    {
      $this->Bill_to_Contact = $Bill_to_Contact;
      return $this;
    }

    /**
     * @return string
     */
    public function getCust_No_ORC()
    {
      return $this->Cust_No_ORC;
    }

    /**
     * @param string $Cust_No_ORC
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setCust_No_ORC($Cust_No_ORC)
    {
      $this->Cust_No_ORC = $Cust_No_ORC;
      return $this;
    }

    /**
     * @return float
     */
    public function getAmount_ORC()
    {
      return $this->Amount_ORC;
    }

    /**
     * @param float $Amount_ORC
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setAmount_ORC($Amount_ORC)
    {
      $this->Amount_ORC = $Amount_ORC;
      return $this;
    }

    /**
     * @return float
     */
    public function getCommission_Percent_ORC()
    {
      return $this->Commission_Percent_ORC;
    }

    /**
     * @param float $Commission_Percent_ORC
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setCommission_Percent_ORC($Commission_Percent_ORC)
    {
      $this->Commission_Percent_ORC = $Commission_Percent_ORC;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setPayment_Terms_Code($Payment_Terms_Code)
    {
      $this->Payment_Terms_Code = $Payment_Terms_Code;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setPayment_Method_Code($Payment_Method_Code)
    {
      $this->Payment_Method_Code = $Payment_Method_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getDirect_Debit_Mandate_ID()
    {
      return $this->Direct_Debit_Mandate_ID;
    }

    /**
     * @param string $Direct_Debit_Mandate_ID
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setDirect_Debit_Mandate_ID($Direct_Debit_Mandate_ID)
    {
      $this->Direct_Debit_Mandate_ID = $Direct_Debit_Mandate_ID;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setVAT_Bus_Posting_Group($VAT_Bus_Posting_Group)
    {
      $this->VAT_Bus_Posting_Group = $VAT_Bus_Posting_Group;
      return $this;
    }

    /**
     * @return string
     */
    public function getCredit_Card_No()
    {
      return $this->Credit_Card_No;
    }

    /**
     * @param string $Credit_Card_No
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setCredit_Card_No($Credit_Card_No)
    {
      $this->Credit_Card_No = $Credit_Card_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getGetCreditcardNumber()
    {
      return $this->GetCreditcardNumber;
    }

    /**
     * @param string $GetCreditcardNumber
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setGetCreditcardNumber($GetCreditcardNumber)
    {
      $this->GetCreditcardNumber = $GetCreditcardNumber;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setShip_to_Code($Ship_to_Code)
    {
      $this->Ship_to_Code = $Ship_to_Code;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setShip_to_Post_Code($Ship_to_Post_Code)
    {
      $this->Ship_to_Post_Code = $Ship_to_Post_Code;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setShip_to_City($Ship_to_City)
    {
      $this->Ship_to_City = $Ship_to_City;
      return $this;
    }

    /**
     * @return string
     */
    public function getState()
    {
      return $this->State;
    }

    /**
     * @param string $State
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setState($State)
    {
      $this->State = $State;
      return $this;
    }

    /**
     * @return string
     */
    public function getTax_Area_Code()
    {
      return $this->Tax_Area_Code;
    }

    /**
     * @param string $Tax_Area_Code
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setTax_Area_Code($Tax_Area_Code)
    {
      $this->Tax_Area_Code = $Tax_Area_Code;
      return $this;
    }

    /**
     * @return float
     */
    public function getGST_Rounding_Precision()
    {
      return $this->GST_Rounding_Precision;
    }

    /**
     * @param float $GST_Rounding_Precision
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setGST_Rounding_Precision($GST_Rounding_Precision)
    {
      $this->GST_Rounding_Precision = $GST_Rounding_Precision;
      return $this;
    }

    /**
     * @return GST_Rounding_Type
     */
    public function getGST_Rounding_Type()
    {
      return $this->GST_Rounding_Type;
    }

    /**
     * @param GST_Rounding_Type $GST_Rounding_Type
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setGST_Rounding_Type($GST_Rounding_Type)
    {
      $this->GST_Rounding_Type = $GST_Rounding_Type;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setTax_Liable($Tax_Liable)
    {
      $this->Tax_Liable = $Tax_Liable;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setShip_to_Contact($Ship_to_Contact)
    {
      $this->Ship_to_Contact = $Ship_to_Contact;
      return $this;
    }

    /**
     * @return string
     */
    public function getShip_to_Country_Region_Code()
    {
      return $this->Ship_to_Country_Region_Code;
    }

    /**
     * @param string $Ship_to_Country_Region_Code
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setShip_to_Country_Region_Code($Ship_to_Country_Region_Code)
    {
      $this->Ship_to_Country_Region_Code = $Ship_to_Country_Region_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getShip_to_County()
    {
      return $this->Ship_to_County;
    }

    /**
     * @param string $Ship_to_County
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setShip_to_County($Ship_to_County)
    {
      $this->Ship_to_County = $Ship_to_County;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setOutbound_Whse_Handling_Time($Outbound_Whse_Handling_Time)
    {
      $this->Outbound_Whse_Handling_Time = $Outbound_Whse_Handling_Time;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setShipment_Method_Code($Shipment_Method_Code)
    {
      $this->Shipment_Method_Code = $Shipment_Method_Code;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setShipping_Time($Shipping_Time)
    {
      $this->Shipping_Time = $Shipping_Time;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getLate_Order_Shipping()
    {
      return $this->Late_Order_Shipping;
    }

    /**
     * @param boolean $Late_Order_Shipping
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setLate_Order_Shipping($Late_Order_Shipping)
    {
      $this->Late_Order_Shipping = $Late_Order_Shipping;
      return $this;
    }

    /**
     * @return string
     */
    public function getPackage_Tracking_No()
    {
      return $this->Package_Tracking_No;
    }

    /**
     * @param string $Package_Tracking_No
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setPackage_Tracking_No($Package_Tracking_No)
    {
      $this->Package_Tracking_No = $Package_Tracking_No;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setShipment_Date($Shipment_Date)
    {
      $this->Shipment_Date = $Shipment_Date;
      return $this;
    }

    /**
     * @return Shipping_Advice
     */
    public function getShipping_Advice()
    {
      return $this->Shipping_Advice;
    }

    /**
     * @param Shipping_Advice $Shipping_Advice
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setShipping_Advice($Shipping_Advice)
    {
      $this->Shipping_Advice = $Shipping_Advice;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setCurrency_Code($Currency_Code)
    {
      $this->Currency_Code = $Currency_Code;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getEU_3_Party_Trade()
    {
      return $this->EU_3_Party_Trade;
    }

    /**
     * @param boolean $EU_3_Party_Trade
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setEU_3_Party_Trade($EU_3_Party_Trade)
    {
      $this->EU_3_Party_Trade = $EU_3_Party_Trade;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setTransaction_Specification($Transaction_Specification)
    {
      $this->Transaction_Specification = $Transaction_Specification;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setTransport_Method($Transport_Method)
    {
      $this->Transport_Method = $Transport_Method;
      return $this;
    }

    /**
     * @return string
     */
    public function getExit_Point()
    {
      return $this->Exit_Point;
    }

    /**
     * @param string $Exit_Point
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setExit_Point($Exit_Point)
    {
      $this->Exit_Point = $Exit_Point;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setPrepmt_Pmt_Discount_Date($Prepmt_Pmt_Discount_Date)
    {
      $this->Prepmt_Pmt_Discount_Date = $Prepmt_Pmt_Discount_Date;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setTransit_Document($Transit_Document)
    {
      $this->Transit_Document = $Transit_Document;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getFree_Supply()
    {
      return $this->Free_Supply;
    }

    /**
     * @param boolean $Free_Supply
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setFree_Supply($Free_Supply)
    {
      $this->Free_Supply = $Free_Supply;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getTDS_Certificate_Receivable()
    {
      return $this->TDS_Certificate_Receivable;
    }

    /**
     * @param boolean $TDS_Certificate_Receivable
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setTDS_Certificate_Receivable($TDS_Certificate_Receivable)
    {
      $this->TDS_Certificate_Receivable = $TDS_Certificate_Receivable;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getCalc_Inv_Discount_Percent()
    {
      return $this->Calc_Inv_Discount_Percent;
    }

    /**
     * @param boolean $Calc_Inv_Discount_Percent
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setCalc_Inv_Discount_Percent($Calc_Inv_Discount_Percent)
    {
      $this->Calc_Inv_Discount_Percent = $Calc_Inv_Discount_Percent;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getExport_or_Deemed_Export()
    {
      return $this->Export_or_Deemed_Export;
    }

    /**
     * @param boolean $Export_or_Deemed_Export
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setExport_or_Deemed_Export($Export_or_Deemed_Export)
    {
      $this->Export_or_Deemed_Export = $Export_or_Deemed_Export;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getVAT_Exempted()
    {
      return $this->VAT_Exempted;
    }

    /**
     * @param boolean $VAT_Exempted
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setVAT_Exempted($VAT_Exempted)
    {
      $this->VAT_Exempted = $VAT_Exempted;
      return $this;
    }

    /**
     * @return Nature_of_Services
     */
    public function getNature_of_Services()
    {
      return $this->Nature_of_Services;
    }

    /**
     * @param Nature_of_Services $Nature_of_Services
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setNature_of_Services($Nature_of_Services)
    {
      $this->Nature_of_Services = $Nature_of_Services;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setTrading($Trading)
    {
      $this->Trading = $Trading;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getST_Pure_Agent()
    {
      return $this->ST_Pure_Agent;
    }

    /**
     * @param boolean $ST_Pure_Agent
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setST_Pure_Agent($ST_Pure_Agent)
    {
      $this->ST_Pure_Agent = $ST_Pure_Agent;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getRe_Dispatch()
    {
      return $this->Re_Dispatch;
    }

    /**
     * @param boolean $Re_Dispatch
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setRe_Dispatch($Re_Dispatch)
    {
      $this->Re_Dispatch = $Re_Dispatch;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setPoT($PoT)
    {
      $this->PoT = $PoT;
      return $this;
    }

    /**
     * @return string
     */
    public function getReturnOrderNo()
    {
      return $this->ReturnOrderNo;
    }

    /**
     * @param string $ReturnOrderNo
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setReturnOrderNo($ReturnOrderNo)
    {
      $this->ReturnOrderNo = $ReturnOrderNo;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setLC_No($LC_No)
    {
      $this->LC_No = $LC_No;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setForm_Code($Form_Code)
    {
      $this->Form_Code = $Form_Code;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setForm_No($Form_No)
    {
      $this->Form_No = $Form_No;
      return $this;
    }

    /**
     * @return time
     */
    public function getTime_of_Removal()
    {
      return $this->Time_of_Removal;
    }

    /**
     * @param time $Time_of_Removal
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setTime_of_Removal($Time_of_Removal)
    {
      $this->Time_of_Removal = $Time_of_Removal;
      return $this;
    }

    /**
     * @return string
     */
    public function getCallbackUrl()
    {
      return $this->CallbackUrl;
    }

    /**
     * @param string $CallbackUrl
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setCallbackUrl($CallbackUrl)
    {
      $this->CallbackUrl = $CallbackUrl;
      return $this;
    }

    /**
     * @return string
     */
    public function getMode_of_Transport()
    {
      return $this->Mode_of_Transport;
    }

    /**
     * @param string $Mode_of_Transport
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setMode_of_Transport($Mode_of_Transport)
    {
      $this->Mode_of_Transport = $Mode_of_Transport;
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setService_Tax_Rounding_Type($Service_Tax_Rounding_Type)
    {
      $this->Service_Tax_Rounding_Type = $Service_Tax_Rounding_Type;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getGST_Without_Payment_of_Duty()
    {
      return $this->GST_Without_Payment_of_Duty;
    }

    /**
     * @param boolean $GST_Without_Payment_of_Duty
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setGST_Without_Payment_of_Duty($GST_Without_Payment_of_Duty)
    {
      $this->GST_Without_Payment_of_Duty = $GST_Without_Payment_of_Duty;
      return $this;
    }

    /**
     * @return string
     */
    public function getBill_Of_Export_No()
    {
      return $this->Bill_Of_Export_No;
    }

    /**
     * @param string $Bill_Of_Export_No
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setBill_Of_Export_No($Bill_Of_Export_No)
    {
      $this->Bill_Of_Export_No = $Bill_Of_Export_No;
      return $this;
    }

    /**
     * @return date
     */
    public function getBill_Of_Export_Date()
    {
      return $this->Bill_Of_Export_Date;
    }

    /**
     * @param date $Bill_Of_Export_Date
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setBill_Of_Export_Date($Bill_Of_Export_Date)
    {
      $this->Bill_Of_Export_Date = $Bill_Of_Export_Date;
      return $this;
    }

    /**
     * @return string
     */
    public function getE_Commerce_Customer()
    {
      return $this->e_Commerce_Customer;
    }

    /**
     * @param string $e_Commerce_Customer
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setE_Commerce_Customer($e_Commerce_Customer)
    {
      $this->e_Commerce_Customer = $e_Commerce_Customer;
      return $this;
    }

    /**
     * @return string
     */
    public function getUser_ID()
    {
      return $this->User_ID;
    }

    /**
     * @param string $User_ID
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setUser_ID($User_ID)
    {
      $this->User_ID = $User_ID;
      return $this;
    }

    /**
     * @return string
     */
    public function getE_Commerce_Merchant_Id()
    {
      return $this->e_Commerce_Merchant_Id;
    }

    /**
     * @param string $e_Commerce_Merchant_Id
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setE_Commerce_Merchant_Id($e_Commerce_Merchant_Id)
    {
      $this->e_Commerce_Merchant_Id = $e_Commerce_Merchant_Id;
      return $this;
    }

    /**
     * @return Sales_Order_Line_List
     */
    public function getSalesLines()
    {
      return $this->SalesLines;
    }

    /**
     * @param Sales_Order_Line_List $SalesLines
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order
     */
    public function setSalesLines($SalesLines)
    {
      $this->SalesLines = $SalesLines;
      return $this;
    }

}
