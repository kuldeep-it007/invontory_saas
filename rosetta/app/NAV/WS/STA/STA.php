<?php

namespace Rosetta\NAV\WS\STA;

class STA
{

    /**
     * @var string $Key
     */
    protected $Key = null;

    /**
     * @var string $Code
     */
    protected $Code = null;

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
     * @var string $State
     */
    protected $State = null;

    /**
     * @var string $Phone_No
     */
    protected $Phone_No = null;

    /**
     * @var string $Contact
     */
    protected $Contact = null;

    /**
     * @var string $Location_Code
     */
    protected $Location_Code = null;

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
     * @var string $Service_Zone_Code
     */
    protected $Service_Zone_Code = null;

    /**
     * @var date $Last_Date_Modified
     */
    protected $Last_Date_Modified = null;

    /**
     * @var boolean $Tax_Liable
     */
    protected $Tax_Liable = null;

    /**
     * @var string $GST_Registration_No
     */
    protected $GST_Registration_No = null;

    /**
     * @var boolean $Consignee
     */
    protected $Consignee = null;

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
     * @var string $Customer_No
     */
    protected $Customer_No = null;

    
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
     * @return \Rosetta\NAV\WS\STA\STA
     */
    public function setKey($Key)
    {
      $this->Key = $Key;
      return $this;
    }

    /**
     * @return string
     */
    public function getCode()
    {
      return $this->Code;
    }

    /**
     * @param string $Code
     * @return \Rosetta\NAV\WS\STA\STA
     */
    public function setCode($Code)
    {
      $this->Code = $Code;
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
     * @return \Rosetta\NAV\WS\STA\STA
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
     * @return \Rosetta\NAV\WS\STA\STA
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
     * @return \Rosetta\NAV\WS\STA\STA
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
     * @return \Rosetta\NAV\WS\STA\STA
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
     * @return \Rosetta\NAV\WS\STA\STA
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
     * @return \Rosetta\NAV\WS\STA\STA
     */
    public function setCountry_Region_Code($Country_Region_Code)
    {
      $this->Country_Region_Code = $Country_Region_Code;
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
     * @return \Rosetta\NAV\WS\STA\STA
     */
    public function setState($State)
    {
      $this->State = $State;
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
     * @return \Rosetta\NAV\WS\STA\STA
     */
    public function setPhone_No($Phone_No)
    {
      $this->Phone_No = $Phone_No;
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
     * @return \Rosetta\NAV\WS\STA\STA
     */
    public function setContact($Contact)
    {
      $this->Contact = $Contact;
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
     * @return \Rosetta\NAV\WS\STA\STA
     */
    public function setLocation_Code($Location_Code)
    {
      $this->Location_Code = $Location_Code;
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
     * @return \Rosetta\NAV\WS\STA\STA
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
     * @return \Rosetta\NAV\WS\STA\STA
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
     * @return \Rosetta\NAV\WS\STA\STA
     */
    public function setShipping_Agent_Service_Code($Shipping_Agent_Service_Code)
    {
      $this->Shipping_Agent_Service_Code = $Shipping_Agent_Service_Code;
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
     * @return \Rosetta\NAV\WS\STA\STA
     */
    public function setService_Zone_Code($Service_Zone_Code)
    {
      $this->Service_Zone_Code = $Service_Zone_Code;
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
     * @return \Rosetta\NAV\WS\STA\STA
     */
    public function setLast_Date_Modified($Last_Date_Modified)
    {
      $this->Last_Date_Modified = $Last_Date_Modified;
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
     * @return \Rosetta\NAV\WS\STA\STA
     */
    public function setTax_Liable($Tax_Liable)
    {
      $this->Tax_Liable = $Tax_Liable;
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
     * @return \Rosetta\NAV\WS\STA\STA
     */
    public function setGST_Registration_No($GST_Registration_No)
    {
      $this->GST_Registration_No = $GST_Registration_No;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getConsignee()
    {
      return $this->Consignee;
    }

    /**
     * @param boolean $Consignee
     * @return \Rosetta\NAV\WS\STA\STA
     */
    public function setConsignee($Consignee)
    {
      $this->Consignee = $Consignee;
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
     * @return \Rosetta\NAV\WS\STA\STA
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
     * @return \Rosetta\NAV\WS\STA\STA
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
     * @return \Rosetta\NAV\WS\STA\STA
     */
    public function setHome_Page($Home_Page)
    {
      $this->Home_Page = $Home_Page;
      return $this;
    }

    /**
     * @return string
     */
    public function getCustomer_No()
    {
      return $this->Customer_No;
    }

    /**
     * @param string $Customer_No
     * @return \Rosetta\NAV\WS\STA\STA
     */
    public function setCustomer_No($Customer_No)
    {
      $this->Customer_No = $Customer_No;
      return $this;
    }

}
