<?php

namespace Rosetta\NAV\WS\ItemCard\SalesPrice;

class SalesPrices_List
{

    /**
     * @var SalesPrices[] $SalesPrices
     */
    protected $SalesPrices = null;

    /**
     * @param SalesPrices[] $SalesPrices
     */
    public function __construct(array $SalesPrices)
    {
      $this->SalesPrices = $SalesPrices;
    }

    /**
     * @return SalesPrices[]
     */
    public function getSalesPrices()
    {
      return $this->SalesPrices;
    }

    /**
     * @param SalesPrices[] $SalesPrices
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\SalesPrices_List
     */
    public function setSalesPrices(array $SalesPrices)
    {
      $this->SalesPrices = $SalesPrices;
      return $this;
    }

}
