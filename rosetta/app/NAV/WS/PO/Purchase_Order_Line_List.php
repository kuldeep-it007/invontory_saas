<?php

namespace Rosetta\NAV\WS\PO;

class Purchase_Order_Line_List
{

    /**
     * @var Purchase_Order_Line[] $Purchase_Order_Line
     */
    protected $Purchase_Order_Line = null;

    /**
     * @param Purchase_Order_Line[] $Purchase_Order_Line
     */
    public function __construct(array $Purchase_Order_Line)
    {
      $this->Purchase_Order_Line = $Purchase_Order_Line;
    }

    /**
     * @return Purchase_Order_Line[]
     */
    public function getPurchase_Order_Line()
    {
      return $this->Purchase_Order_Line;
    }

    /**
     * @param Purchase_Order_Line[] $Purchase_Order_Line
     * @return \Rosetta\NAV\WS\PO\Purchase_Order_Line_List
     */
    public function setPurchase_Order_Line(array $Purchase_Order_Line)
    {
      $this->Purchase_Order_Line = $Purchase_Order_Line;
      return $this;
    }

}
