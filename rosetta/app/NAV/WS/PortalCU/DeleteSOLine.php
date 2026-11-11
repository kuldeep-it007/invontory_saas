<?php

namespace Rosetta\NAV\WS\PortalCU;

class DeleteSOLine
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
     * @var string $lotNo
     */
    protected $lotNo = null;

    /**
     * @param string $docNo
     * @param int $docLineNo
     * @param string $lotNo
     */
    public function __construct($docNo, $docLineNo, $lotNo)
    {
      $this->docNo = $docNo;
      $this->docLineNo = $docLineNo;
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
     * @return \Rosetta\NAV\WS\PortalCU\DeleteSOLine
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
     * @return \Rosetta\NAV\WS\PortalCU\DeleteSOLine
     */
    public function setDocLineNo($docLineNo)
    {
      $this->docLineNo = $docLineNo;
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
     * @return \Rosetta\NAV\WS\PortalCU\DeleteSOLine
     */
    public function setLotNo($lotNo)
    {
      $this->lotNo = $lotNo;
      return $this;
    }

}
