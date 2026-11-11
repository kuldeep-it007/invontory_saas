<?php

namespace Rosetta\NAV\WS\SalesOrder;

class CreateMultiple_Result
{

    /**
     * @var Sales_Order_List $Sales_Order_List
     */
    protected $Sales_Order_List = null;

    /**
     * @param Sales_Order_List $Sales_Order_List
     */
    public function __construct($Sales_Order_List)
    {
      $this->Sales_Order_List = $Sales_Order_List;
    }

    /**
     * @return Sales_Order_List
     */
    public function getSales_Order_List()
    {
      return $this->Sales_Order_List;
    }

    /**
     * @param Sales_Order_List $Sales_Order_List
     * @return \Rosetta\NAV\WS\SalesOrder\CreateMultiple_Result
     */
    public function setSales_Order_List($Sales_Order_List)
    {
      $this->Sales_Order_List = $Sales_Order_List;
      return $this;
    }

}
