<?php

namespace Rosetta\NAV\WS\SalesLines;

class Read_Result
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
     * @return \Rosetta\NAV\WS\SalesLines\Read_Result
     */
    public function setSalesLines($SalesLines)
    {
      $this->SalesLines = $SalesLines;
      return $this;
    }

}
