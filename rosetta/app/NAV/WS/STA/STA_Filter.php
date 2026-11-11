<?php

namespace Rosetta\NAV\WS\STA;

class STA_Filter
{

    /**
     * @var STA_Fields $Field
     */
    protected $Field = null;

    /**
     * @var string $Criteria
     */
    protected $Criteria = null;

    /**
     * @param STA_Fields $Field
     * @param string $Criteria
     */
    public function __construct($Field, $Criteria)
    {
      $this->Field = $Field;
      $this->Criteria = $Criteria;
    }

    /**
     * @return STA_Fields
     */
    public function getField()
    {
      return $this->Field;
    }

    /**
     * @param STA_Fields $Field
     * @return \Rosetta\NAV\WS\STA\STA_Filter
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
     * @return \Rosetta\NAV\WS\STA\STA_Filter
     */
    public function setCriteria($Criteria)
    {
      $this->Criteria = $Criteria;
      return $this;
    }

}
