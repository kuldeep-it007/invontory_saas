<?php

namespace Rosetta\NAV\WS\STA;

class Read
{

    /**
     * @var string $Customer_No
     */
    protected $Customer_No = null;

    /**
     * @var string $Code
     */
    protected $Code = null;

    /**
     * @param string $Customer_No
     * @param string $Code
     */
    public function __construct($Customer_No, $Code)
    {
      $this->Customer_No = $Customer_No;
      $this->Code = $Code;
    }

    /**
     * @return string
     */
    public function getCustomer_No()
    {
      return $this->Customer_No;
    }

    /**
     * @param string $Customer_No
     * @return \Rosetta\NAV\WS\STA\Read
     */
    public function setCustomer_No($Customer_No)
    {
      $this->Customer_No = $Customer_No;
      return $this;
    }

    /**
     * @return string
     */
    public function getCode()
    {
      return $this->Code;
    }

    /**
     * @param string $Code
     * @return \Rosetta\NAV\WS\STA\Read
     */
    public function setCode($Code)
    {
      $this->Code = $Code;
      return $this;
    }

}
