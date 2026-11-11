<?php

namespace Rosetta\NAV\WS\GeneralProductPostingGroup;

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
     * @return \Rosetta\NAV\WS\GeneralProductPostingGroup\Read
     */
    public function setCode($Code)
    {
      $this->Code = $Code;
      return $this;
    }

}
