<?php

namespace Rosetta\NAV\WS\SalesOrder;

class Sales_Order_Line_List
{

    /**
     * @var Sales_Order_Line[] $Sales_Order_Line
     */
    protected $Sales_Order_Line = null;

    /**
     * @param Sales_Order_Line[] $Sales_Order_Line
     */
    public function __construct(array $Sales_Order_Line)
    {
      $this->Sales_Order_Line = $Sales_Order_Line;
    }

    /**
     * @return Sales_Order_Line[]
     */
    public function getSales_Order_Line()
    {
      return $this->Sales_Order_Line;
    }

    /**
     * @param Sales_Order_Line[] $Sales_Order_Line
     * @return \Rosetta\NAV\WS\SalesOrder\Sales_Order_Line_List
     */
    public function setSales_Order_Line(array $Sales_Order_Line)
    {
      $this->Sales_Order_Line = $Sales_Order_Line;
      return $this;
    }

}
