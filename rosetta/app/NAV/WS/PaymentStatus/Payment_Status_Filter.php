<?php

namespace Rosetta\NAV\WS\PaymentStatus;

class Payment_Status_Filter
{

    /**
     * @var Payment_Status_Fields $Field
     */
    protected $Field = null;

    /**
     * @var string $Criteria
     */
    protected $Criteria = null;

    /**
     * @param Payment_Status_Fields $Field
     * @param string $Criteria
     */
    public function __construct($Field, $Criteria)
    {
      $this->Field = $Field;
      $this->Criteria = $Criteria;
    }

    /**
     * @return Payment_Status_Fields
     */
    public function getField()
    {
      return $this->Field;
    }

    /**
     * @param Payment_Status_Fields $Field
     * @return \Rosetta\NAV\WS\PaymentStatus\Payment_Status_Filter
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
     * @return \Rosetta\NAV\WS\PaymentStatus\Payment_Status_Filter
     */
    public function setCriteria($Criteria)
    {
      $this->Criteria = $Criteria;
      return $this;
    }

}
