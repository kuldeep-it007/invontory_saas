<?php

namespace Rosetta\NAV\WS\ProductGroups;

class GetRecIdFromKey
{

    /**
     * @var string $Key
     */
    protected $Key = null;

    /**
     * @param string $Key
     */
    public function __construct($Key)
    {
      $this->Key = $Key;
    }

    /**
     * @return string
     */
    public function getKey()
    {
      return $this->Key;
    }

    /**
     * @param string $Key
     * @return \Rosetta\NAV\WS\ProductGroups\GetRecIdFromKey
     */
    public function setKey($Key)
    {
      $this->Key = $Key;
      return $this;
    }

}
