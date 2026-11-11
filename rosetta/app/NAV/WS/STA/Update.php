<?php

namespace Rosetta\NAV\WS\STA;

class Update
{

    /**
     * @var STA $STA
     */
    protected $STA = null;

    /**
     * @param STA $STA
     */
    public function __construct($STA)
    {
      $this->STA = $STA;
    }

    /**
     * @return STA
     */
    public function getSTA()
    {
      return $this->STA;
    }

    /**
     * @param STA $STA
     * @return \Rosetta\NAV\WS\STA\Update
     */
    public function setSTA($STA)
    {
      $this->STA = $STA;
      return $this;
    }

}
