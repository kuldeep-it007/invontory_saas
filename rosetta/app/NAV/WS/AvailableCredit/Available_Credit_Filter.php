<?php

namespace Rosetta\NAV\WS\AvailableCredit;

class Available_Credit_Filter
{

    /**
     * @var Available_Credit_Fields $Field
     */
    protected $Field = null;

    /**
     * @var string $Criteria
     */
    protected $Criteria = null;

    /**
     * @param Available_Credit_Fields $Field
     * @param string $Criteria
     */
    public function __construct($Field, $Criteria)
    {
      $this->Field = $Field;
      $this->Criteria = $Criteria;
    }

    /**
     * @return Available_Credit_Fields
     */
    public function getField()
    {
      return $this->Field;
    }

    /**
     * @param Available_Credit_Fields $Field
     * @return \Rosetta\NAV\WS\AvailableCredit\Available_Credit_Filter
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
     * @return \Rosetta\NAV\WS\AvailableCredit\Available_Credit_Filter
     */
    public function setCriteria($Criteria)
    {
      $this->Criteria = $Criteria;
      return $this;
    }

}
