<?php

namespace Rosetta\NAV\WS\PendingPurchaseOrders;

class Pending_Purchase_Orders_Filter
{

    /**
     * @var Pending_Purchase_Orders_Fields $Field
     */
    protected $Field = null;

    /**
     * @var string $Criteria
     */
    protected $Criteria = null;

    /**
     * @param Pending_Purchase_Orders_Fields $Field
     * @param string $Criteria
     */
    public function __construct($Field, $Criteria)
    {
      $this->Field = $Field;
      $this->Criteria = $Criteria;
    }

    /**
     * @return Pending_Purchase_Orders_Fields
     */
    public function getField()
    {
      return $this->Field;
    }

    /**
     * @param Pending_Purchase_Orders_Fields $Field
     * @return \Rosetta\NAV\WS\PendingPurchaseOrders\Pending_Purchase_Orders_Filter
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
     * @return \Rosetta\NAV\WS\PendingPurchaseOrders\Pending_Purchase_Orders_Filter
     */
    public function setCriteria($Criteria)
    {
      $this->Criteria = $Criteria;
      return $this;
    }

}
