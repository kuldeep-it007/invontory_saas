<?php

namespace Rosetta\NAV\WS\PO\Line;

class Purchase_Line_Filter
{

    /**
     * @var Purchase_Line_Fields $Field
     */
    protected $Field = null;

    /**
     * @var string $Criteria
     */
    protected $Criteria = null;

    /**
     * @param Purchase_Line_Fields $Field
     * @param string $Criteria
     */
    public function __construct($Field, $Criteria)
    {
      $this->Field = $Field;
      $this->Criteria = $Criteria;
    }

    /**
     * @return Purchase_Line_Fields
     */
    public function getField()
    {
      return $this->Field;
    }

    /**
     * @param Purchase_Line_Fields $Field
     * @return \Rosetta\NAV\WS\PO\Line\Purchase_Line_Filter
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
     * @return \Rosetta\NAV\WS\PO\Line\Purchase_Line_Filter
     */
    public function setCriteria($Criteria)
    {
      $this->Criteria = $Criteria;
      return $this;
    }

}
