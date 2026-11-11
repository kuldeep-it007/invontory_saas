<?php

namespace Rosetta\NAV\WS\AvailableCredit;

class Read_Result
{

    /**
     * @var Available_Credit $Available_Credit
     */
    protected $Available_Credit = null;

    /**
     * @param Available_Credit $Available_Credit
     */
    public function __construct($Available_Credit)
    {
      $this->Available_Credit = $Available_Credit;
    }

    /**
     * @return Available_Credit
     */
    public function getAvailable_Credit()
    {
      return $this->Available_Credit;
    }

    /**
     * @param Available_Credit $Available_Credit
     * @return \Rosetta\NAV\WS\AvailableCredit\Read_Result
     */
    public function setAvailable_Credit($Available_Credit)
    {
      $this->Available_Credit = $Available_Credit;
      return $this;
    }

}
