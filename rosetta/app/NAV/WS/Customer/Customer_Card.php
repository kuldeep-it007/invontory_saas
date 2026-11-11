<?php

namespace Rosetta\NAV\WS\Customer;

class Customer_Card
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
     * @var string $Name
     */
    protected $Name = null;

    /**
     * @var string $Address
     */
    protected $Address = null;

    /**
     * @var string $Address_2
     */
    protected $Address_2 = null;

    /**
     * @var string $Post_Code
     */
    protected $Post_Code = null;

    /**
     * @var string $City
     */
    protected $City = null;

    /**
     * @var string $Country_Region_Code
     */
    protected $Country_Region_Code = null;

    /**
     * @var string $State_Code
     */
    protected $State_Code = null;

    /**
     * @var string $Phone_No
     */
    protected $Phone_No = null;

    /**
     * @var string $Primary_Contact_No
     */
    protected $Primary_Contact_No = null;

    /**
     * @var string $Customer_Group_For_Credit
     */
    protected $Customer_Group_For_Credit = null;

    /**
     * @var string $Contact
     */
    protected $Contact = null;

    /**
     * @var string $Search_Name
     */
    protected $Search_Name = null;

    /**
     * @var string $Location_Code
     */
    protected $Location_Code = null;

    /**
     * @var float $Balance_LCY
     */
    protected $Balance_LCY = null;

    /**
     * @var float $Credit_Limit_LCY
     */
    protected $Credit_Limit_LCY = null;

    /**
     * @var string $Salesperson_Code
     */
    protected $Salesperson_Code = null;

    /**
     * @var string $Responsibility_Center
     */
    protected $Responsibility_Center = null;

    /**
     * @var string $Service_Zone_Code
     */
    protected $Service_Zone_Code = null;

    /**
     * @var Blocked $Blocked
     */
    protected $Blocked = null;

    /**
     * @var string $Territory_Code
     */
    protected $Territory_Code = null;

    /**
     * @var date $Last_Date_Modified
     */
    protected $Last_Date_Modified = null;

    /**
     * @var string $Fax_No
     */
    protected $Fax_No = null;

    /**
     * @var string $E_Mail
     */
    protected $E_Mail = null;

    /**
     * @var string $Home_Page
     */
    protected $Home_Page = null;

    /**
     * @var string $Distributor_Code
     */
    protected $Distributor_Code = null;

    /**
     * @var boolean $Franchisee
     */
    protected $Franchisee = null;

    /**
     * @var string $IC_Partner_Code
     */
    protected $IC_Partner_Code = null;

    /**
     * @var string $Document_Sending_Profile
     */
    protected $Document_Sending_Profile = null;

    /**
     * @var string $Form_Code
     */
    protected $Form_Code = null;

    /**
     * @var boolean $Tax_Liable
     */
    protected $Tax_Liable = null;

    /**
     * @var string $Structure
     */
    protected $Structure = null;

    /**
     * @var string $Gen_Bus_Posting_Group
     */
    protected $Gen_Bus_Posting_Group = null;

    /**
     * @var string $VAT_Bus_Posting_Group
     */
    protected $VAT_Bus_Posting_Group = null;

    /**
     * @var string $Customer_Posting_Group
     */
    protected $Customer_Posting_Group = null;

    /**
     * @var string $Customer_Price_Group
     */
    protected $Customer_Price_Group = null;

    /**
     * @var string $Customer_Disc_Group
     */
    protected $Customer_Disc_Group = null;

    /**
     * @var string $Bill_to_Customer_No
     */
    protected $Bill_to_Customer_No = null;

    /**
     * @var string $VAT_Registration_No
     */
    protected $VAT_Registration_No = null;

    /**
     * @var string $GLN
     */
    protected $GLN = null;

    /**
     * @var int $Invoice_Copies
     */
    protected $Invoice_Copies = null;

    /**
     * @var string $Invoice_Disc_Code
     */
    protected $Invoice_Disc_Code = null;

    /**
     * @var Copy_Sell_to_Addr_to_Qte_From $Copy_Sell_to_Addr_to_Qte_From
     */
    protected $Copy_Sell_to_Addr_to_Qte_From = null;

    /**
     * @var string $Excise_Bus_Posting_Group
     */
    protected $Excise_Bus_Posting_Group = null;

    /**
     * @var boolean $Allow_Line_Disc
     */
    protected $Allow_Line_Disc = null;

    /**
     * @var boolean $Prices_Including_VAT
     */
    protected $Prices_Including_VAT = null;

    /**
     * @var float $Prepayment_Percent
     */
    protected $Prepayment_Percent = null;

    /**
     * @var Application_Method $Application_Method
     */
    protected $Application_Method = null;

    /**
     * @var Partner_Type $Partner_Type
     */
    protected $Partner_Type = null;

    /**
     * @var string $Payment_Terms_Code
     */
    protected $Payment_Terms_Code = null;

    /**
     * @var string $Payment_Method_Code
     */
    protected $Payment_Method_Code = null;

    /**
     * @var string $Reminder_Terms_Code
     */
    protected $Reminder_Terms_Code = null;

    /**
     * @var string $Fin_Charge_Terms_Code
     */
    protected $Fin_Charge_Terms_Code = null;

    /**
     * @var string $Cash_Flow_Payment_Terms_Code
     */
    protected $Cash_Flow_Payment_Terms_Code = null;

    /**
     * @var boolean $Print_Statements
     */
    protected $Print_Statements = null;

    /**
     * @var int $Last_Statement_No
     */
    protected $Last_Statement_No = null;

    /**
     * @var boolean $Block_Payment_Tolerance
     */
    protected $Block_Payment_Tolerance = null;

    /**
     * @var string $Preferred_Bank_Account
     */
    protected $Preferred_Bank_Account = null;

    /**
     * @var boolean $Combine_Shipments
     */
    protected $Combine_Shipments = null;

    /**
     * @var Reserve $Reserve
     */
    protected $Reserve = null;

    /**
     * @var Shipping_Advice $Shipping_Advice
     */
    protected $Shipping_Advice = null;

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
     * @var string $Base_Calendar_Code
     */
    protected $Base_Calendar_Code = null;

    /**
     * @var string $Customized_Calendar
     */
    protected $Customized_Calendar = null;

    /**
     * @var string $Currency_Code
     */
    protected $Currency_Code = null;

    /**
     * @var string $Language_Code
     */
    protected $Language_Code = null;

    /**
     * @var string $L_S_T_No
     */
    protected $L_S_T_No = null;

    /**
     * @var string $C_S_T_No
     */
    protected $C_S_T_No = null;

    /**
     * @var string $E_C_C_No
     */
    protected $E_C_C_No = null;

    /**
     * @var string $Range
     */
    protected $Range = null;

    /**
     * @var string $Collectorate
     */
    protected $Collectorate = null;

    /**
     * @var GST_Customer_Type $GST_Customer_Type
     */
    protected $GST_Customer_Type = null;

    /**
     * @var GST_Registration_Type $GST_Registration_Type
     */
    protected $GST_Registration_Type = null;

    /**
     * @var string $GST_Registration_No
     */
    protected $GST_Registration_No = null;

    /**
     * @var boolean $e_Commerce_Operator
     */
    protected $e_Commerce_Operator = null;

    /**
     * @var string $P_A_N_No
     */
    protected $P_A_N_No = null;

    /**
     * @var P_A_N_Status $P_A_N_Status
     */
    protected $P_A_N_Status = null;

    /**
     * @var string $P_A_N_Reference_No
     */
    protected $P_A_N_Reference_No = null;

    /**
     * @var string $T_I_N_No
     */
    protected $T_I_N_No = null;

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
     * @var string $Service_Entity_Type
     */
    protected $Service_Entity_Type = null;

    
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
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
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
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setNo($No)
    {
      $this->No = $No;
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
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setName($Name)
    {
      $this->Name = $Name;
      return $this;
    }

    /**
     * @return string
     */
    public function getAddress()
    {
      return $this->Address;
    }

    /**
     * @param string $Address
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setAddress($Address)
    {
      $this->Address = $Address;
      return $this;
    }

    /**
     * @return string
     */
    public function getAddress_2()
    {
      return $this->Address_2;
    }

    /**
     * @param string $Address_2
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setAddress_2($Address_2)
    {
      $this->Address_2 = $Address_2;
      return $this;
    }

    /**
     * @return string
     */
    public function getPost_Code()
    {
      return $this->Post_Code;
    }

    /**
     * @param string $Post_Code
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setPost_Code($Post_Code)
    {
      $this->Post_Code = $Post_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getCity()
    {
      return $this->City;
    }

    /**
     * @param string $City
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setCity($City)
    {
      $this->City = $City;
      return $this;
    }

    /**
     * @return string
     */
    public function getCountry_Region_Code()
    {
      return $this->Country_Region_Code;
    }

    /**
     * @param string $Country_Region_Code
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setCountry_Region_Code($Country_Region_Code)
    {
      $this->Country_Region_Code = $Country_Region_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getState_Code()
    {
      return $this->State_Code;
    }

    /**
     * @param string $State_Code
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setState_Code($State_Code)
    {
      $this->State_Code = $State_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getPhone_No()
    {
      return $this->Phone_No;
    }

    /**
     * @param string $Phone_No
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setPhone_No($Phone_No)
    {
      $this->Phone_No = $Phone_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getPrimary_Contact_No()
    {
      return $this->Primary_Contact_No;
    }

    /**
     * @param string $Primary_Contact_No
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setPrimary_Contact_No($Primary_Contact_No)
    {
      $this->Primary_Contact_No = $Primary_Contact_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getCustomer_Group_For_Credit()
    {
      return $this->Customer_Group_For_Credit;
    }

    /**
     * @param string $Customer_Group_For_Credit
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setCustomer_Group_For_Credit($Customer_Group_For_Credit)
    {
      $this->Customer_Group_For_Credit = $Customer_Group_For_Credit;
      return $this;
    }

    /**
     * @return string
     */
    public function getContact()
    {
      return $this->Contact;
    }

    /**
     * @param string $Contact
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setContact($Contact)
    {
      $this->Contact = $Contact;
      return $this;
    }

    /**
     * @return string
     */
    public function getSearch_Name()
    {
      return $this->Search_Name;
    }

    /**
     * @param string $Search_Name
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setSearch_Name($Search_Name)
    {
      $this->Search_Name = $Search_Name;
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
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setLocation_Code($Location_Code)
    {
      $this->Location_Code = $Location_Code;
      return $this;
    }

    /**
     * @return float
     */
    public function getBalance_LCY()
    {
      return $this->Balance_LCY;
    }

    /**
     * @param float $Balance_LCY
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setBalance_LCY($Balance_LCY)
    {
      $this->Balance_LCY = $Balance_LCY;
      return $this;
    }

    /**
     * @return float
     */
    public function getCredit_Limit_LCY()
    {
      return $this->Credit_Limit_LCY;
    }

    /**
     * @param float $Credit_Limit_LCY
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setCredit_Limit_LCY($Credit_Limit_LCY)
    {
      $this->Credit_Limit_LCY = $Credit_Limit_LCY;
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
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setSalesperson_Code($Salesperson_Code)
    {
      $this->Salesperson_Code = $Salesperson_Code;
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
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setResponsibility_Center($Responsibility_Center)
    {
      $this->Responsibility_Center = $Responsibility_Center;
      return $this;
    }

    /**
     * @return string
     */
    public function getService_Zone_Code()
    {
      return $this->Service_Zone_Code;
    }

    /**
     * @param string $Service_Zone_Code
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setService_Zone_Code($Service_Zone_Code)
    {
      $this->Service_Zone_Code = $Service_Zone_Code;
      return $this;
    }

    /**
     * @return Blocked
     */
    public function getBlocked()
    {
      return $this->Blocked;
    }

    /**
     * @param Blocked $Blocked
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setBlocked($Blocked)
    {
      $this->Blocked = $Blocked;
      return $this;
    }

    /**
     * @return string
     */
    public function getTerritory_Code()
    {
      return $this->Territory_Code;
    }

    /**
     * @param string $Territory_Code
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setTerritory_Code($Territory_Code)
    {
      $this->Territory_Code = $Territory_Code;
      return $this;
    }

    /**
     * @return date
     */
    public function getLast_Date_Modified()
    {
      return $this->Last_Date_Modified;
    }

    /**
     * @param date $Last_Date_Modified
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setLast_Date_Modified($Last_Date_Modified)
    {
      $this->Last_Date_Modified = $Last_Date_Modified;
      return $this;
    }

    /**
     * @return string
     */
    public function getFax_No()
    {
      return $this->Fax_No;
    }

    /**
     * @param string $Fax_No
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setFax_No($Fax_No)
    {
      $this->Fax_No = $Fax_No;
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
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setE_Mail($E_Mail)
    {
      $this->E_Mail = $E_Mail;
      return $this;
    }

    /**
     * @return string
     */
    public function getHome_Page()
    {
      return $this->Home_Page;
    }

    /**
     * @param string $Home_Page
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setHome_Page($Home_Page)
    {
      $this->Home_Page = $Home_Page;
      return $this;
    }

    /**
     * @return string
     */
    public function getDistributor_Code()
    {
      return $this->Distributor_Code;
    }

    /**
     * @param string $Distributor_Code
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setDistributor_Code($Distributor_Code)
    {
      $this->Distributor_Code = $Distributor_Code;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getFranchisee()
    {
      return $this->Franchisee;
    }

    /**
     * @param boolean $Franchisee
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setFranchisee($Franchisee)
    {
      $this->Franchisee = $Franchisee;
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
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setIC_Partner_Code($IC_Partner_Code)
    {
      $this->IC_Partner_Code = $IC_Partner_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getDocument_Sending_Profile()
    {
      return $this->Document_Sending_Profile;
    }

    /**
     * @param string $Document_Sending_Profile
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setDocument_Sending_Profile($Document_Sending_Profile)
    {
      $this->Document_Sending_Profile = $Document_Sending_Profile;
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
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setForm_Code($Form_Code)
    {
      $this->Form_Code = $Form_Code;
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
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setTax_Liable($Tax_Liable)
    {
      $this->Tax_Liable = $Tax_Liable;
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
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setStructure($Structure)
    {
      $this->Structure = $Structure;
      return $this;
    }

    /**
     * @return string
     */
    public function getGen_Bus_Posting_Group()
    {
      return $this->Gen_Bus_Posting_Group;
    }

    /**
     * @param string $Gen_Bus_Posting_Group
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setGen_Bus_Posting_Group($Gen_Bus_Posting_Group)
    {
      $this->Gen_Bus_Posting_Group = $Gen_Bus_Posting_Group;
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
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setVAT_Bus_Posting_Group($VAT_Bus_Posting_Group)
    {
      $this->VAT_Bus_Posting_Group = $VAT_Bus_Posting_Group;
      return $this;
    }

    /**
     * @return string
     */
    public function getCustomer_Posting_Group()
    {
      return $this->Customer_Posting_Group;
    }

    /**
     * @param string $Customer_Posting_Group
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setCustomer_Posting_Group($Customer_Posting_Group)
    {
      $this->Customer_Posting_Group = $Customer_Posting_Group;
      return $this;
    }

    /**
     * @return string
     */
    public function getCustomer_Price_Group()
    {
      return $this->Customer_Price_Group;
    }

    /**
     * @param string $Customer_Price_Group
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setCustomer_Price_Group($Customer_Price_Group)
    {
      $this->Customer_Price_Group = $Customer_Price_Group;
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
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setCustomer_Disc_Group($Customer_Disc_Group)
    {
      $this->Customer_Disc_Group = $Customer_Disc_Group;
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
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setBill_to_Customer_No($Bill_to_Customer_No)
    {
      $this->Bill_to_Customer_No = $Bill_to_Customer_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getVAT_Registration_No()
    {
      return $this->VAT_Registration_No;
    }

    /**
     * @param string $VAT_Registration_No
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setVAT_Registration_No($VAT_Registration_No)
    {
      $this->VAT_Registration_No = $VAT_Registration_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getGLN()
    {
      return $this->GLN;
    }

    /**
     * @param string $GLN
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setGLN($GLN)
    {
      $this->GLN = $GLN;
      return $this;
    }

    /**
     * @return int
     */
    public function getInvoice_Copies()
    {
      return $this->Invoice_Copies;
    }

    /**
     * @param int $Invoice_Copies
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setInvoice_Copies($Invoice_Copies)
    {
      $this->Invoice_Copies = $Invoice_Copies;
      return $this;
    }

    /**
     * @return string
     */
    public function getInvoice_Disc_Code()
    {
      return $this->Invoice_Disc_Code;
    }

    /**
     * @param string $Invoice_Disc_Code
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setInvoice_Disc_Code($Invoice_Disc_Code)
    {
      $this->Invoice_Disc_Code = $Invoice_Disc_Code;
      return $this;
    }

    /**
     * @return Copy_Sell_to_Addr_to_Qte_From
     */
    public function getCopy_Sell_to_Addr_to_Qte_From()
    {
      return $this->Copy_Sell_to_Addr_to_Qte_From;
    }

    /**
     * @param Copy_Sell_to_Addr_to_Qte_From $Copy_Sell_to_Addr_to_Qte_From
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setCopy_Sell_to_Addr_to_Qte_From($Copy_Sell_to_Addr_to_Qte_From)
    {
      $this->Copy_Sell_to_Addr_to_Qte_From = $Copy_Sell_to_Addr_to_Qte_From;
      return $this;
    }

    /**
     * @return string
     */
    public function getExcise_Bus_Posting_Group()
    {
      return $this->Excise_Bus_Posting_Group;
    }

    /**
     * @param string $Excise_Bus_Posting_Group
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setExcise_Bus_Posting_Group($Excise_Bus_Posting_Group)
    {
      $this->Excise_Bus_Posting_Group = $Excise_Bus_Posting_Group;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getAllow_Line_Disc()
    {
      return $this->Allow_Line_Disc;
    }

    /**
     * @param boolean $Allow_Line_Disc
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setAllow_Line_Disc($Allow_Line_Disc)
    {
      $this->Allow_Line_Disc = $Allow_Line_Disc;
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
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setPrices_Including_VAT($Prices_Including_VAT)
    {
      $this->Prices_Including_VAT = $Prices_Including_VAT;
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
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setPrepayment_Percent($Prepayment_Percent)
    {
      $this->Prepayment_Percent = $Prepayment_Percent;
      return $this;
    }

    /**
     * @return Application_Method
     */
    public function getApplication_Method()
    {
      return $this->Application_Method;
    }

    /**
     * @param Application_Method $Application_Method
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setApplication_Method($Application_Method)
    {
      $this->Application_Method = $Application_Method;
      return $this;
    }

    /**
     * @return Partner_Type
     */
    public function getPartner_Type()
    {
      return $this->Partner_Type;
    }

    /**
     * @param Partner_Type $Partner_Type
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setPartner_Type($Partner_Type)
    {
      $this->Partner_Type = $Partner_Type;
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
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setPayment_Terms_Code($Payment_Terms_Code)
    {
      $this->Payment_Terms_Code = $Payment_Terms_Code;
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
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setPayment_Method_Code($Payment_Method_Code)
    {
      $this->Payment_Method_Code = $Payment_Method_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getReminder_Terms_Code()
    {
      return $this->Reminder_Terms_Code;
    }

    /**
     * @param string $Reminder_Terms_Code
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setReminder_Terms_Code($Reminder_Terms_Code)
    {
      $this->Reminder_Terms_Code = $Reminder_Terms_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getFin_Charge_Terms_Code()
    {
      return $this->Fin_Charge_Terms_Code;
    }

    /**
     * @param string $Fin_Charge_Terms_Code
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setFin_Charge_Terms_Code($Fin_Charge_Terms_Code)
    {
      $this->Fin_Charge_Terms_Code = $Fin_Charge_Terms_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getCash_Flow_Payment_Terms_Code()
    {
      return $this->Cash_Flow_Payment_Terms_Code;
    }

    /**
     * @param string $Cash_Flow_Payment_Terms_Code
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setCash_Flow_Payment_Terms_Code($Cash_Flow_Payment_Terms_Code)
    {
      $this->Cash_Flow_Payment_Terms_Code = $Cash_Flow_Payment_Terms_Code;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getPrint_Statements()
    {
      return $this->Print_Statements;
    }

    /**
     * @param boolean $Print_Statements
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setPrint_Statements($Print_Statements)
    {
      $this->Print_Statements = $Print_Statements;
      return $this;
    }

    /**
     * @return int
     */
    public function getLast_Statement_No()
    {
      return $this->Last_Statement_No;
    }

    /**
     * @param int $Last_Statement_No
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setLast_Statement_No($Last_Statement_No)
    {
      $this->Last_Statement_No = $Last_Statement_No;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getBlock_Payment_Tolerance()
    {
      return $this->Block_Payment_Tolerance;
    }

    /**
     * @param boolean $Block_Payment_Tolerance
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setBlock_Payment_Tolerance($Block_Payment_Tolerance)
    {
      $this->Block_Payment_Tolerance = $Block_Payment_Tolerance;
      return $this;
    }

    /**
     * @return string
     */
    public function getPreferred_Bank_Account()
    {
      return $this->Preferred_Bank_Account;
    }

    /**
     * @param string $Preferred_Bank_Account
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setPreferred_Bank_Account($Preferred_Bank_Account)
    {
      $this->Preferred_Bank_Account = $Preferred_Bank_Account;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getCombine_Shipments()
    {
      return $this->Combine_Shipments;
    }

    /**
     * @param boolean $Combine_Shipments
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setCombine_Shipments($Combine_Shipments)
    {
      $this->Combine_Shipments = $Combine_Shipments;
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
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setReserve($Reserve)
    {
      $this->Reserve = $Reserve;
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
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setShipping_Advice($Shipping_Advice)
    {
      $this->Shipping_Advice = $Shipping_Advice;
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
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
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
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
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
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
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
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setShipping_Time($Shipping_Time)
    {
      $this->Shipping_Time = $Shipping_Time;
      return $this;
    }

    /**
     * @return string
     */
    public function getBase_Calendar_Code()
    {
      return $this->Base_Calendar_Code;
    }

    /**
     * @param string $Base_Calendar_Code
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setBase_Calendar_Code($Base_Calendar_Code)
    {
      $this->Base_Calendar_Code = $Base_Calendar_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getCustomized_Calendar()
    {
      return $this->Customized_Calendar;
    }

    /**
     * @param string $Customized_Calendar
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setCustomized_Calendar($Customized_Calendar)
    {
      $this->Customized_Calendar = $Customized_Calendar;
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
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setCurrency_Code($Currency_Code)
    {
      $this->Currency_Code = $Currency_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getLanguage_Code()
    {
      return $this->Language_Code;
    }

    /**
     * @param string $Language_Code
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setLanguage_Code($Language_Code)
    {
      $this->Language_Code = $Language_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getL_S_T_No()
    {
      return $this->L_S_T_No;
    }

    /**
     * @param string $L_S_T_No
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setL_S_T_No($L_S_T_No)
    {
      $this->L_S_T_No = $L_S_T_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getC_S_T_No()
    {
      return $this->C_S_T_No;
    }

    /**
     * @param string $C_S_T_No
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setC_S_T_No($C_S_T_No)
    {
      $this->C_S_T_No = $C_S_T_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getE_C_C_No()
    {
      return $this->E_C_C_No;
    }

    /**
     * @param string $E_C_C_No
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setE_C_C_No($E_C_C_No)
    {
      $this->E_C_C_No = $E_C_C_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getRange()
    {
      return $this->Range;
    }

    /**
     * @param string $Range
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setRange($Range)
    {
      $this->Range = $Range;
      return $this;
    }

    /**
     * @return string
     */
    public function getCollectorate()
    {
      return $this->Collectorate;
    }

    /**
     * @param string $Collectorate
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setCollectorate($Collectorate)
    {
      $this->Collectorate = $Collectorate;
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
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setGST_Customer_Type($GST_Customer_Type)
    {
      $this->GST_Customer_Type = $GST_Customer_Type;
      return $this;
    }

    /**
     * @return GST_Registration_Type
     */
    public function getGST_Registration_Type()
    {
      return $this->GST_Registration_Type;
    }

    /**
     * @param GST_Registration_Type $GST_Registration_Type
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setGST_Registration_Type($GST_Registration_Type)
    {
      $this->GST_Registration_Type = $GST_Registration_Type;
      return $this;
    }

    /**
     * @return string
     */
    public function getGST_Registration_No()
    {
      return $this->GST_Registration_No;
    }

    /**
     * @param string $GST_Registration_No
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setGST_Registration_No($GST_Registration_No)
    {
      $this->GST_Registration_No = $GST_Registration_No;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getE_Commerce_Operator()
    {
      return $this->e_Commerce_Operator;
    }

    /**
     * @param boolean $e_Commerce_Operator
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setE_Commerce_Operator($e_Commerce_Operator)
    {
      $this->e_Commerce_Operator = $e_Commerce_Operator;
      return $this;
    }

    /**
     * @return string
     */
    public function getP_A_N_No()
    {
      return $this->P_A_N_No;
    }

    /**
     * @param string $P_A_N_No
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setP_A_N_No($P_A_N_No)
    {
      $this->P_A_N_No = $P_A_N_No;
      return $this;
    }

    /**
     * @return P_A_N_Status
     */
    public function getP_A_N_Status()
    {
      return $this->P_A_N_Status;
    }

    /**
     * @param P_A_N_Status $P_A_N_Status
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setP_A_N_Status($P_A_N_Status)
    {
      $this->P_A_N_Status = $P_A_N_Status;
      return $this;
    }

    /**
     * @return string
     */
    public function getP_A_N_Reference_No()
    {
      return $this->P_A_N_Reference_No;
    }

    /**
     * @param string $P_A_N_Reference_No
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setP_A_N_Reference_No($P_A_N_Reference_No)
    {
      $this->P_A_N_Reference_No = $P_A_N_Reference_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getT_I_N_No()
    {
      return $this->T_I_N_No;
    }

    /**
     * @param string $T_I_N_No
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setT_I_N_No($T_I_N_No)
    {
      $this->T_I_N_No = $T_I_N_No;
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
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
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
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
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
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setNature_of_Services($Nature_of_Services)
    {
      $this->Nature_of_Services = $Nature_of_Services;
      return $this;
    }

    /**
     * @return string
     */
    public function getService_Entity_Type()
    {
      return $this->Service_Entity_Type;
    }

    /**
     * @param string $Service_Entity_Type
     * @return \Rosetta\NAV\WS\Customer\Customer_Card
     */
    public function setService_Entity_Type($Service_Entity_Type)
    {
      $this->Service_Entity_Type = $Service_Entity_Type;
      return $this;
    }

}
