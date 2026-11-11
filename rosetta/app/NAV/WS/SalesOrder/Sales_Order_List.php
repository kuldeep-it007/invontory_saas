<?php

namespace Rosetta\NAV\WS\SalesOrder;

class Sales_Order_List
{

    /**
     * @var Sales_Order[] $Sales_Order
     */
    protected $Sales_Order = null;

    /**
     * @param Sales_Order[] $Sales_Order
     */
    public function __construct(array $Sales_Order)
    {
      $this->Sales_Order = $Sales_Order;
    }

    /**
     * @return Sales_Order[]
     */
    public function getSales_Order()
    {
      return $this->Sales_Order;
    }

    /**
     * @param Sales_Order[] $Sales_Order
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_List
     */
    public function setSales_Order(array $Sales_Order)
    {
      $this->Sales_Order = $Sales_Order;
      return $this;
    }

}
