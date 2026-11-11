<?php

namespace Rosetta\NAV\WS\SalesOrder;

class Delete_SalesLines_Result
{

    /**
     * @var boolean $Delete_SalesLines_Result
     */
    protected $Delete_SalesLines_Result = null;

    /**
     * @param boolean $Delete_SalesLines_Result
     */
    public function __construct($Delete_SalesLines_Result)
    {
      $this->Delete_SalesLines_Result = $Delete_SalesLines_Result;
    }

    /**
     * @return boolean
     */
    public function getDelete_SalesLines_Result()
    {
      return $this->Delete_SalesLines_Result;
    }

    /**
     * @param boolean $Delete_SalesLines_Result
     * @return \Rosetta\NAV\WS\SalesOrder\Delete_SalesLines_Result
     */
    public function setDelete_SalesLines_Result($Delete_SalesLines_Result)
    {
      $this->Delete_SalesLines_Result = $Delete_SalesLines_Result;
      return $this;
    }

}
