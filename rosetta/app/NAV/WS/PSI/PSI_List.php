<?php

namespace Rosetta\NAV\WS\PSI;

class PSI_List
{

    /**
     * @var PSI[] $PSI
     */
    protected $PSI = null;

    /**
     * @param PSI[] $PSI
     */
    public function __construct(array $PSI)
    {
      $this->PSI = $PSI;
    }

    /**
     * @return PSI[]
     */
    public function getPSI()
    {
      return $this->PSI;
    }

    /**
     * @param PSI[] $PSI
     * @return \Rosetta\NAV\WS\PSI\PSI_List
     */
    public function setPSI(array $PSI)
    {
      $this->PSI = $PSI;
      return $this;
    }

}
