<?php

namespace Rosetta\NAV\WS\PortalCU;

class GeneratePO
{

    /**
     * @var string $orderNo
     */
    protected $orderNo = null;

    /**
     * @param string $orderNo
     */
    public function __construct($orderNo)
    {
      $this->orderNo = $orderNo;
    }

    /**
     * @return string
     */
    public function getOrderNo()
    {
      return $this->orderNo;
    }

    /**
     * @param string $orderNo
     * @return \Rosetta\NAV\WS\PortalCU\GeneratePO
     */
    public function setOrderNo($orderNo)
    {
      $this->orderNo = $orderNo;
      return $this;
    }

}
