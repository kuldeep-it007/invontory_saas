<?php

namespace Rosetta\NAV\WS\PO;

class UpdateMultiple_Result
{

    /**
     * @var PO_List $PO_List
     */
    protected $PO_List = null;

    /**
     * @param PO_List $PO_List
     */
    public function __construct($PO_List)
    {
      $this->PO_List = $PO_List;
    }

    /**
     * @return PO_List
     */
    public function getPO_List()
    {
      return $this->PO_List;
    }

    /**
     * @param PO_List $PO_List
     * @return \Rosetta\NAV\WS\PO\UpdateMultiple_Result
     */
    public function setPO_List($PO_List)
    {
      $this->PO_List = $PO_List;
      return $this;
    }

}
