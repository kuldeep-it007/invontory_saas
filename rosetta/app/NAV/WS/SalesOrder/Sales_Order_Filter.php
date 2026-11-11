<?php

namespace Rosetta\NAV\WS\SalesOrder;

class Sales_Order_Filter
{

    /**
     * @var Sales_Order_Fields $Field
     */
    protected $Field = null;

    /**
     * @var string $Criteria
     */
    protected $Criteria = null;

    /**
     * @param Sales_Order_Fields $Field
     * @param string $Criteria
     */
    public function __construct($Field, $Criteria)
    {
      $this->Field = $Field;
      $this->Criteria = $Criteria;
    }

    /**
     * @return Sales_Order_Fields
     */
    public function getField()
    {
      return $this->Field;
    }

    /**
     * @param Sales_Order_Fields $Field
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Filter
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
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Filter
     */
    public function setCriteria($Criteria)
    {
      $this->Criteria = $Criteria;
      return $this;
    }

}
