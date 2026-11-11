<?php

namespace Rosetta\NAV\WS\PO;

class Delete_PurchLines
{

    /**
     * @var string $PurchLines_Key
     */
    protected $PurchLines_Key = null;

    /**
     * @param string $PurchLines_Key
     */
    public function __construct($PurchLines_Key)
    {
      $this->PurchLines_Key = $PurchLines_Key;
    }

    /**
     * @return string
     */
    public function getPurchLines_Key()
    {
      return $this->PurchLines_Key;
    }

    /**
     * @param string $PurchLines_Key
     * @return \Rosetta\NAV\WS\PO\Delete_PurchLines
     */
    public function setPurchLines_Key($PurchLines_Key)
    {
      $this->PurchLines_Key = $PurchLines_Key;
      return $this;
    }

}
