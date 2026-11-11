<?php

namespace Rosetta\NAV\WS\PSSLines;

class PSS_Filter
{

    /**
     * @var PSS_Fields $Field
     */
    protected $Field = null;

    /**
     * @var string $Criteria
     */
    protected $Criteria = null;

    /**
     * @param PSS_Fields $Field
     * @param string $Criteria
     */
    public function __construct($Field, $Criteria)
    {
      $this->Field = $Field;
      $this->Criteria = $Criteria;
    }

    /**
     * @return PSS_Fields
     */
    public function getField()
    {
      return $this->Field;
    }

    /**
     * @param PSS_Fields $Field
     * @return \Rosetta\NAV\WS\PSSLines\PSS_Filter
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
     * @return \Rosetta\NAV\WS\PSSLines\PSS_Filter
     */
    public function setCriteria($Criteria)
    {
      $this->Criteria = $Criteria;
      return $this;
    }

}
