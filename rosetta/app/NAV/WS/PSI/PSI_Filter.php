<?php

namespace Rosetta\NAV\WS\PSI;

class PSI_Filter
{

    /**
     * @var PSI_Fields $Field
     */
    protected $Field = null;

    /**
     * @var string $Criteria
     */
    protected $Criteria = null;

    /**
     * @param PSI_Fields $Field
     * @param string $Criteria
     */
    public function __construct($Field, $Criteria)
    {
      $this->Field = $Field;
      $this->Criteria = $Criteria;
    }

    /**
     * @return PSI_Fields
     */
    public function getField()
    {
      return $this->Field;
    }

    /**
     * @param PSI_Fields $Field
     * @return \Rosetta\NAV\WS\PSI\PSI_Filter
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
     * @return \Rosetta\NAV\WS\PSI\PSI_Filter
     */
    public function setCriteria($Criteria)
    {
      $this->Criteria = $Criteria;
      return $this;
    }

}
