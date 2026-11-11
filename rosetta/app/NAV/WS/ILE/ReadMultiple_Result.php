<?php

namespace Rosetta\NAV\WS\ILE;

class ReadMultiple_Result
{

    /**
     * @var ILE_List $ReadMultiple_Result
     */
    protected $ReadMultiple_Result = null;

    /**
     * @param ILE_List $ReadMultiple_Result
     */
    public function __construct($ReadMultiple_Result)
    {
      $this->ReadMultiple_Result = $ReadMultiple_Result;
    }

    /**
     * @return ILE_List
     */
    public function getReadMultiple_Result()
    {
      return $this->ReadMultiple_Result;
    }

    /**
     * @param ILE_List $ReadMultiple_Result
     * @return \Rosetta\NAV\WS\ILE\ReadMultiple_Result
     */
    public function setReadMultiple_Result($ReadMultiple_Result)
    {
      $this->ReadMultiple_Result = $ReadMultiple_Result;
      return $this;
    }

}
