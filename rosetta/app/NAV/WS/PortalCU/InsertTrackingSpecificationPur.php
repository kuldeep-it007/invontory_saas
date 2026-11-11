<?php

namespace Rosetta\NAV\WS\PortalCU;

class InsertTrackingSpecificationPur
{

    /**
     * @var string $docNo
     */
    protected $docNo = null;

    /**
     * @var int $docLineNo
     */
    protected $docLineNo = null;

    /**
     * @var float $qty
     */
    protected $qty = null;

    /**
     * @var string $serialNo
     */
    protected $serialNo = null;

    /**
     * @var string $lotNo
     */
    protected $lotNo = null;

    /**
     * @param string $docNo
     * @param int $docLineNo
     * @param float $qty
     * @param string $serialNo
     * @param string $lotNo
     */
    public function __construct($docNo, $docLineNo, $qty, $serialNo, $lotNo)
    {
      $this->docNo = $docNo;
      $this->docLineNo = $docLineNo;
      $this->qty = $qty;
      $this->serialNo = $serialNo;
      $this->lotNo = $lotNo;
    }

    /**
     * @return string
     */
    public function getDocNo()
    {
      return $this->docNo;
    }

    /**
     * @param string $docNo
     * @return \Rosetta\NAV\WS\PortalCU\InsertTrackingSpecificationPur
     */
    public function setDocNo($docNo)
    {
      $this->docNo = $docNo;
      return $this;
    }

    /**
     * @return int
     */
    public function getDocLineNo()
    {
      return $this->docLineNo;
    }

    /**
     * @param int $docLineNo
     * @return \Rosetta\NAV\WS\PortalCU\InsertTrackingSpecificationPur
     */
    public function setDocLineNo($docLineNo)
    {
      $this->docLineNo = $docLineNo;
      return $this;
    }

    /**
     * @return float
     */
    public function getQty()
    {
      return $this->qty;
    }

    /**
     * @param float $qty
     * @return \Rosetta\NAV\WS\PortalCU\InsertTrackingSpecificationPur
     */
    public function setQty($qty)
    {
      $this->qty = $qty;
      return $this;
    }

    /**
     * @return string
     */
    public function getSerialNo()
    {
      return $this->serialNo;
    }

    /**
     * @param string $serialNo
     * @return \Rosetta\NAV\WS\PortalCU\InsertTrackingSpecificationPur
     */
    public function setSerialNo($serialNo)
    {
      $this->serialNo = $serialNo;
      return $this;
    }

    /**
     * @return string
     */
    public function getLotNo()
    {
      return $this->lotNo;
    }

    /**
     * @param string $lotNo
     * @return \Rosetta\NAV\WS\PortalCU\InsertTrackingSpecificationPur
     */
    public function setLotNo($lotNo)
    {
      $this->lotNo = $lotNo;
      return $this;
    }

}
