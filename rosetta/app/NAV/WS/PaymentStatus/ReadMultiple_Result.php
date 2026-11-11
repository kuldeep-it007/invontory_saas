<?php

namespace Rosetta\NAV\WS\PaymentStatus;

class ReadMultiple_Result
{

    /**
     * @var Payment_Status_List $ReadMultiple_Result
     */
    protected $ReadMultiple_Result = null;

    /**
     * @param Payment_Status_List $ReadMultiple_Result
     */
    public function __construct($ReadMultiple_Result)
    {
      $this->ReadMultiple_Result = $ReadMultiple_Result;
    }

    /**
     * @return Payment_Status_List
     */
    public function getReadMultiple_Result()
    {
      return $this->ReadMultiple_Result;
    }

    /**
     * @param Payment_Status_List $ReadMultiple_Result
     * @return \Rosetta\NAV\WS\PaymentStatus\ReadMultiple_Result
     */
    public function setReadMultiple_Result($ReadMultiple_Result)
    {
      $this->ReadMultiple_Result = $ReadMultiple_Result;
      return $this;
    }

}
