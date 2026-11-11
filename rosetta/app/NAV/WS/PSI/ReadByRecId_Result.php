<?php

namespace Rosetta\NAV\WS\PSI;

class ReadByRecId_Result
{

    /**
     * @var PSI $PSI
     */
    protected $PSI = null;

    /**
     * @param PSI $PSI
     */
    public function __construct($PSI)
    {
      $this->PSI = $PSI;
    }

    /**
     * @return PSI
     */
    public function getPSI()
    {
      return $this->PSI;
    }

    /**
     * @param PSI $PSI
     * @return \Rosetta\NAV\WS\PSI\ReadByRecId_Result
     */
    public function setPSI($PSI)
    {
      $this->PSI = $PSI;
      return $this;
    }

}
