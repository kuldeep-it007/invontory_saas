<?php

namespace Rosetta\NAV\WS\ILE;

class Read
{

    /**
     * @var int $Entry_No
     */
    protected $Entry_No = null;

    /**
     * @param int $Entry_No
     */
    public function __construct($Entry_No)
    {
      $this->Entry_No = $Entry_No;
    }

    /**
     * @return int
     */
    public function getEntry_No()
    {
      return $this->Entry_No;
    }

    /**
     * @param int $Entry_No
     * @return \Rosetta\NAV\WS\ILE\Read
     */
    public function setEntry_No($Entry_No)
    {
      $this->Entry_No = $Entry_No;
      return $this;
    }

}
