<?php

namespace Rosetta\NAV\WS\SalesOrder;

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
     * @return \Rosetta\NAV\WS\SalesOrder\ReadByRecId
     */
    public function setRecId($recId)
    {
      $this->recId = $recId;
      return $this;
    }

}
