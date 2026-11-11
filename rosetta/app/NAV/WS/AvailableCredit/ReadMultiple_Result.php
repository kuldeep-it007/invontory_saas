<?php

namespace Rosetta\NAV\WS\AvailableCredit;

class ReadMultiple_Result
{

    /**
     * @var Available_Credit_List $ReadMultiple_Result
     */
    protected $ReadMultiple_Result = null;

    /**
     * @param Available_Credit_List $ReadMultiple_Result
     */
    public function __construct($ReadMultiple_Result)
    {
      $this->ReadMultiple_Result = $ReadMultiple_Result;
    }

    /**
     * @return Available_Credit_List
     */
    public function getReadMultiple_Result()
    {
      return $this->ReadMultiple_Result;
    }

    /**
     * @param Available_Credit_List $ReadMultiple_Result
     * @return \Rosetta\NAV\WS\AvailableCredit\ReadMultiple_Result
     */
    public function setReadMultiple_Result($ReadMultiple_Result)
    {
      $this->ReadMultiple_Result = $ReadMultiple_Result;
      return $this;
    }

}
