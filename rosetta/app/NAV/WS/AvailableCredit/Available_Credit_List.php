<?php

namespace Rosetta\NAV\WS\AvailableCredit;

class Available_Credit_List
{

    /**
     * @var Available_Credit[] $Available_Credit
     */
    protected $Available_Credit = null;

    /**
     * @param Available_Credit[] $Available_Credit
     */
    public function __construct(array $Available_Credit)
    {
      $this->Available_Credit = $Available_Credit;
    }

    /**
     * @return Available_Credit[]
     */
    public function getAvailable_Credit()
    {
      return $this->Available_Credit;
    }

    /**
     * @param Available_Credit[] $Available_Credit
     * @return \Rosetta\NAV\WS\AvailableCredit\Available_Credit_List
     */
    public function setAvailable_Credit(array $Available_Credit)
    {
      $this->Available_Credit = $Available_Credit;
      return $this;
    }

}
