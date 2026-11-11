<?php

namespace Rosetta\NAV\WS\PSSLines;

class ReadByRecId_Result
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
     * @return \Rosetta\NAV\WS\PSSLines\ReadByRecId_Result
     */
    public function setPSS($PSS)
    {
      $this->PSS = $PSS;
      return $this;
    }

}
