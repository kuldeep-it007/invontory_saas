<?php

namespace Rosetta\NAV\WS\PSI;

class PSI
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
     * @var string $Pre_Assigned_No
     */
    protected $Pre_Assigned_No = null;

    /**
     * @var string $Sell_to_Customer_No
     */
    protected $Sell_to_Customer_No = null;

    /**
     * @var string $Order_No
     */
    protected $Order_No = null;

    /**
     * @var date $Order_Date
     */
    protected $Order_Date = null;

    /**
     * @var string $Sell_to_Customer_Name
     */
    protected $Sell_to_Customer_Name = null;

    /**
     * @var string $Currency_Code
     */
    protected $Currency_Code = null;

    /**
     * @var float $Amount
     */
    protected $Amount = null;

    /**
     * @var float $Amount_Including_VAT
     */
    protected $Amount_Including_VAT = null;

    /**
     * @var string $Sell_to_Post_Code
     */
    protected $Sell_to_Post_Code = null;

    /**
     * @var string $Sell_to_Country_Region_Code
     */
    protected $Sell_to_Country_Region_Code = null;

    /**
     * @var string $Sell_to_Contact
     */
    protected $Sell_to_Contact = null;

    /**
     * @var string $Bill_to_Customer_No
     */
    protected $Bill_to_Customer_No = null;

    /**
     * @var string $Bill_to_Name
     */
    protected $Bill_to_Name = null;

    /**
     * @var string $Bill_to_Post_Code
     */
    protected $Bill_to_Post_Code = null;

    /**
     * @var string $Bill_to_Country_Region_Code
     */
    protected $Bill_to_Country_Region_Code = null;

    /**
     * @var string $Bill_to_Contact
     */
    protected $Bill_to_Contact = null;

    /**
     * @var string $Ship_to_Code
     */
    protected $Ship_to_Code = null;

    /**
     * @var string $Ship_to_Name
     */
    protected $Ship_to_Name = null;

    /**
     * @var string $Ship_to_Post_Code
     */
    protected $Ship_to_Post_Code = null;

    /**
     * @var string $Ship_to_Country_Region_Code
     */
    protected $Ship_to_Country_Region_Code = null;

    /**
     * @var string $Ship_to_Contact
     */
    protected $Ship_to_Contact = null;

    /**
     * @var date $Posting_Date
     */
    protected $Posting_Date = null;

    /**
     * @var string $Salesperson_Code
     */
    protected $Salesperson_Code = null;

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
     * @var int $No_Printed
     */
    protected $No_Printed = null;

    /**
     * @var date $Document_Date
     */
    protected $Document_Date = null;

    /**
     * @var string $Payment_Terms_Code
     */
    protected $Payment_Terms_Code = null;

    /**
     * @var date $Due_Date
     */
    protected $Due_Date = null;

    /**
     * @var float $Payment_Discount_Percent
     */
    protected $Payment_Discount_Percent = null;

    /**
     * @var string $Shipment_Method_Code
     */
    protected $Shipment_Method_Code = null;

    /**
     * @var date $Shipment_Date
     */
    protected $Shipment_Date = null;

    /**
     * @var string $C_Form_No
     */
    protected $C_Form_No = null;

    /**
     * @var date $C_Form_Date
     */
    protected $C_Form_Date = null;

    /**
     * @var float $C_Form_Amount
     */
    protected $C_Form_Amount = null;

    /**
     * @var string $Port_of_Loading
     */
    protected $Port_of_Loading = null;

    /**
     * @var string $Country_of_Origin
     */
    protected $Country_of_Origin = null;

    /**
     * @var string $Final_Destination
     */
    protected $Final_Destination = null;

    /**
     * @var string $Seal_No
     */
    protected $Seal_No = null;

    /**
     * @var string $BL_No
     */
    protected $BL_No = null;

    /**
     * @var Document_Exchange_Status $Document_Exchange_Status
     */
    protected $Document_Exchange_Status = null;

    /**
     * @var Invoice_Type $Invoice_Type
     */
    protected $Invoice_Type = null;

    /**
     * @var boolean $Coupled_to_CRM
     */
    protected $Coupled_to_CRM = null;

    /**
     * @var boolean $Canceled
     */
    protected $Canceled = null;

    
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
     * @return \Rosetta\NAV\WS\PSI\PSI
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
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setNo($No)
    {
      $this->No = $No;
      return $this;
    }

    /**
     * @return string
     */
    public function getPre_Assigned_No()
    {
      return $this->Pre_Assigned_No;
    }

    /**
     * @param string $Pre_Assigned_No
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setPre_Assigned_No($Pre_Assigned_No)
    {
      $this->Pre_Assigned_No = $Pre_Assigned_No;
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
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setSell_to_Customer_No($Sell_to_Customer_No)
    {
      $this->Sell_to_Customer_No = $Sell_to_Customer_No;
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
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setOrder_No($Order_No)
    {
      $this->Order_No = $Order_No;
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
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setOrder_Date($Order_Date)
    {
      $this->Order_Date = $Order_Date;
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
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setSell_to_Customer_Name($Sell_to_Customer_Name)
    {
      $this->Sell_to_Customer_Name = $Sell_to_Customer_Name;
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
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setCurrency_Code($Currency_Code)
    {
      $this->Currency_Code = $Currency_Code;
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
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setAmount($Amount)
    {
      $this->Amount = $Amount;
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
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setAmount_Including_VAT($Amount_Including_VAT)
    {
      $this->Amount_Including_VAT = $Amount_Including_VAT;
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
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setSell_to_Post_Code($Sell_to_Post_Code)
    {
      $this->Sell_to_Post_Code = $Sell_to_Post_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getSell_to_Country_Region_Code()
    {
      return $this->Sell_to_Country_Region_Code;
    }

    /**
     * @param string $Sell_to_Country_Region_Code
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setSell_to_Country_Region_Code($Sell_to_Country_Region_Code)
    {
      $this->Sell_to_Country_Region_Code = $Sell_to_Country_Region_Code;
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
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setSell_to_Contact($Sell_to_Contact)
    {
      $this->Sell_to_Contact = $Sell_to_Contact;
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
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setBill_to_Customer_No($Bill_to_Customer_No)
    {
      $this->Bill_to_Customer_No = $Bill_to_Customer_No;
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
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setBill_to_Name($Bill_to_Name)
    {
      $this->Bill_to_Name = $Bill_to_Name;
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
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setBill_to_Post_Code($Bill_to_Post_Code)
    {
      $this->Bill_to_Post_Code = $Bill_to_Post_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getBill_to_Country_Region_Code()
    {
      return $this->Bill_to_Country_Region_Code;
    }

    /**
     * @param string $Bill_to_Country_Region_Code
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setBill_to_Country_Region_Code($Bill_to_Country_Region_Code)
    {
      $this->Bill_to_Country_Region_Code = $Bill_to_Country_Region_Code;
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
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setBill_to_Contact($Bill_to_Contact)
    {
      $this->Bill_to_Contact = $Bill_to_Contact;
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
     * @return \Rosetta\NAV\WS\PSI\PSI
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
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setShip_to_Name($Ship_to_Name)
    {
      $this->Ship_to_Name = $Ship_to_Name;
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
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setShip_to_Post_Code($Ship_to_Post_Code)
    {
      $this->Ship_to_Post_Code = $Ship_to_Post_Code;
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
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setShip_to_Country_Region_Code($Ship_to_Country_Region_Code)
    {
      $this->Ship_to_Country_Region_Code = $Ship_to_Country_Region_Code;
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
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setShip_to_Contact($Ship_to_Contact)
    {
      $this->Ship_to_Contact = $Ship_to_Contact;
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
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setPosting_Date($Posting_Date)
    {
      $this->Posting_Date = $Posting_Date;
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
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setSalesperson_Code($Salesperson_Code)
    {
      $this->Salesperson_Code = $Salesperson_Code;
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
     * @return \Rosetta\NAV\WS\PSI\PSI
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
     * @return \Rosetta\NAV\WS\PSI\PSI
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
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setLocation_Code($Location_Code)
    {
      $this->Location_Code = $Location_Code;
      return $this;
    }

    /**
     * @return int
     */
    public function getNo_Printed()
    {
      return $this->No_Printed;
    }

    /**
     * @param int $No_Printed
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setNo_Printed($No_Printed)
    {
      $this->No_Printed = $No_Printed;
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
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setDocument_Date($Document_Date)
    {
      $this->Document_Date = $Document_Date;
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
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setPayment_Terms_Code($Payment_Terms_Code)
    {
      $this->Payment_Terms_Code = $Payment_Terms_Code;
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
     * @return \Rosetta\NAV\WS\PSI\PSI
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
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setPayment_Discount_Percent($Payment_Discount_Percent)
    {
      $this->Payment_Discount_Percent = $Payment_Discount_Percent;
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
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setShipment_Method_Code($Shipment_Method_Code)
    {
      $this->Shipment_Method_Code = $Shipment_Method_Code;
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
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setShipment_Date($Shipment_Date)
    {
      $this->Shipment_Date = $Shipment_Date;
      return $this;
    }

    /**
     * @return string
     */
    public function getC_Form_No()
    {
      return $this->C_Form_No;
    }

    /**
     * @param string $C_Form_No
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setC_Form_No($C_Form_No)
    {
      $this->C_Form_No = $C_Form_No;
      return $this;
    }

    /**
     * @return date
     */
    public function getC_Form_Date()
    {
      return $this->C_Form_Date;
    }

    /**
     * @param date $C_Form_Date
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setC_Form_Date($C_Form_Date)
    {
      $this->C_Form_Date = $C_Form_Date;
      return $this;
    }

    /**
     * @return float
     */
    public function getC_Form_Amount()
    {
      return $this->C_Form_Amount;
    }

    /**
     * @param float $C_Form_Amount
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setC_Form_Amount($C_Form_Amount)
    {
      $this->C_Form_Amount = $C_Form_Amount;
      return $this;
    }

    /**
     * @return string
     */
    public function getPort_of_Loading()
    {
      return $this->Port_of_Loading;
    }

    /**
     * @param string $Port_of_Loading
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setPort_of_Loading($Port_of_Loading)
    {
      $this->Port_of_Loading = $Port_of_Loading;
      return $this;
    }

    /**
     * @return string
     */
    public function getCountry_of_Origin()
    {
      return $this->Country_of_Origin;
    }

    /**
     * @param string $Country_of_Origin
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setCountry_of_Origin($Country_of_Origin)
    {
      $this->Country_of_Origin = $Country_of_Origin;
      return $this;
    }

    /**
     * @return string
     */
    public function getFinal_Destination()
    {
      return $this->Final_Destination;
    }

    /**
     * @param string $Final_Destination
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setFinal_Destination($Final_Destination)
    {
      $this->Final_Destination = $Final_Destination;
      return $this;
    }

    /**
     * @return string
     */
    public function getSeal_No()
    {
      return $this->Seal_No;
    }

    /**
     * @param string $Seal_No
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setSeal_No($Seal_No)
    {
      $this->Seal_No = $Seal_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getBL_No()
    {
      return $this->BL_No;
    }

    /**
     * @param string $BL_No
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setBL_No($BL_No)
    {
      $this->BL_No = $BL_No;
      return $this;
    }

    /**
     * @return Document_Exchange_Status
     */
    public function getDocument_Exchange_Status()
    {
      return $this->Document_Exchange_Status;
    }

    /**
     * @param Document_Exchange_Status $Document_Exchange_Status
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setDocument_Exchange_Status($Document_Exchange_Status)
    {
      $this->Document_Exchange_Status = $Document_Exchange_Status;
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
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setInvoice_Type($Invoice_Type)
    {
      $this->Invoice_Type = $Invoice_Type;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getCoupled_to_CRM()
    {
      return $this->Coupled_to_CRM;
    }

    /**
     * @param boolean $Coupled_to_CRM
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setCoupled_to_CRM($Coupled_to_CRM)
    {
      $this->Coupled_to_CRM = $Coupled_to_CRM;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getCanceled()
    {
      return $this->Canceled;
    }

    /**
     * @param boolean $Canceled
     * @return \Rosetta\NAV\WS\PSI\PSI
     */
    public function setCanceled($Canceled)
    {
      $this->Canceled = $Canceled;
      return $this;
    }

}
