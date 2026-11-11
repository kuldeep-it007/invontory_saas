<?php

namespace Rosetta\NAV\WS\STA;

class ReadMultiple_Result
{

    /**
     * @var STA_List $ReadMultiple_Result
     */
    protected $ReadMultiple_Result = null;

    /**
     * @param STA_List $ReadMultiple_Result
     */
    public function __construct($ReadMultiple_Result)
    {
      $this->ReadMultiple_Result = $ReadMultiple_Result;
    }

    /**
     * @return STA_List
     */
    public function getReadMultiple_Result()
    {
      return $this->ReadMultiple_Result;
    }

    /**
     * @param STA_List $ReadMultiple_Result
     * @return \Rosetta\NAV\WS\STA\ReadMultiple_Result
     */
    public function setReadMultiple_Result($ReadMultiple_Result)
    {
      $this->ReadMultiple_Result = $ReadMultiple_Result;
      return $this;
    }

}
