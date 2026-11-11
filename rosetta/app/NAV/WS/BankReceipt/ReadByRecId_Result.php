<?php

namespace Rosetta\NAV\WS\BankReceipt;

class ReadByRecId_Result
{

    /**
     * @var bankreceipt $bankreceipt
     */
    protected $bankreceipt = null;

    /**
     * @param bankreceipt $bankreceipt
     */
    public function __construct($bankreceipt)
    {
      $this->bankreceipt = $bankreceipt;
    }

    /**
     * @return bankreceipt
     */
    public function getBankreceipt()
    {
      return $this->bankreceipt;
    }

    /**
     * @param bankreceipt $bankreceipt
     * @return \Rosetta\NAV\WS\BankReceipt\ReadByRecId_Result
     */
    public function setBankreceipt($bankreceipt)
    {
      $this->bankreceipt = $bankreceipt;
      return $this;
    }

}
