<?php

namespace Rosetta\NAV\WS\SalesLines;

class SalesLines_Filter
{

    /**
     * @var SalesLines_Fields $Field
     */
    protected $Field = null;

    /**
     * @var string $Criteria
     */
    protected $Criteria = null;

    /**
     * @param SalesLines_Fields $Field
     * @param string $Criteria
     */
    public function __construct($Field, $Criteria)
    {
      $this->Field = $Field;
      $this->Criteria = $Criteria;
    }

    /**
     * @return SalesLines_Fields
     */
    public function getField()
    {
      return $this->Field;
    }

    /**
     * @param SalesLines_Fields $Field
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines_Filter
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
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines_Filter
     */
    public function setCriteria($Criteria)
    {
      $this->Criteria = $Criteria;
      return $this;
    }

}
