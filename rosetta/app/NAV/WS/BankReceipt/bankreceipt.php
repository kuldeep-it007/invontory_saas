<?php

namespace Rosetta\NAV\WS\BankReceipt;

class bankreceipt
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
     * @var date $Document_Date
     */
    protected $Document_Date = null;

    /**
     * @var Document_Type $Document_Type
     */
    protected $Document_Type = null;

    /**
     * @var string $Document_No
     */
    protected $Document_No = null;

    /**
     * @var Payment_Type $Payment_Type
     */
    protected $Payment_Type = null;

    /**
     * @var string $Payment_Purchase_Order
     */
    protected $Payment_Purchase_Order = null;

    /**
     * @var float $Payment_to_Made
     */
    protected $Payment_to_Made = null;

    /**
     * @var boolean $Serv_Tax_on_Advance_Payment
     */
    protected $Serv_Tax_on_Advance_Payment = null;

    /**
     * @var boolean $ST_Pure_Agent
     */
    protected $ST_Pure_Agent = null;

    /**
     * @var float $Service_Tax_Base_Amount_LCY
     */
    protected $Service_Tax_Base_Amount_LCY = null;

    /**
     * @var Service_Tax_Type $Service_Tax_Type
     */
    protected $Service_Tax_Type = null;

    /**
     * @var string $Service_Tax_Group_Code
     */
    protected $Service_Tax_Group_Code = null;

    /**
     * @var string $Service_Tax_Registration_No
     */
    protected $Service_Tax_Registration_No = null;

    /**
     * @var boolean $PoT
     */
    protected $PoT = null;

    /**
     * @var string $External_Document_No
     */
    protected $External_Document_No = null;

    /**
     * @var boolean $TDS_Certificate_Receivable
     */
    protected $TDS_Certificate_Receivable = null;

    /**
     * @var Party_Type $Party_Type
     */
    protected $Party_Type = null;

    /**
     * @var string $Party_Code
     */
    protected $Party_Code = null;

    /**
     * @var Account_Type $Account_Type
     */
    protected $Account_Type = null;

    /**
     * @var string $Account_No
     */
    protected $Account_No = null;

    /**
     * @var string $Description
     */
    protected $Description = null;

    /**
     * @var string $T_C_A_N_No
     */
    protected $T_C_A_N_No = null;

    /**
     * @var string $Assessee_Code
     */
    protected $Assessee_Code = null;

    /**
     * @var string $TCS_Nature_of_Collection
     */
    protected $TCS_Nature_of_Collection = null;

    /**
     * @var float $TDS_TCS_Percent
     */
    protected $TDS_TCS_Percent = null;

    /**
     * @var float $TDS_TCS_Amount
     */
    protected $TDS_TCS_Amount = null;

    /**
     * @var float $TDS_TCS_Amt_Incl_Surcharge
     */
    protected $TDS_TCS_Amt_Incl_Surcharge = null;

    /**
     * @var float $eCESS_on_TDS_TCS_Amount
     */
    protected $eCESS_on_TDS_TCS_Amount = null;

    /**
     * @var float $SHE_Cess_on_TDS_TCS_Amount
     */
    protected $SHE_Cess_on_TDS_TCS_Amount = null;

    /**
     * @var float $Bal_TDS_TCS_Including_SHECESS
     */
    protected $Bal_TDS_TCS_Including_SHECESS = null;

    /**
     * @var float $Total_TDS_TCS_Incl_SHE_CESS
     */
    protected $Total_TDS_TCS_Incl_SHE_CESS = null;

    /**
     * @var string $Salespers_Purch_Code
     */
    protected $Salespers_Purch_Code = null;

    /**
     * @var string $Campaign_No
     */
    protected $Campaign_No = null;

    /**
     * @var string $Currency_Code
     */
    protected $Currency_Code = null;

    /**
     * @var Gen_Posting_Type $Gen_Posting_Type
     */
    protected $Gen_Posting_Type = null;

    /**
     * @var string $Gen_Bus_Posting_Group
     */
    protected $Gen_Bus_Posting_Group = null;

    /**
     * @var string $Gen_Prod_Posting_Group
     */
    protected $Gen_Prod_Posting_Group = null;

    /**
     * @var string $VAT_Bus_Posting_Group
     */
    protected $VAT_Bus_Posting_Group = null;

    /**
     * @var string $VAT_Prod_Posting_Group
     */
    protected $VAT_Prod_Posting_Group = null;

    /**
     * @var float $Amount
     */
    protected $Amount = null;

    /**
     * @var float $Debit_Amount
     */
    protected $Debit_Amount = null;

    /**
     * @var float $Credit_Amount
     */
    protected $Credit_Amount = null;

    /**
     * @var float $VAT_Amount
     */
    protected $VAT_Amount = null;

    /**
     * @var float $VAT_Difference
     */
    protected $VAT_Difference = null;

    /**
     * @var float $Bal_VAT_Amount
     */
    protected $Bal_VAT_Amount = null;

    /**
     * @var float $Bal_VAT_Difference
     */
    protected $Bal_VAT_Difference = null;

    /**
     * @var Bal_Account_Type $Bal_Account_Type
     */
    protected $Bal_Account_Type = null;

    /**
     * @var string $Bal_Account_No
     */
    protected $Bal_Account_No = null;

    /**
     * @var Bal_Gen_Posting_Type $Bal_Gen_Posting_Type
     */
    protected $Bal_Gen_Posting_Type = null;

    /**
     * @var string $Bal_Gen_Bus_Posting_Group
     */
    protected $Bal_Gen_Bus_Posting_Group = null;

    /**
     * @var string $Bal_Gen_Prod_Posting_Group
     */
    protected $Bal_Gen_Prod_Posting_Group = null;

    /**
     * @var string $Bal_VAT_Bus_Posting_Group
     */
    protected $Bal_VAT_Bus_Posting_Group = null;

    /**
     * @var string $Bal_VAT_Prod_Posting_Group
     */
    protected $Bal_VAT_Prod_Posting_Group = null;

    /**
     * @var Nature_of_Supply $Nature_of_Supply
     */
    protected $Nature_of_Supply = null;

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
     * @var GST_Place_of_Supply $GST_Place_of_Supply
     */
    protected $GST_Place_of_Supply = null;

    /**
     * @var string $GST_Bill_to_BuyFrom_State_Code
     */
    protected $GST_Bill_to_BuyFrom_State_Code = null;

    /**
     * @var string $GST_Ship_to_State_Code
     */
    protected $GST_Ship_to_State_Code = null;

    /**
     * @var string $Location_State_Code
     */
    protected $Location_State_Code = null;

    /**
     * @var GST_Customer_Type $GST_Customer_Type
     */
    protected $GST_Customer_Type = null;

    /**
     * @var GST_Vendor_Type $GST_Vendor_Type
     */
    protected $GST_Vendor_Type = null;

    /**
     * @var string $HSN_SAC_Code
     */
    protected $HSN_SAC_Code = null;

    /**
     * @var boolean $GST_on_Advance_Payment
     */
    protected $GST_on_Advance_Payment = null;

    /**
     * @var string $Ship_to_Code
     */
    protected $Ship_to_Code = null;

    /**
     * @var string $Location_Code
     */
    protected $Location_Code = null;

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
     * @var string $Cheque_No
     */
    protected $Cheque_No = null;

    /**
     * @var date $Cheque_Date
     */
    protected $Cheque_Date = null;

    /**
     * @var Applies_to_Doc_Type $Applies_to_Doc_Type
     */
    protected $Applies_to_Doc_Type = null;

    /**
     * @var string $Applies_to_Doc_No
     */
    protected $Applies_to_Doc_No = null;

    /**
     * @var boolean $Include_Serv_Tax_in_TDS_Base
     */
    protected $Include_Serv_Tax_in_TDS_Base = null;

    /**
     * @var Service_Tax_Rounding_Type $Service_Tax_Rounding_Type
     */
    protected $Service_Tax_Rounding_Type = null;

    /**
     * @var float $Service_Tax_Rounding_Precision
     */
    protected $Service_Tax_Rounding_Precision = null;

    /**
     * @var string $Sales_Order_No
     */
    protected $Sales_Order_No = null;

    /**
     * @var string $Applies_to_ID
     */
    protected $Applies_to_ID = null;

    /**
     * @var string $Reason_Code
     */
    protected $Reason_Code = null;

    /**
     * @var boolean $Apply
     */
    protected $Apply = null;

    /**
     * @var string $AccName
     */
    protected $AccName = null;

    /**
     * @var string $BalAccName
     */
    protected $BalAccName = null;

    /**
     * @var float $TotalDebitAmount
     */
    protected $TotalDebitAmount = null;

    /**
     * @var float $Balance
     */
    protected $Balance = null;

    /**
     * @var float $TotalBalance
     */
    protected $TotalBalance = null;

    /**
     * @var float $TotalCreditAmount
     */
    protected $TotalCreditAmount = null;

    
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setPosting_Date($Posting_Date)
    {
      $this->Posting_Date = $Posting_Date;
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setDocument_Date($Document_Date)
    {
      $this->Document_Date = $Document_Date;
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setDocument_No($Document_No)
    {
      $this->Document_No = $Document_No;
      return $this;
    }

    /**
     * @return Payment_Type
     */
    public function getPayment_Type()
    {
      return $this->Payment_Type;
    }

    /**
     * @param Payment_Type $Payment_Type
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setPayment_Type($Payment_Type)
    {
      $this->Payment_Type = $Payment_Type;
      return $this;
    }

    /**
     * @return string
     */
    public function getPayment_Purchase_Order()
    {
      return $this->Payment_Purchase_Order;
    }

    /**
     * @param string $Payment_Purchase_Order
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setPayment_Purchase_Order($Payment_Purchase_Order)
    {
      $this->Payment_Purchase_Order = $Payment_Purchase_Order;
      return $this;
    }

    /**
     * @return float
     */
    public function getPayment_to_Made()
    {
      return $this->Payment_to_Made;
    }

    /**
     * @param float $Payment_to_Made
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setPayment_to_Made($Payment_to_Made)
    {
      $this->Payment_to_Made = $Payment_to_Made;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getServ_Tax_on_Advance_Payment()
    {
      return $this->Serv_Tax_on_Advance_Payment;
    }

    /**
     * @param boolean $Serv_Tax_on_Advance_Payment
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setServ_Tax_on_Advance_Payment($Serv_Tax_on_Advance_Payment)
    {
      $this->Serv_Tax_on_Advance_Payment = $Serv_Tax_on_Advance_Payment;
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setST_Pure_Agent($ST_Pure_Agent)
    {
      $this->ST_Pure_Agent = $ST_Pure_Agent;
      return $this;
    }

    /**
     * @return float
     */
    public function getService_Tax_Base_Amount_LCY()
    {
      return $this->Service_Tax_Base_Amount_LCY;
    }

    /**
     * @param float $Service_Tax_Base_Amount_LCY
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setService_Tax_Base_Amount_LCY($Service_Tax_Base_Amount_LCY)
    {
      $this->Service_Tax_Base_Amount_LCY = $Service_Tax_Base_Amount_LCY;
      return $this;
    }

    /**
     * @return Service_Tax_Type
     */
    public function getService_Tax_Type()
    {
      return $this->Service_Tax_Type;
    }

    /**
     * @param Service_Tax_Type $Service_Tax_Type
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setService_Tax_Type($Service_Tax_Type)
    {
      $this->Service_Tax_Type = $Service_Tax_Type;
      return $this;
    }

    /**
     * @return string
     */
    public function getService_Tax_Group_Code()
    {
      return $this->Service_Tax_Group_Code;
    }

    /**
     * @param string $Service_Tax_Group_Code
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setService_Tax_Group_Code($Service_Tax_Group_Code)
    {
      $this->Service_Tax_Group_Code = $Service_Tax_Group_Code;
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setService_Tax_Registration_No($Service_Tax_Registration_No)
    {
      $this->Service_Tax_Registration_No = $Service_Tax_Registration_No;
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setPoT($PoT)
    {
      $this->PoT = $PoT;
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setExternal_Document_No($External_Document_No)
    {
      $this->External_Document_No = $External_Document_No;
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setTDS_Certificate_Receivable($TDS_Certificate_Receivable)
    {
      $this->TDS_Certificate_Receivable = $TDS_Certificate_Receivable;
      return $this;
    }

    /**
     * @return Party_Type
     */
    public function getParty_Type()
    {
      return $this->Party_Type;
    }

    /**
     * @param Party_Type $Party_Type
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setParty_Type($Party_Type)
    {
      $this->Party_Type = $Party_Type;
      return $this;
    }

    /**
     * @return string
     */
    public function getParty_Code()
    {
      return $this->Party_Code;
    }

    /**
     * @param string $Party_Code
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setParty_Code($Party_Code)
    {
      $this->Party_Code = $Party_Code;
      return $this;
    }

    /**
     * @return Account_Type
     */
    public function getAccount_Type()
    {
      return $this->Account_Type;
    }

    /**
     * @param Account_Type $Account_Type
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setAccount_Type($Account_Type)
    {
      $this->Account_Type = $Account_Type;
      return $this;
    }

    /**
     * @return string
     */
    public function getAccount_No()
    {
      return $this->Account_No;
    }

    /**
     * @param string $Account_No
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setAccount_No($Account_No)
    {
      $this->Account_No = $Account_No;
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setDescription($Description)
    {
      $this->Description = $Description;
      return $this;
    }

    /**
     * @return string
     */
    public function getT_C_A_N_No()
    {
      return $this->T_C_A_N_No;
    }

    /**
     * @param string $T_C_A_N_No
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setT_C_A_N_No($T_C_A_N_No)
    {
      $this->T_C_A_N_No = $T_C_A_N_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getAssessee_Code()
    {
      return $this->Assessee_Code;
    }

    /**
     * @param string $Assessee_Code
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setAssessee_Code($Assessee_Code)
    {
      $this->Assessee_Code = $Assessee_Code;
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setTCS_Nature_of_Collection($TCS_Nature_of_Collection)
    {
      $this->TCS_Nature_of_Collection = $TCS_Nature_of_Collection;
      return $this;
    }

    /**
     * @return float
     */
    public function getTDS_TCS_Percent()
    {
      return $this->TDS_TCS_Percent;
    }

    /**
     * @param float $TDS_TCS_Percent
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setTDS_TCS_Percent($TDS_TCS_Percent)
    {
      $this->TDS_TCS_Percent = $TDS_TCS_Percent;
      return $this;
    }

    /**
     * @return float
     */
    public function getTDS_TCS_Amount()
    {
      return $this->TDS_TCS_Amount;
    }

    /**
     * @param float $TDS_TCS_Amount
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setTDS_TCS_Amount($TDS_TCS_Amount)
    {
      $this->TDS_TCS_Amount = $TDS_TCS_Amount;
      return $this;
    }

    /**
     * @return float
     */
    public function getTDS_TCS_Amt_Incl_Surcharge()
    {
      return $this->TDS_TCS_Amt_Incl_Surcharge;
    }

    /**
     * @param float $TDS_TCS_Amt_Incl_Surcharge
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setTDS_TCS_Amt_Incl_Surcharge($TDS_TCS_Amt_Incl_Surcharge)
    {
      $this->TDS_TCS_Amt_Incl_Surcharge = $TDS_TCS_Amt_Incl_Surcharge;
      return $this;
    }

    /**
     * @return float
     */
    public function getECESS_on_TDS_TCS_Amount()
    {
      return $this->eCESS_on_TDS_TCS_Amount;
    }

    /**
     * @param float $eCESS_on_TDS_TCS_Amount
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setECESS_on_TDS_TCS_Amount($eCESS_on_TDS_TCS_Amount)
    {
      $this->eCESS_on_TDS_TCS_Amount = $eCESS_on_TDS_TCS_Amount;
      return $this;
    }

    /**
     * @return float
     */
    public function getSHE_Cess_on_TDS_TCS_Amount()
    {
      return $this->SHE_Cess_on_TDS_TCS_Amount;
    }

    /**
     * @param float $SHE_Cess_on_TDS_TCS_Amount
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setSHE_Cess_on_TDS_TCS_Amount($SHE_Cess_on_TDS_TCS_Amount)
    {
      $this->SHE_Cess_on_TDS_TCS_Amount = $SHE_Cess_on_TDS_TCS_Amount;
      return $this;
    }

    /**
     * @return float
     */
    public function getBal_TDS_TCS_Including_SHECESS()
    {
      return $this->Bal_TDS_TCS_Including_SHECESS;
    }

    /**
     * @param float $Bal_TDS_TCS_Including_SHECESS
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setBal_TDS_TCS_Including_SHECESS($Bal_TDS_TCS_Including_SHECESS)
    {
      $this->Bal_TDS_TCS_Including_SHECESS = $Bal_TDS_TCS_Including_SHECESS;
      return $this;
    }

    /**
     * @return float
     */
    public function getTotal_TDS_TCS_Incl_SHE_CESS()
    {
      return $this->Total_TDS_TCS_Incl_SHE_CESS;
    }

    /**
     * @param float $Total_TDS_TCS_Incl_SHE_CESS
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setTotal_TDS_TCS_Incl_SHE_CESS($Total_TDS_TCS_Incl_SHE_CESS)
    {
      $this->Total_TDS_TCS_Incl_SHE_CESS = $Total_TDS_TCS_Incl_SHE_CESS;
      return $this;
    }

    /**
     * @return string
     */
    public function getSalespers_Purch_Code()
    {
      return $this->Salespers_Purch_Code;
    }

    /**
     * @param string $Salespers_Purch_Code
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setSalespers_Purch_Code($Salespers_Purch_Code)
    {
      $this->Salespers_Purch_Code = $Salespers_Purch_Code;
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setCampaign_No($Campaign_No)
    {
      $this->Campaign_No = $Campaign_No;
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setCurrency_Code($Currency_Code)
    {
      $this->Currency_Code = $Currency_Code;
      return $this;
    }

    /**
     * @return Gen_Posting_Type
     */
    public function getGen_Posting_Type()
    {
      return $this->Gen_Posting_Type;
    }

    /**
     * @param Gen_Posting_Type $Gen_Posting_Type
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setGen_Posting_Type($Gen_Posting_Type)
    {
      $this->Gen_Posting_Type = $Gen_Posting_Type;
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setGen_Bus_Posting_Group($Gen_Bus_Posting_Group)
    {
      $this->Gen_Bus_Posting_Group = $Gen_Bus_Posting_Group;
      return $this;
    }

    /**
     * @return string
     */
    public function getGen_Prod_Posting_Group()
    {
      return $this->Gen_Prod_Posting_Group;
    }

    /**
     * @param string $Gen_Prod_Posting_Group
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setGen_Prod_Posting_Group($Gen_Prod_Posting_Group)
    {
      $this->Gen_Prod_Posting_Group = $Gen_Prod_Posting_Group;
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setVAT_Bus_Posting_Group($VAT_Bus_Posting_Group)
    {
      $this->VAT_Bus_Posting_Group = $VAT_Bus_Posting_Group;
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setVAT_Prod_Posting_Group($VAT_Prod_Posting_Group)
    {
      $this->VAT_Prod_Posting_Group = $VAT_Prod_Posting_Group;
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setAmount($Amount)
    {
      $this->Amount = $Amount;
      return $this;
    }

    /**
     * @return float
     */
    public function getDebit_Amount()
    {
      return $this->Debit_Amount;
    }

    /**
     * @param float $Debit_Amount
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setDebit_Amount($Debit_Amount)
    {
      $this->Debit_Amount = $Debit_Amount;
      return $this;
    }

    /**
     * @return float
     */
    public function getCredit_Amount()
    {
      return $this->Credit_Amount;
    }

    /**
     * @param float $Credit_Amount
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setCredit_Amount($Credit_Amount)
    {
      $this->Credit_Amount = $Credit_Amount;
      return $this;
    }

    /**
     * @return float
     */
    public function getVAT_Amount()
    {
      return $this->VAT_Amount;
    }

    /**
     * @param float $VAT_Amount
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setVAT_Amount($VAT_Amount)
    {
      $this->VAT_Amount = $VAT_Amount;
      return $this;
    }

    /**
     * @return float
     */
    public function getVAT_Difference()
    {
      return $this->VAT_Difference;
    }

    /**
     * @param float $VAT_Difference
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setVAT_Difference($VAT_Difference)
    {
      $this->VAT_Difference = $VAT_Difference;
      return $this;
    }

    /**
     * @return float
     */
    public function getBal_VAT_Amount()
    {
      return $this->Bal_VAT_Amount;
    }

    /**
     * @param float $Bal_VAT_Amount
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setBal_VAT_Amount($Bal_VAT_Amount)
    {
      $this->Bal_VAT_Amount = $Bal_VAT_Amount;
      return $this;
    }

    /**
     * @return float
     */
    public function getBal_VAT_Difference()
    {
      return $this->Bal_VAT_Difference;
    }

    /**
     * @param float $Bal_VAT_Difference
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setBal_VAT_Difference($Bal_VAT_Difference)
    {
      $this->Bal_VAT_Difference = $Bal_VAT_Difference;
      return $this;
    }

    /**
     * @return Bal_Account_Type
     */
    public function getBal_Account_Type()
    {
      return $this->Bal_Account_Type;
    }

    /**
     * @param Bal_Account_Type $Bal_Account_Type
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setBal_Account_Type($Bal_Account_Type)
    {
      $this->Bal_Account_Type = $Bal_Account_Type;
      return $this;
    }

    /**
     * @return string
     */
    public function getBal_Account_No()
    {
      return $this->Bal_Account_No;
    }

    /**
     * @param string $Bal_Account_No
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setBal_Account_No($Bal_Account_No)
    {
      $this->Bal_Account_No = $Bal_Account_No;
      return $this;
    }

    /**
     * @return Bal_Gen_Posting_Type
     */
    public function getBal_Gen_Posting_Type()
    {
      return $this->Bal_Gen_Posting_Type;
    }

    /**
     * @param Bal_Gen_Posting_Type $Bal_Gen_Posting_Type
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setBal_Gen_Posting_Type($Bal_Gen_Posting_Type)
    {
      $this->Bal_Gen_Posting_Type = $Bal_Gen_Posting_Type;
      return $this;
    }

    /**
     * @return string
     */
    public function getBal_Gen_Bus_Posting_Group()
    {
      return $this->Bal_Gen_Bus_Posting_Group;
    }

    /**
     * @param string $Bal_Gen_Bus_Posting_Group
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setBal_Gen_Bus_Posting_Group($Bal_Gen_Bus_Posting_Group)
    {
      $this->Bal_Gen_Bus_Posting_Group = $Bal_Gen_Bus_Posting_Group;
      return $this;
    }

    /**
     * @return string
     */
    public function getBal_Gen_Prod_Posting_Group()
    {
      return $this->Bal_Gen_Prod_Posting_Group;
    }

    /**
     * @param string $Bal_Gen_Prod_Posting_Group
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setBal_Gen_Prod_Posting_Group($Bal_Gen_Prod_Posting_Group)
    {
      $this->Bal_Gen_Prod_Posting_Group = $Bal_Gen_Prod_Posting_Group;
      return $this;
    }

    /**
     * @return string
     */
    public function getBal_VAT_Bus_Posting_Group()
    {
      return $this->Bal_VAT_Bus_Posting_Group;
    }

    /**
     * @param string $Bal_VAT_Bus_Posting_Group
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setBal_VAT_Bus_Posting_Group($Bal_VAT_Bus_Posting_Group)
    {
      $this->Bal_VAT_Bus_Posting_Group = $Bal_VAT_Bus_Posting_Group;
      return $this;
    }

    /**
     * @return string
     */
    public function getBal_VAT_Prod_Posting_Group()
    {
      return $this->Bal_VAT_Prod_Posting_Group;
    }

    /**
     * @param string $Bal_VAT_Prod_Posting_Group
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setBal_VAT_Prod_Posting_Group($Bal_VAT_Prod_Posting_Group)
    {
      $this->Bal_VAT_Prod_Posting_Group = $Bal_VAT_Prod_Posting_Group;
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setNature_of_Supply($Nature_of_Supply)
    {
      $this->Nature_of_Supply = $Nature_of_Supply;
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setTotal_GST_Amount($Total_GST_Amount)
    {
      $this->Total_GST_Amount = $Total_GST_Amount;
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setGST_Place_of_Supply($GST_Place_of_Supply)
    {
      $this->GST_Place_of_Supply = $GST_Place_of_Supply;
      return $this;
    }

    /**
     * @return string
     */
    public function getGST_Bill_to_BuyFrom_State_Code()
    {
      return $this->GST_Bill_to_BuyFrom_State_Code;
    }

    /**
     * @param string $GST_Bill_to_BuyFrom_State_Code
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setGST_Bill_to_BuyFrom_State_Code($GST_Bill_to_BuyFrom_State_Code)
    {
      $this->GST_Bill_to_BuyFrom_State_Code = $GST_Bill_to_BuyFrom_State_Code;
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setLocation_State_Code($Location_State_Code)
    {
      $this->Location_State_Code = $Location_State_Code;
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setGST_Customer_Type($GST_Customer_Type)
    {
      $this->GST_Customer_Type = $GST_Customer_Type;
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setGST_Vendor_Type($GST_Vendor_Type)
    {
      $this->GST_Vendor_Type = $GST_Vendor_Type;
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setHSN_SAC_Code($HSN_SAC_Code)
    {
      $this->HSN_SAC_Code = $HSN_SAC_Code;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getGST_on_Advance_Payment()
    {
      return $this->GST_on_Advance_Payment;
    }

    /**
     * @param boolean $GST_on_Advance_Payment
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setGST_on_Advance_Payment($GST_on_Advance_Payment)
    {
      $this->GST_on_Advance_Payment = $GST_on_Advance_Payment;
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setShip_to_Code($Ship_to_Code)
    {
      $this->Ship_to_Code = $Ship_to_Code;
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setLocation_Code($Location_Code)
    {
      $this->Location_Code = $Location_Code;
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setShortcutDimCode_x005B_8_x005D_($ShortcutDimCode_x005B_8_x005D_)
    {
      $this->ShortcutDimCode_x005B_8_x005D_ = $ShortcutDimCode_x005B_8_x005D_;
      return $this;
    }

    /**
     * @return string
     */
    public function getCheque_No()
    {
      return $this->Cheque_No;
    }

    /**
     * @param string $Cheque_No
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setCheque_No($Cheque_No)
    {
      $this->Cheque_No = $Cheque_No;
      return $this;
    }

    /**
     * @return date
     */
    public function getCheque_Date()
    {
      return $this->Cheque_Date;
    }

    /**
     * @param date $Cheque_Date
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setCheque_Date($Cheque_Date)
    {
      $this->Cheque_Date = $Cheque_Date;
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setApplies_to_Doc_No($Applies_to_Doc_No)
    {
      $this->Applies_to_Doc_No = $Applies_to_Doc_No;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getInclude_Serv_Tax_in_TDS_Base()
    {
      return $this->Include_Serv_Tax_in_TDS_Base;
    }

    /**
     * @param boolean $Include_Serv_Tax_in_TDS_Base
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setInclude_Serv_Tax_in_TDS_Base($Include_Serv_Tax_in_TDS_Base)
    {
      $this->Include_Serv_Tax_in_TDS_Base = $Include_Serv_Tax_in_TDS_Base;
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setService_Tax_Rounding_Type($Service_Tax_Rounding_Type)
    {
      $this->Service_Tax_Rounding_Type = $Service_Tax_Rounding_Type;
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setService_Tax_Rounding_Precision($Service_Tax_Rounding_Precision)
    {
      $this->Service_Tax_Rounding_Precision = $Service_Tax_Rounding_Precision;
      return $this;
    }

    /**
     * @return string
     */
    public function getSales_Order_No()
    {
      return $this->Sales_Order_No;
    }

    /**
     * @param string $Sales_Order_No
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setSales_Order_No($Sales_Order_No)
    {
      $this->Sales_Order_No = $Sales_Order_No;
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setApplies_to_ID($Applies_to_ID)
    {
      $this->Applies_to_ID = $Applies_to_ID;
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
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setReason_Code($Reason_Code)
    {
      $this->Reason_Code = $Reason_Code;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getApply()
    {
      return $this->Apply;
    }

    /**
     * @param boolean $Apply
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setApply($Apply)
    {
      $this->Apply = $Apply;
      return $this;
    }

    /**
     * @return string
     */
    public function getAccName()
    {
      return $this->AccName;
    }

    /**
     * @param string $AccName
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setAccName($AccName)
    {
      $this->AccName = $AccName;
      return $this;
    }

    /**
     * @return string
     */
    public function getBalAccName()
    {
      return $this->BalAccName;
    }

    /**
     * @param string $BalAccName
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setBalAccName($BalAccName)
    {
      $this->BalAccName = $BalAccName;
      return $this;
    }

    /**
     * @return float
     */
    public function getTotalDebitAmount()
    {
      return $this->TotalDebitAmount;
    }

    /**
     * @param float $TotalDebitAmount
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setTotalDebitAmount($TotalDebitAmount)
    {
      $this->TotalDebitAmount = $TotalDebitAmount;
      return $this;
    }

    /**
     * @return float
     */
    public function getBalance()
    {
      return $this->Balance;
    }

    /**
     * @param float $Balance
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setBalance($Balance)
    {
      $this->Balance = $Balance;
      return $this;
    }

    /**
     * @return float
     */
    public function getTotalBalance()
    {
      return $this->TotalBalance;
    }

    /**
     * @param float $TotalBalance
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setTotalBalance($TotalBalance)
    {
      $this->TotalBalance = $TotalBalance;
      return $this;
    }

    /**
     * @return float
     */
    public function getTotalCreditAmount()
    {
      return $this->TotalCreditAmount;
    }

    /**
     * @param float $TotalCreditAmount
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt
     */
    public function setTotalCreditAmount($TotalCreditAmount)
    {
      $this->TotalCreditAmount = $TotalCreditAmount;
      return $this;
    }

}
