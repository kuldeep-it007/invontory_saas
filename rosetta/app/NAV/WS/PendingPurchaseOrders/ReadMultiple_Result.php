<?php

namespace Rosetta\NAV\WS\PendingPurchaseOrders;

class ReadMultiple_Result
{

    /**
     * @var Pending_Purchase_Orders_List $ReadMultiple_Result
     */
    protected $ReadMultiple_Result = null;

    /**
     * @param Pending_Purchase_Orders_List $ReadMultiple_Result
     */
    public function __construct($ReadMultiple_Result)
    {
      $this->ReadMultiple_Result = $ReadMultiple_Result;
    }

    /**
     * @return Pending_Purchase_Orders_List
     */
    public function getReadMultiple_Result()
    {
      return $this->ReadMultiple_Result;
    }

    /**
     * @param Pending_Purchase_Orders_List $ReadMultiple_Result
     * @return \Rosetta\NAV\WS\PendingPurchaseOrders\ReadMultiple_Result
     */
    public function setReadMultiple_Result($ReadMultiple_Result)
    {
      $this->ReadMultiple_Result = $ReadMultiple_Result;
      return $this;
    }

}
