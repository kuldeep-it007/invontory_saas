<?php

namespace Rosetta\NAV\WS\SalesOrder;

class Create
{

    /**
     * @var Sales_Order $Sales_Order
     */
    protected $Sales_Order = null;

    /**
     * @param Sales_Order $Sales_Order
     */
    public function __construct($Sales_Order)
    {
      $this->Sales_Order = $Sales_Order;
    }

    /**
     * @return Sales_Order
     */
    public function getSales_Order()
    {
      return $this->Sales_Order;
    }

    /**
     * @param Sales_Order $Sales_Order
     * @return \Rosetta\NAV\WS\SalesOrder\Create
     */
    public function setSales_Order($Sales_Order)
    {
      $this->Sales_Order = $Sales_Order;
      return $this;
    }

}
