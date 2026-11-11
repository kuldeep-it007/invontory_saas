<?php

namespace Rosetta\NAV\WS\PO\Line;

class CreateMultiple
{

    /**
     * @var Purchase_Line_List $Purchase_Line_List
     */
    protected $Purchase_Line_List = null;

    /**
     * @param Purchase_Line_List $Purchase_Line_List
     */
    public function __construct($Purchase_Line_List)
    {
      $this->Purchase_Line_List = $Purchase_Line_List;
    }

    /**
     * @return Purchase_Line_List
     */
    public function getPurchase_Line_List()
    {
      return $this->Purchase_Line_List;
    }

    /**
     * @param Purchase_Line_List $Purchase_Line_List
     * @return \Rosetta\NAV\WS\PO\Line\CreateMultiple
     */
    public function setPurchase_Line_List($Purchase_Line_List)
    {
      $this->Purchase_Line_List = $Purchase_Line_List;
      return $this;
    }

}
