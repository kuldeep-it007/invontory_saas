<?php

namespace Rosetta\NAV\WS\SalesLines;

class Create_Result
{

    /**
     * @var SalesLines $SalesLines
     */
    protected $SalesLines = null;

    /**
     * @param SalesLines $SalesLines
     */
    public function __construct($SalesLines)
    {
      $this->SalesLines = $SalesLines;
    }

    /**
     * @return SalesLines
     */
    public function getSalesLines()
    {
      return $this->SalesLines;
    }

    /**
     * @param SalesLines $SalesLines
     * @return \Rosetta\NAV\WS\SalesLines\Create_Result
     */
    public function setSalesLines($SalesLines)
    {
      $this->SalesLines = $SalesLines;
      return $this;
    }

}
