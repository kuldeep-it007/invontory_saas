<?php

namespace Rosetta\NAV\WS\PO\Line;

class Create_Result
{

    /**
     * @var Purchase_Line $Purchase_Line
     */
    protected $Purchase_Line = null;

    /**
     * @param Purchase_Line $Purchase_Line
     */
    public function __construct($Purchase_Line)
    {
      $this->Purchase_Line = $Purchase_Line;
    }

    /**
     * @return Purchase_Line
     */
    public function getPurchase_Line()
    {
      return $this->Purchase_Line;
    }

    /**
     * @param Purchase_Line $Purchase_Line
     * @return \Rosetta\NAV\WS\PO\Line\Create_Result
     */
    public function setPurchase_Line($Purchase_Line)
    {
      $this->Purchase_Line = $Purchase_Line;
      return $this;
    }

}
