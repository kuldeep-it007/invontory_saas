<?php

namespace Rosetta\NAV\WS\PendingPurchaseOrders;

class IsUpdated_Result
{

    /**
     * @var boolean $IsUpdated_Result
     */
    protected $IsUpdated_Result = null;

    /**
     * @param boolean $IsUpdated_Result
     */
    public function __construct($IsUpdated_Result)
    {
      $this->IsUpdated_Result = $IsUpdated_Result;
    }

    /**
     * @return boolean
     */
    public function getIsUpdated_Result()
    {
      return $this->IsUpdated_Result;
    }

    /**
     * @param boolean $IsUpdated_Result
     * @return \Rosetta\NAV\WS\PendingPurchaseOrders\IsUpdated_Result
     */
    public function setIsUpdated_Result($IsUpdated_Result)
    {
      $this->IsUpdated_Result = $IsUpdated_Result;
      return $this;
    }

}
