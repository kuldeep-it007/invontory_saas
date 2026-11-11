<?php

namespace Rosetta\NAV\WS\Inventory;

class Inventory_Filter
{

    /**
     * @var Inventory_Fields $Field
     */
    protected $Field = null;

    /**
     * @var string $Criteria
     */
    protected $Criteria = null;

    /**
     * @param Inventory_Fields $Field
     * @param string $Criteria
     */
    public function __construct($Field, $Criteria)
    {
      $this->Field = $Field;
      $this->Criteria = $Criteria;
    }

    /**
     * @return Inventory_Fields
     */
    public function getField()
    {
      return $this->Field;
    }

    /**
     * @param Inventory_Fields $Field
     * @return \Rosetta\NAV\WS\Inventory\Inventory_Filter
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
     * @return \Rosetta\NAV\WS\Inventory\Inventory_Filter
     */
    public function setCriteria($Criteria)
    {
      $this->Criteria = $Criteria;
      return $this;
    }

}
