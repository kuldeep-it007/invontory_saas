<?php

namespace Rosetta\NAV\WS\PO;

class Delete_PurchLines_Result
{

    /**
     * @var boolean $Delete_PurchLines_Result
     */
    protected $Delete_PurchLines_Result = null;

    /**
     * @param boolean $Delete_PurchLines_Result
     */
    public function __construct($Delete_PurchLines_Result)
    {
      $this->Delete_PurchLines_Result = $Delete_PurchLines_Result;
    }

    /**
     * @return boolean
     */
    public function getDelete_PurchLines_Result()
    {
      return $this->Delete_PurchLines_Result;
    }

    /**
     * @param boolean $Delete_PurchLines_Result
     * @return \Rosetta\NAV\WS\PO\Delete_PurchLines_Result
     */
    public function setDelete_PurchLines_Result($Delete_PurchLines_Result)
    {
      $this->Delete_PurchLines_Result = $Delete_PurchLines_Result;
      return $this;
    }

}
