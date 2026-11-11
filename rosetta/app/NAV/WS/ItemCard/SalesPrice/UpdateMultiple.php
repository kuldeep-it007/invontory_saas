<?php

namespace Rosetta\NAV\WS\ItemCard\SalesPrice;

class UpdateMultiple
{

    /**
     * @var SalesPrices_List $SalesPrices_List
     */
    protected $SalesPrices_List = null;

    /**
     * @param SalesPrices_List $SalesPrices_List
     */
    public function __construct($SalesPrices_List)
    {
      $this->SalesPrices_List = $SalesPrices_List;
    }

    /**
     * @return SalesPrices_List
     */
    public function getSalesPrices_List()
    {
      return $this->SalesPrices_List;
    }

    /**
     * @param SalesPrices_List $SalesPrices_List
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\UpdateMultiple
     */
    public function setSalesPrices_List($SalesPrices_List)
    {
      $this->SalesPrices_List = $SalesPrices_List;
      return $this;
    }

}
