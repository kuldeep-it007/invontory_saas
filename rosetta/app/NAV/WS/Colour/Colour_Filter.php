<?php

namespace Rosetta\NAV\WS\Colour;

class Colour_Filter
{

    /**
     * @var Colour_Fields $Field
     */
    protected $Field = null;

    /**
     * @var string $Criteria
     */
    protected $Criteria = null;

    /**
     * @param Colour_Fields $Field
     * @param string $Criteria
     */
    public function __construct($Field, $Criteria)
    {
      $this->Field = $Field;
      $this->Criteria = $Criteria;
    }

    /**
     * @return Colour_Fields
     */
    public function getField()
    {
      return $this->Field;
    }

    /**
     * @param Colour_Fields $Field
     * @return \Rosetta\NAV\WS\Colour\Colour_Filter
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
     * @return \Rosetta\NAV\WS\Colour\Colour_Filter
     */
    public function setCriteria($Criteria)
    {
      $this->Criteria = $Criteria;
      return $this;
    }

}
