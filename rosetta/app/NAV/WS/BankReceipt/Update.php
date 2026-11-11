<?php

namespace Rosetta\NAV\WS\BankReceipt;

class Update
{

    /**
     * @var string $CurrentJnlBatchName
     */
    protected $CurrentJnlBatchName = null;

    /**
     * @var bankreceipt $bankreceipt
     */
    protected $bankreceipt = null;

    /**
     * @param string $CurrentJnlBatchName
     * @param bankreceipt $bankreceipt
     */
    public function __construct($CurrentJnlBatchName, $bankreceipt)
    {
      $this->CurrentJnlBatchName = $CurrentJnlBatchName;
      $this->bankreceipt = $bankreceipt;
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
     * @return \Rosetta\NAV\WS\BankReceipt\Update
     */
    public function setCurrentJnlBatchName($CurrentJnlBatchName)
    {
      $this->CurrentJnlBatchName = $CurrentJnlBatchName;
      return $this;
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
     * @return \Rosetta\NAV\WS\BankReceipt\Update
     */
    public function setBankreceipt($bankreceipt)
    {
      $this->bankreceipt = $bankreceipt;
      return $this;
    }

}
