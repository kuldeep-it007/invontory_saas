<?php

namespace Rosetta\NAV\WS\PortalCU;

class EditSO
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
     * @var string $lotNo
     */
    protected $lotNo = null;

    /**
     * @var float $unitprice
     */
    protected $unitprice = null;

    /**
     * @var string $newlot
     */
    protected $newlot = null;

    /**
     * @param string $docNo
     * @param int $docLineNo
     * @param float $qty
     * @param string $lotNo
     * @param float $unitprice
     * @param string $newlot
     */
    public function __construct($docNo, $docLineNo, $qty, $lotNo, $unitprice, $newlot)
    {
      $this->docNo = $docNo;
      $this->docLineNo = $docLineNo;
      $this->qty = $qty;
      $this->lotNo = $lotNo;
      $this->unitprice = $unitprice;
      $this->newlot = $newlot;
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
     * @return \Rosetta\NAV\WS\PortalCU\EditSO
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
     * @return \Rosetta\NAV\WS\PortalCU\EditSO
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
     * @return \Rosetta\NAV\WS\PortalCU\EditSO
     */
    public function setQty($qty)
    {
      $this->qty = $qty;
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
     * @return \Rosetta\NAV\WS\PortalCU\EditSO
     */
    public function setLotNo($lotNo)
    {
      $this->lotNo = $lotNo;
      return $this;
    }

    /**
     * @return float
     */
    public function getUnitprice()
    {
      return $this->unitprice;
    }

    /**
     * @param float $unitprice
     * @return \Rosetta\NAV\WS\PortalCU\EditSO
     */
    public function setUnitprice($unitprice)
    {
      $this->unitprice = $unitprice;
      return $this;
    }

    /**
     * @return string
     */
    public function getNewlot()
    {
      return $this->newlot;
    }

    /**
     * @param string $newlot
     * @return \Rosetta\NAV\WS\PortalCU\EditSO
     */
    public function setNewlot($newlot)
    {
      $this->newlot = $newlot;
      return $this;
    }

}
