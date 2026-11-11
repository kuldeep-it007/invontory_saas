<?php

namespace Rosetta\NAV\WS\SalesLines;

class SalesLines_List
{

    /**
     * @var SalesLines[] $SalesLines
     */
    protected $SalesLines = null;

    /**
     * @param SalesLines[] $SalesLines
     */
    public function __construct(array $SalesLines)
    {
      $this->SalesLines = $SalesLines;
    }

    /**
     * @return SalesLines[]
     */
    public function getSalesLines()
    {
      return $this->SalesLines;
    }

    /**
     * @param SalesLines[] $SalesLines
     * @return \Rosetta\NAV\WS\SalesLines\SalesLines_List
     */
    public function setSalesLines(array $SalesLines)
    {
      $this->SalesLines = $SalesLines;
      return $this;
    }

}
