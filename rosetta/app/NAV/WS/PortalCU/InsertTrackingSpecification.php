<?php

namespace Rosetta\NAV\WS\PortalCU;

class InsertTrackingSpecification
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
     * @var boolean $reserve
     */
    protected $reserve = null;

    /**
     * @param string $docNo
     * @param int $docLineNo
     * @param float $qty
     * @param string $serialNo
     * @param string $lotNo
     * @param boolean $reserve
     */
    public function __construct($docNo, $docLineNo, $qty, $serialNo, $lotNo, $reserve)
    {
      $this->docNo = $docNo;
      $this->docLineNo = $docLineNo;
      $this->qty = $qty;
      $this->serialNo = $serialNo;
      $this->lotNo = $lotNo;
      $this->reserve = $reserve;
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
     * @return \Rosetta\NAV\WS\PortalCU\InsertTrackingSpecification
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
     * @return \Rosetta\NAV\WS\PortalCU\InsertTrackingSpecification
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
     * @return \Rosetta\NAV\WS\PortalCU\InsertTrackingSpecification
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
     * @return \Rosetta\NAV\WS\PortalCU\InsertTrackingSpecification
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
     * @return \Rosetta\NAV\WS\PortalCU\InsertTrackingSpecification
     */
    public function setLotNo($lotNo)
    {
      $this->lotNo = $lotNo;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getReserve()
    {
      return $this->reserve;
    }

    /**
     * @param boolean $reserve
     * @return \Rosetta\NAV\WS\PortalCU\InsertTrackingSpecification
     */
    public function setReserve($reserve)
    {
      $this->reserve = $reserve;
      return $this;
    }

}
