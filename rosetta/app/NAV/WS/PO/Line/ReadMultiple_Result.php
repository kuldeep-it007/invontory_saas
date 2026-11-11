<?php

namespace Rosetta\NAV\WS\PO\Line;

class ReadMultiple_Result
{

    /**
     * @var Purchase_Line_List $ReadMultiple_Result
     */
    protected $ReadMultiple_Result = null;

    /**
     * @param Purchase_Line_List $ReadMultiple_Result
     */
    public function __construct($ReadMultiple_Result)
    {
      $this->ReadMultiple_Result = $ReadMultiple_Result;
    }

    /**
     * @return Purchase_Line_List
     */
    public function getReadMultiple_Result()
    {
      return $this->ReadMultiple_Result;
    }

    /**
     * @param Purchase_Line_List $ReadMultiple_Result
     * @return \Rosetta\NAV\WS\PO\Line\ReadMultiple_Result
     */
    public function setReadMultiple_Result($ReadMultiple_Result)
    {
      $this->ReadMultiple_Result = $ReadMultiple_Result;
      return $this;
    }

}
