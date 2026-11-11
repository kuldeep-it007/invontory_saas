<?php

namespace Rosetta\NAV\WS\SalesLines;

class CreateMultiple
{

    /**
     * @var SalesLines_List $SalesLines_List
     */
    protected $SalesLines_List = null;

    /**
     * @param SalesLines_List $SalesLines_List
     */
    public function __construct($SalesLines_List)
    {
      $this->SalesLines_List = $SalesLines_List;
    }

    /**
     * @return SalesLines_List
     */
    public function getSalesLines_List()
    {
      return $this->SalesLines_List;
    }

    /**
     * @param SalesLines_List $SalesLines_List
     * @return \Rosetta\NAV\WS\SalesLines\CreateMultiple
     */
    public function setSalesLines_List($SalesLines_List)
    {
      $this->SalesLines_List = $SalesLines_List;
      return $this;
    }

}
