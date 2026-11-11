<?php

namespace Rosetta\NAV\WS\BankReceipt;

class bankreceipt_Filter
{

    /**
     * @var bankreceipt_Fields $Field
     */
    protected $Field = null;

    /**
     * @var string $Criteria
     */
    protected $Criteria = null;

    /**
     * @param bankreceipt_Fields $Field
     * @param string $Criteria
     */
    public function __construct($Field, $Criteria)
    {
      $this->Field = $Field;
      $this->Criteria = $Criteria;
    }

    /**
     * @return bankreceipt_Fields
     */
    public function getField()
    {
      return $this->Field;
    }

    /**
     * @param bankreceipt_Fields $Field
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt_Filter
     */
    public function setField($Field)
    {
      $this->Field = $Field;
      return $this;
    }

    /**
     * @return string
     */
    public function getCriteria()
    {
      return $this->Criteria;
    }

    /**
     * @param string $Criteria
     * @return \Rosetta\NAV\WS\BankReceipt\bankreceipt_Filter
     */
    public function setCriteria($Criteria)
    {
      $this->Criteria = $Criteria;
      return $this;
    }

}
