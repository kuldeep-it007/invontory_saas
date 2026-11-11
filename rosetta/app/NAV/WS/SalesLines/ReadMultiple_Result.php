<?php

namespace Rosetta\NAV\WS\SalesLines;

class ReadMultiple_Result
{

    /**
     * @var SalesLines_List $ReadMultiple_Result
     */
    protected $ReadMultiple_Result = null;

    /**
     * @param SalesLines_List $ReadMultiple_Result
     */
    public function __construct($ReadMultiple_Result)
    {
      $this->ReadMultiple_Result = $ReadMultiple_Result;
    }

    /**
     * @return SalesLines_List
     */
    public function getReadMultiple_Result()
    {
      return $this->ReadMultiple_Result;
    }

    /**
     * @param SalesLines_List $ReadMultiple_Result
     * @return \Rosetta\NAV\WS\SalesLines\ReadMultiple_Result
     */
    public function setReadMultiple_Result($ReadMultiple_Result)
    {
      $this->ReadMultiple_Result = $ReadMultiple_Result;
      return $this;
    }

}
