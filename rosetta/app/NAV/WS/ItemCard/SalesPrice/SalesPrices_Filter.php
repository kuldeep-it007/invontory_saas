<?php

namespace Rosetta\NAV\WS\ItemCard\SalesPrice;

class SalesPrices_Filter
{

    /**
     * @var SalesPrices_Fields $Field
     */
    protected $Field = null;

    /**
     * @var string $Criteria
     */
    protected $Criteria = null;

    /**
     * @param SalesPrices_Fields $Field
     * @param string $Criteria
     */
    public function __construct($Field, $Criteria)
    {
      $this->Field = $Field;
      $this->Criteria = $Criteria;
    }

    /**
     * @return SalesPrices_Fields
     */
    public function getField()
    {
      return $this->Field;
    }

    /**
     * @param SalesPrices_Fields $Field
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices_Filter
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
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices_Filter
     */
    public function setCriteria($Criteria)
    {
      $this->Criteria = $Criteria;
      return $this;
    }

}
