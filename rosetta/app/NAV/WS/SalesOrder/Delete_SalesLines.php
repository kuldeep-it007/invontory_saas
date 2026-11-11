<?php

namespace Rosetta\NAV\WS\SalesOrder;

class Delete_SalesLines
{

    /**
     * @var string $SalesLines_Key
     */
    protected $SalesLines_Key = null;

    /**
     * @param string $SalesLines_Key
     */
    public function __construct($SalesLines_Key)
    {
      $this->SalesLines_Key = $SalesLines_Key;
    }

    /**
     * @return string
     */
    public function getSalesLines_Key()
    {
      return $this->SalesLines_Key;
    }

    /**
     * @param string $SalesLines_Key
     * @return \Rosetta\NAV\WS\SalesOrder\Delete_SalesLines
     */
    public function setSalesLines_Key($SalesLines_Key)
    {
      $this->SalesLines_Key = $SalesLines_Key;
      return $this;
    }

}
