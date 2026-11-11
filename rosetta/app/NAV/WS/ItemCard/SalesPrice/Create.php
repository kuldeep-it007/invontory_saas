<?php

namespace Rosetta\NAV\WS\ItemCard\SalesPrice;

class Create
{

    /**
     * @var SalesPrices $SalesPrices
     */
    protected $SalesPrices = null;

    /**
     * @param SalesPrices $SalesPrices
     */
    public function __construct($SalesPrices)
    {
      $this->SalesPrices = $SalesPrices;
    }

    /**
     * @return SalesPrices
     */
    public function getSalesPrices()
    {
      return $this->SalesPrices;
    }

    /**
     * @param SalesPrices $SalesPrices
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\Create
     */
    public function setSalesPrices($SalesPrices)
    {
      $this->SalesPrices = $SalesPrices;
      return $this;
    }

}
