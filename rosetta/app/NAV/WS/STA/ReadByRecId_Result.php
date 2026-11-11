<?php

namespace Rosetta\NAV\WS\STA;

class ReadByRecId_Result
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
     * @return \Rosetta\NAV\WS\STA\ReadByRecId_Result
     */
    public function setSTA($STA)
    {
      $this->STA = $STA;
      return $this;
    }

}
