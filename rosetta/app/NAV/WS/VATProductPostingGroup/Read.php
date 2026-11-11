<?php

namespace Rosetta\NAV\WS\VATProductPostingGroup;

class Read
{

    /**
     * @var string $Code
     */
    protected $Code = null;

    /**
     * @param string $Code
     */
    public function __construct($Code)
    {
      $this->Code = $Code;
    }

    /**
     * @return string
     */
    public function getCode()
    {
      return $this->Code;
    }

    /**
     * @param string $Code
     * @return \Rosetta\NAV\WS\VATProductPostingGroup\Read
     */
    public function setCode($Code)
    {
      $this->Code = $Code;
      return $this;
    }

}
