<?php

namespace Rosetta\NAV\WS\PortalCU;

class GenerateOC_Result
{

    /**
     * @var string $pDF
     */
    protected $pDF = null;

    /**
     * @param string $pDF
     */
    public function __construct($pDF)
    {
      $this->pDF = $pDF;
    }

    /**
     * @return string
     */
    public function getPDF()
    {
      return $this->pDF;
    }

    /**
     * @param string $pDF
     * @return \Rosetta\NAV\WS\PortalCU\GenerateOC_Result
     */
    public function setPDF($pDF)
    {
      $this->pDF = $pDF;
      return $this;
    }

}
