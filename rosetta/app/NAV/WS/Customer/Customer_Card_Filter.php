<?php

namespace Rosetta\NAV\WS\Customer;

class Customer_Card_Filter
{

    /**
     * @var Customer_Card_Fields $Field
     */
    protected $Field = null;

    /**
     * @var string $Criteria
     */
    protected $Criteria = null;

    /**
     * @param Customer_Card_Fields $Field
     * @param string $Criteria
     */
    public function __construct($Field, $Criteria)
    {
      $this->Field = $Field;
      $this->Criteria = $Criteria;
    }

    /**
     * @return Customer_Card_Fields
     */
    public function getField()
    {
      return $this->Field;
    }

    /**
     * @param Customer_Card_Fields $Field
     * @return \Rosetta\NAV\WS\Customer\Customer_Card_Filter
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
     * @return \Rosetta\NAV\WS\Customer\Customer_Card_Filter
     */
    public function setCriteria($Criteria)
    {
      $this->Criteria = $Criteria;
      return $this;
    }

}
