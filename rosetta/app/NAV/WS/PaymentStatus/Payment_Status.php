<?php

namespace Rosetta\NAV\WS\PaymentStatus;

class Payment_Status
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
     * @var Document_Type $Document_Type
     */
    protected $Document_Type = null;

    /**
     * @var string $Document_No
     */
    protected $Document_No = null;

    /**
     * @var string $Customer_No
     */
    protected $Customer_No = null;

    /**
     * @var string $Description
     */
    protected $Description = null;

    /**
     * @var string $Currency_Code
     */
    protected $Currency_Code = null;

    /**
     * @var float $Original_Amount
     */
    protected $Original_Amount = null;

    /**
     * @var float $Original_Amt_LCY
     */
    protected $Original_Amt_LCY = null;

    /**
     * @var float $Amount
     */
    protected $Amount = null;

     /**
     * @var float $Amount_Paid
     */
    protected $Amount_Paid = null;

    /**
     * @var float $Amount_Paid_LCY
     */
    protected $Amount_Paid_LCY = null;

    /**
     * @var float $Amount_LCY
     */
    protected $Amount_LCY = null;

    /**
     * @var float $Remaining_Amount
     */
    protected $Remaining_Amount = null;

    /**
     * @var float $Remaining_Amt_LCY
     */
    protected $Remaining_Amt_LCY = null;

    /**
     * @var boolean $Open
     */
    protected $Open = null;

    
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
     * @return \Rosetta\NAV\WS\PaymentStatus\Payment_Status
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
     * @return \Rosetta\NAV\WS\PaymentStatus\Payment_Status
     */
    public function setPosting_Date($Posting_Date)
    {
      $this->Posting_Date = $Posting_Date;
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
     * @return \Rosetta\NAV\WS\PaymentStatus\Payment_Status
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
     * @return \Rosetta\NAV\WS\PaymentStatus\Payment_Status
     */
    public function setDocument_No($Document_No)
    {
      $this->Document_No = $Document_No;
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
     * @return \Rosetta\NAV\WS\PaymentStatus\Payment_Status
     */
    public function setCustomer_No($Customer_No)
    {
      $this->Customer_No = $Customer_No;
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
     * @return \Rosetta\NAV\WS\PaymentStatus\Payment_Status
     */
    public function setDescription($Description)
    {
      $this->Description = $Description;
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
     * @return \Rosetta\NAV\WS\PaymentStatus\Payment_Status
     */
    public function setCurrency_Code($Currency_Code)
    {
      $this->Currency_Code = $Currency_Code;
      return $this;
    }

    /**
     * @return float
     */
    public function getOriginal_Amount()
    {
      return $this->Original_Amount;
    }

    /**
     * @param float $Original_Amount
     * @return \Rosetta\NAV\WS\PaymentStatus\Payment_Status
     */
    public function setOriginal_Amount($Original_Amount)
    {
      $this->Original_Amount = $Original_Amount;
      return $this;
    }

    /**
     * @return float
     */
    public function getOriginal_Amt_LCY()
    {
      return $this->Original_Amt_LCY;
    }

    /**
     * @param float $Original_Amt_LCY
     * @return \Rosetta\NAV\WS\PaymentStatus\Payment_Status
     */
    public function setOriginal_Amt_LCY($Original_Amt_LCY)
    {
      $this->Original_Amt_LCY = $Original_Amt_LCY;
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
     * @return \Rosetta\NAV\WS\PaymentStatus\Payment_Status
     */
    public function setAmount($Amount)
    {
      $this->Amount = $Amount;
      return $this;
    }

    /**
     * @return float
     */
    public function getAmount_Paid()
    {
      return $this->Amount_Paid;
    }

    /**
     * @param float $Amount_Paid
     * @return \Rosetta\NAV\WS\PaymentStatus\Payment_Status
     */
    public function setAmount_Paid($Amount_Paid)
    {
      $this->Amount_Paid = $Amount_Paid;
      return $this;
    }
    
    /**
     * @return float
     */
    public function getAmount_Paid_LCY()
    {
      return $this->Amount_Paid_LCY;
    }

    /**
     * @param float $Amount_Paid_LCY
     * @return \Rosetta\NAV\WS\PaymentStatus\Payment_Status
     */
    public function setAmount_Paid_LCY($Amount_Paid_LCY)
    {
      $this->Amount_Paid_LCY = $Amount_Paid_LCY;
      return $this;
    }
    
    /**
     * @return float
     */
    public function getAmount_LCY()
    {
      return $this->Amount_LCY;
    }

    /**
     * @param float $Amount_LCY
     * @return \Rosetta\NAV\WS\PaymentStatus\Payment_Status
     */
    public function setAmount_LCY($Amount_LCY)
    {
      $this->Amount_LCY = $Amount_LCY;
      return $this;
    }

    /**
     * @return float
     */
    public function getRemaining_Amount()
    {
      return $this->Remaining_Amount;
    }

    /**
     * @param float $Remaining_Amount
     * @return \Rosetta\NAV\WS\PaymentStatus\Payment_Status
     */
    public function setRemaining_Amount($Remaining_Amount)
    {
      $this->Remaining_Amount = $Remaining_Amount;
      return $this;
    }

    /**
     * @return float
     */
    public function getRemaining_Amt_LCY()
    {
      return $this->Remaining_Amt_LCY;
    }

    /**
     * @param float $Remaining_Amt_LCY
     * @return \Rosetta\NAV\WS\PaymentStatus\Payment_Status
     */
    public function setRemaining_Amt_LCY($Remaining_Amt_LCY)
    {
      $this->Remaining_Amt_LCY = $Remaining_Amt_LCY;
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
     * @return \Rosetta\NAV\WS\PaymentStatus\Payment_Status
     */
    public function setOpen($Open)
    {
      $this->Open = $Open;
      return $this;
    }

}
