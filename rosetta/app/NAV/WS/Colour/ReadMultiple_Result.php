<?php

namespace Rosetta\NAV\WS\Colour;

class ReadMultiple_Result
{

    /**
     * @var Colour_List $ReadMultiple_Result
     */
    protected $ReadMultiple_Result = null;

    /**
     * @param Colour_List $ReadMultiple_Result
     */
    public function __construct($ReadMultiple_Result)
    {
      $this->ReadMultiple_Result = $ReadMultiple_Result;
    }

    /**
     * @return Colour_List
     */
    public function getReadMultiple_Result()
    {
      return $this->ReadMultiple_Result;
    }

    /**
     * @param Colour_List $ReadMultiple_Result
     * @return \Rosetta\NAV\WS\Colour\ReadMultiple_Result
     */
    public function setReadMultiple_Result($ReadMultiple_Result)
    {
      $this->ReadMultiple_Result = $ReadMultiple_Result;
      return $this;
    }

}
