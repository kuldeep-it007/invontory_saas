<?php

namespace Rosetta\NAV\WS\PSSLines;

class Read_Result
{

    /**
     * @var PSS $PSS
     */
    protected $PSS = null;

    /**
     * @param PSS $PSS
     */
    public function __construct($PSS)
    {
      $this->PSS = $PSS;
    }

    /**
     * @return PSS
     */
    public function getPSS()
    {
      return $this->PSS;
    }

    /**
     * @param PSS $PSS
     * @return \Rosetta\NAV\WS\PSSLines\Read_Result
     */
    public function setPSS($PSS)
    {
      $this->PSS = $PSS;
      return $this;
    }

}
