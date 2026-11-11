<?php

namespace Rosetta\NAV\WS\PO;

class Create
{

    /**
     * @var PO $PO
     */
    protected $PO = null;

    /**
     * @param PO $PO
     */
    public function __construct($PO)
    {
      $this->PO = $PO;
    }

    /**
     * @return PO
     */
    public function getPO()
    {
      return $this->PO;
    }

    /**
     * @param PO $PO
     * @return \Rosetta\NAV\WS\PO\Create
     */
    public function setPO($PO)
    {
      $this->PO = $PO;
      return $this;
    }

}
