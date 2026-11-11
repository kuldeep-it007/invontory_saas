<?php

namespace Rosetta\NAV\WS\Inventory;

class Read
{

    /**
     * @var string $No
     */
    protected $No = null;

    /**
     * @param string $No
     */
    public function __construct($No)
    {
      $this->No = $No;
    }

    /**
     * @return string
     */
    public function getNo()
    {
      return $this->No;
    }

    /**
     * @param string $No
     * @return \Rosetta\NAV\WS\Inventory\Read
     */
    public function setNo($No)
    {
      $this->No = $No;
      return $this;
    }

}
