<?php

namespace Rosetta\NAV\WS\SalesLines;

class Read
{

    /**
     * @var string $Document_No
     */
    protected $Document_No = null;

    /**
     * @var int $Line_No
     */
    protected $Line_No = null;

    /**
     * @param string $Document_No
     * @param int $Line_No
     */
    public function __construct($Document_No, $Line_No)
    {
      $this->Document_No = $Document_No;
      $this->Line_No = $Line_No;
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
     * @return \Rosetta\NAV\WS\SalesLines\Read
     */
    public function setDocument_No($Document_No)
    {
      $this->Document_No = $Document_No;
      return $this;
    }

    /**
     * @return int
     */
    public function getLine_No()
    {
      return $this->Line_No;
    }

    /**
     * @param int $Line_No
     * @return \Rosetta\NAV\WS\SalesLines\Read
     */
    public function setLine_No($Line_No)
    {
      $this->Line_No = $Line_No;
      return $this;
    }

}
