<?php

namespace Rosetta\NAV\WS\SalesOrder;

class ReadMultiple_Result
{

    /**
     * @var Sales_Order_List $ReadMultiple_Result
     */
    protected $ReadMultiple_Result = null;

    /**
     * @param Sales_Order_List $ReadMultiple_Result
     */
    public function __construct($ReadMultiple_Result)
    {
      $this->ReadMultiple_Result = $ReadMultiple_Result;
    }

    /**
     * @return Sales_Order_List
     */
    public function getReadMultiple_Result()
    {
      return $this->ReadMultiple_Result;
    }

    /**
     * @param Sales_Order_List $ReadMultiple_Result
     * @return \Rosetta\NAV\WS\SalesOrder\ReadMultiple_Result
     */
    public function setReadMultiple_Result($ReadMultiple_Result)
    {
      $this->ReadMultiple_Result = $ReadMultiple_Result;
      return $this;
    }

}
