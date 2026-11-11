<?php

namespace Rosetta\NAV\WS\STA;

class STA_List
{

    /**
     * @var STA[] $STA
     */
    protected $STA = null;

    /**
     * @param STA[] $STA
     */
    public function __construct(array $STA)
    {
      $this->STA = $STA;
    }

    /**
     * @return STA[]
     */
    public function getSTA()
    {
      return $this->STA;
    }

    /**
     * @param STA[] $STA
     * @return \Rosetta\NAV\WS\STA\STA_List
     */
    public function setSTA(array $STA)
    {
      $this->STA = $STA;
      return $this;
    }

}
