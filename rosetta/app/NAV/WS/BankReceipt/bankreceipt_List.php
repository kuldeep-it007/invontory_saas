<?php

namespace Rosetta\NAV\WS\BankReceipt;

class bankreceipt_List
{

    /**
     * @var bankreceipt[] $bankreceipt
     */
    protected $bankreceipt = null;

    /**
     * @param bankreceipt[] $bankreceipt
     */
    public function __construct(array $bankreceipt)
    {
      $this->bankreceipt = $bankreceipt;
    }

    /**
     * @return bankreceipt[]
     */
    public function getBankreceipt()
    {
      return $this->bankreceipt;
    }

    /**
     * @param bankreceipt[] $bankreceipt
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt_List
     */
    public function setBankreceipt(array $bankreceipt)
    {
      $this->bankreceipt = $bankreceipt;
      return $this;
    }

}
