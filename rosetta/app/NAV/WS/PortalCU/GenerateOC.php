<?php

namespace Rosetta\NAV\WS\PortalCU;

class GenerateOC
{

    /**
     * @var string $pDF
     */
    protected $pDF = null;

    /**
     * @var string $orderNo
     */
    protected $orderNo = null;

    /**
     * @param string $pDF
     * @param string $orderNo
     */
    public function __construct($pDF, $orderNo)
    {
      $this->pDF = $pDF;
      $this->orderNo = $orderNo;
    }

    /**
     * @return string
     */
    public function getPDF()
    {
      return $this->pDF;
    }

    /**
     * @param string $pDF
     * @return \Rosetta\NAV\WS\PortalCU\GenerateOC
     */
    public function setPDF($pDF)
    {
      $this->pDF = $pDF;
      return $this;
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
     * @return \Rosetta\NAV\WS\PortalCU\GenerateOC
     */
    public function setOrderNo($orderNo)
    {
      $this->orderNo = $orderNo;
      return $this;
    }

}
