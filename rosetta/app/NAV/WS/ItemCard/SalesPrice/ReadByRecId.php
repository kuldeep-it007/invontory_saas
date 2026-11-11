<?php

namespace Rosetta\NAV\WS\ItemCard\SalesPrice;

class ReadByRecId
{

    /**
     * @var string $recId
     */
    protected $recId = null;

    /**
     * @param string $recId
     */
    public function __construct($recId)
    {
      $this->recId = $recId;
    }

    /**
     * @return string
     */
    public function getRecId()
    {
      return $this->recId;
    }

    /**
     * @param string $recId
     * @return \Rosetta\NAV\WS\ItemCard\SalesPrice\ReadByRecId
     */
    public function setRecId($recId)
    {
      $this->recId = $recId;
      return $this;
    }

}
