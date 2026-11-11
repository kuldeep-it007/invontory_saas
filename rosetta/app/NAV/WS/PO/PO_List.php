<?php

namespace Rosetta\NAV\WS\PO;

class PO_List
{

    /**
     * @var PO[] $PO
     */
    protected $PO = null;

    /**
     * @param PO[] $PO
     */
    public function __construct(array $PO)
    {
      $this->PO = $PO;
    }

    /**
     * @return PO[]
     */
    public function getPO()
    {
      return $this->PO;
    }

    /**
     * @param PO[] $PO
     * @return \Rosetta\NAV\WS\PO\PO_List
     */
    public function setPO(array $PO)
    {
      $this->PO = $PO;
      return $this;
    }

}
