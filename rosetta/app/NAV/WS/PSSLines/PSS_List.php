<?php

namespace Rosetta\NAV\WS\PSSLines;

class PSS_List
{

    /**
     * @var PSS[] $PSS
     */
    protected $PSS = null;

    /**
     * @param PSS[] $PSS
     */
    public function __construct(array $PSS)
    {
      $this->PSS = $PSS;
    }

    /**
     * @return PSS[]
     */
    public function getPSS()
    {
      return $this->PSS;
    }

    /**
     * @param PSS[] $PSS
     * @return \Rosetta\NAV\WS\PSSLines\PSS_List
     */
    public function setPSS(array $PSS)
    {
      $this->PSS = $PSS;
      return $this;
    }

}
