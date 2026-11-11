<?php

namespace Rosetta\NAV\WS\BankReceipt;

class CreateMultiple_Result
{

    /**
     * @var bankreceipt_List $bankreceipt_List
     */
    protected $bankreceipt_List = null;

    /**
     * @param bankreceipt_List $bankreceipt_List
     */
    public function __construct($bankreceipt_List)
    {
      $this->bankreceipt_List = $bankreceipt_List;
    }

    /**
     * @return bankreceipt_List
     */
    public function getBankreceipt_List()
    {
      return $this->bankreceipt_List;
    }

    /**
     * @param bankreceipt_List $bankreceipt_List
     * @return \Rosetta\NAV\WS\BankReceipt\CreateMultiple_Result
     */
    public function setBankreceipt_List($bankreceipt_List)
    {
      $this->bankreceipt_List = $bankreceipt_List;
      return $this;
    }

}
