<?php

namespace Rosetta\NAV\WS\PendingPurchaseOrders;

class Pending_Purchase_Orders_List
{

    /**
     * @var Pending_Purchase_Orders[] $Pending_Purchase_Orders
     */
    protected $Pending_Purchase_Orders = null;

    /**
     * @param Pending_Purchase_Orders[] $Pending_Purchase_Orders
     */
    public function __construct(array $Pending_Purchase_Orders)
    {
      $this->Pending_Purchase_Orders = $Pending_Purchase_Orders;
    }

    /**
     * @return Pending_Purchase_Orders[]
     */
    public function getPending_Purchase_Orders()
    {
      return $this->Pending_Purchase_Orders;
    }

    /**
     * @param Pending_Purchase_Orders[] $Pending_Purchase_Orders
     * @return \Rosetta\NAV\WS\PendingPurchaseOrders\Pending_Purchase_Orders_List
     */
    public function setPending_Purchase_Orders(array $Pending_Purchase_Orders)
    {
      $this->Pending_Purchase_Orders = $Pending_Purchase_Orders;
      return $this;
    }

}
