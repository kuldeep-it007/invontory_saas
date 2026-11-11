<?php

namespace Rosetta\NAV\WS\BankReceipt;

class UpdateMultiple
{

    /**
     * @var string $CurrentJnlBatchName
     */
    protected $CurrentJnlBatchName = null;

    /**
     * @var bankreceipt_List $bankreceipt_List
     */
    protected $bankreceipt_List = null;

    /**
     * @param string $CurrentJnlBatchName
     * @param bankreceipt_List $bankreceipt_List
     */
    public function __construct($CurrentJnlBatchName, $bankreceipt_List)
    {
      $this->CurrentJnlBatchName = $CurrentJnlBatchName;
      $this->bankreceipt_List = $bankreceipt_List;
    }

    /**
     * @return string
     */
    public function getCurrentJnlBatchName()
    {
      return $this->CurrentJnlBatchName;
    }

    /**
     * @param string $CurrentJnlBatchName
     * @return \Rosetta\NAV\WS\BankReceipt\UpdateMultiple
     */
    public function setCurrentJnlBatchName($CurrentJnlBatchName)
    {
      $this->CurrentJnlBatchName = $CurrentJnlBatchName;
      return $this;
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
     * @return \Rosetta\NAV\WS\BankReceipt\UpdateMultiple
     */
    public function setBankreceipt_List($bankreceipt_List)
    {
      $this->bankreceipt_List = $bankreceipt_List;
      return $this;
    }

}
