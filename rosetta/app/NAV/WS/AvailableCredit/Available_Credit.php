<?php

namespace Rosetta\NAV\WS\AvailableCredit;

class Available_Credit
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
     * @var float $Balance_LCY
     */
    protected $Balance_LCY = null;

    /**
     * @var float $Outstanding_Orders_LCY
     */
    protected $Outstanding_Orders_LCY = null;

    /**
     * @var float $Shipped_Not_Invoiced_LCY
     */
    protected $Shipped_Not_Invoiced_LCY = null;

    /**
     * @var float $Outstanding_Invoices_LCY
     */
    protected $Outstanding_Invoices_LCY = null;

    /**
     * @var float $Outstanding_Serv_Orders_LCY
     */
    protected $Outstanding_Serv_Orders_LCY = null;

    /**
     * @var float $Serv_Shipped_Not_Invoiced_LCY
     */
    protected $Serv_Shipped_Not_Invoiced_LCY = null;

    /**
     * @var float $Outstanding_Serv_Invoices_LCY
     */
    protected $Outstanding_Serv_Invoices_LCY = null;

    /**
     * @var float $GetTotalAmountLCYUI
     */
    protected $GetTotalAmountLCYUI = null;

    /**
     * @var float $Credit_Limit_LCY
     */
    protected $Credit_Limit_LCY = null;

    /**
     * @var float $CalcAvailableCreditUI
     */
    protected $CalcAvailableCreditUI = null;

    /**
     * @var float $Balance_Due_LCY
     */
    protected $Balance_Due_LCY = null;

    /**
     * @var float $GetInvoicedPrepmtAmountLCY
     */
    protected $GetInvoicedPrepmtAmountLCY = null;

    
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
     * @return \Rosetta\NAV\WS\AvailableCredit\Available_Credit
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
     * @return \Rosetta\NAV\WS\AvailableCredit\Available_Credit
     */
    public function setNo($No)
    {
      $this->No = $No;
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
     * @return \Rosetta\NAV\WS\AvailableCredit\Available_Credit
     */
    public function setBalance_LCY($Balance_LCY)
    {
      $this->Balance_LCY = $Balance_LCY;
      return $this;
    }

    /**
     * @return float
     */
    public function getOutstanding_Orders_LCY()
    {
      return $this->Outstanding_Orders_LCY;
    }

    /**
     * @param float $Outstanding_Orders_LCY
     * @return \Rosetta\NAV\WS\AvailableCredit\Available_Credit
     */
    public function setOutstanding_Orders_LCY($Outstanding_Orders_LCY)
    {
      $this->Outstanding_Orders_LCY = $Outstanding_Orders_LCY;
      return $this;
    }

    /**
     * @return float
     */
    public function getShipped_Not_Invoiced_LCY()
    {
      return $this->Shipped_Not_Invoiced_LCY;
    }

    /**
     * @param float $Shipped_Not_Invoiced_LCY
     * @return \Rosetta\NAV\WS\AvailableCredit\Available_Credit
     */
    public function setShipped_Not_Invoiced_LCY($Shipped_Not_Invoiced_LCY)
    {
      $this->Shipped_Not_Invoiced_LCY = $Shipped_Not_Invoiced_LCY;
      return $this;
    }

    /**
     * @return float
     */
    public function getOutstanding_Invoices_LCY()
    {
      return $this->Outstanding_Invoices_LCY;
    }

    /**
     * @param float $Outstanding_Invoices_LCY
     * @return \Rosetta\NAV\WS\AvailableCredit\Available_Credit
     */
    public function setOutstanding_Invoices_LCY($Outstanding_Invoices_LCY)
    {
      $this->Outstanding_Invoices_LCY = $Outstanding_Invoices_LCY;
      return $this;
    }

    /**
     * @return float
     */
    public function getOutstanding_Serv_Orders_LCY()
    {
      return $this->Outstanding_Serv_Orders_LCY;
    }

    /**
     * @param float $Outstanding_Serv_Orders_LCY
     * @return \Rosetta\NAV\WS\AvailableCredit\Available_Credit
     */
    public function setOutstanding_Serv_Orders_LCY($Outstanding_Serv_Orders_LCY)
    {
      $this->Outstanding_Serv_Orders_LCY = $Outstanding_Serv_Orders_LCY;
      return $this;
    }

    /**
     * @return float
     */
    public function getServ_Shipped_Not_Invoiced_LCY()
    {
      return $this->Serv_Shipped_Not_Invoiced_LCY;
    }

    /**
     * @param float $Serv_Shipped_Not_Invoiced_LCY
     * @return \Rosetta\NAV\WS\AvailableCredit\Available_Credit
     */
    public function setServ_Shipped_Not_Invoiced_LCY($Serv_Shipped_Not_Invoiced_LCY)
    {
      $this->Serv_Shipped_Not_Invoiced_LCY = $Serv_Shipped_Not_Invoiced_LCY;
      return $this;
    }

    /**
     * @return float
     */
    public function getOutstanding_Serv_Invoices_LCY()
    {
      return $this->Outstanding_Serv_Invoices_LCY;
    }

    /**
     * @param float $Outstanding_Serv_Invoices_LCY
     * @return \Rosetta\NAV\WS\AvailableCredit\Available_Credit
     */
    public function setOutstanding_Serv_Invoices_LCY($Outstanding_Serv_Invoices_LCY)
    {
      $this->Outstanding_Serv_Invoices_LCY = $Outstanding_Serv_Invoices_LCY;
      return $this;
    }

    /**
     * @return float
     */
    public function getGetTotalAmountLCYUI()
    {
      return $this->GetTotalAmountLCYUI;
    }

    /**
     * @param float $GetTotalAmountLCYUI
     * @return \Rosetta\NAV\WS\AvailableCredit\Available_Credit
     */
    public function setGetTotalAmountLCYUI($GetTotalAmountLCYUI)
    {
      $this->GetTotalAmountLCYUI = $GetTotalAmountLCYUI;
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
     * @return \Rosetta\NAV\WS\AvailableCredit\Available_Credit
     */
    public function setCredit_Limit_LCY($Credit_Limit_LCY)
    {
      $this->Credit_Limit_LCY = $Credit_Limit_LCY;
      return $this;
    }

    /**
     * @return float
     */
    public function getCalcAvailableCreditUI()
    {
      return $this->CalcAvailableCreditUI;
    }

    /**
     * @param float $CalcAvailableCreditUI
     * @return \Rosetta\NAV\WS\AvailableCredit\Available_Credit
     */
    public function setCalcAvailableCreditUI($CalcAvailableCreditUI)
    {
      $this->CalcAvailableCreditUI = $CalcAvailableCreditUI;
      return $this;
    }

    /**
     * @return float
     */
    public function getBalance_Due_LCY()
    {
      return $this->Balance_Due_LCY;
    }

    /**
     * @param float $Balance_Due_LCY
     * @return \Rosetta\NAV\WS\AvailableCredit\Available_Credit
     */
    public function setBalance_Due_LCY($Balance_Due_LCY)
    {
      $this->Balance_Due_LCY = $Balance_Due_LCY;
      return $this;
    }

    /**
     * @return float
     */
    public function getGetInvoicedPrepmtAmountLCY()
    {
      return $this->GetInvoicedPrepmtAmountLCY;
    }

    /**
     * @param float $GetInvoicedPrepmtAmountLCY
     * @return \Rosetta\NAV\WS\AvailableCredit\Available_Credit
     */
    public function setGetInvoicedPrepmtAmountLCY($GetInvoicedPrepmtAmountLCY)
    {
      $this->GetInvoicedPrepmtAmountLCY = $GetInvoicedPrepmtAmountLCY;
      return $this;
    }

}
