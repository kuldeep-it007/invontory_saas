<?php

namespace Rosetta\NAV\WS\PendingPurchaseOrders;

class Read_Result
{

    /**
     * @var Pending_Purchase_Orders $Pending_Purchase_Orders
     */
    protected $Pending_Purchase_Orders = null;

    /**
     * @param Pending_Purchase_Orders $Pending_Purchase_Orders
     */
    public function __construct($Pending_Purchase_Orders)
    {
      $this->Pending_Purchase_Orders = $Pending_Purchase_Orders;
    }

    /**
     * @return Pending_Purchase_Orders
     */
    public function getPending_Purchase_Orders()
    {
      return $this->Pending_Purchase_Orders;
    }

    /**
     * @param Pending_Purchase_Orders $Pending_Purchase_Orders
     * @return \Rosetta\NAV\WS\PendingPurchaseOrders\Read_Result
     */
    public function setPending_Purchase_Orders($Pending_Purchase_Orders)
    {
      $this->Pending_Purchase_Orders = $Pending_Purchase_Orders;
      return $this;
    }

}
