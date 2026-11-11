<?php

namespace Rosetta\NAV\WS\PO\Line;

class Purchase_Line_List
{

    /**
     * @var Purchase_Line[] $Purchase_Line
     */
    protected $Purchase_Line = null;

    /**
     * @param Purchase_Line[] $Purchase_Line
     */
    public function __construct(array $Purchase_Line)
    {
      $this->Purchase_Line = $Purchase_Line;
    }

    /**
     * @return Purchase_Line[]
     */
    public function getPurchase_Line()
    {
      return $this->Purchase_Line;
    }

    /**
     * @param Purchase_Line[] $Purchase_Line
     * @return \Rosetta\NAV\WS\PO\Line\Purchase_Line_List
     */
    public function setPurchase_Line(array $Purchase_Line)
    {
      $this->Purchase_Line = $Purchase_Line;
      return $this;
    }

}
