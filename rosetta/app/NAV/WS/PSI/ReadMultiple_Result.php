<?php

namespace Rosetta\NAV\WS\PSI;

class ReadMultiple_Result
{

    /**
     * @var PSI_List $ReadMultiple_Result
     */
    protected $ReadMultiple_Result = null;

    /**
     * @param PSI_List $ReadMultiple_Result
     */
    public function __construct($ReadMultiple_Result)
    {
      $this->ReadMultiple_Result = $ReadMultiple_Result;
    }

    /**
     * @return PSI_List
     */
    public function getReadMultiple_Result()
    {
      return $this->ReadMultiple_Result;
    }

    /**
     * @param PSI_List $ReadMultiple_Result
     * @return \Rosetta\NAV\WS\PSI\ReadMultiple_Result
     */
    public function setReadMultiple_Result($ReadMultiple_Result)
    {
      $this->ReadMultiple_Result = $ReadMultiple_Result;
      return $this;
    }

}
