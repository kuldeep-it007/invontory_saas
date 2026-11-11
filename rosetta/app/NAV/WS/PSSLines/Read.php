<?php

namespace Rosetta\NAV\WS\PSSLines;

class Read
{

    /**
     * @var string $Document_No
     */
    protected $Document_No = null;

    /**
     * @param string $Document_No
     */
    public function __construct($Document_No)
    {
      $this->Document_No = $Document_No;
    }

    /**
     * @return string
     */
    public function getDocument_No()
    {
      return $this->Document_No;
    }

    /**
     * @param string $Document_No
     * @return \Rosetta\NAV\WS\PSSLines\Read
     */
    public function setDocument_No($Document_No)
    {
      $this->Document_No = $Document_No;
      return $this;
    }

}
