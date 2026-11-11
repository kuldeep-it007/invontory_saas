<?php

namespace Rosetta\NAV\WS\ILE;

class ReadByRecId_Result
{

    /**
     * @var ILE $ILE
     */
    protected $ILE = null;

    /**
     * @param ILE $ILE
     */
    public function __construct($ILE)
    {
      $this->ILE = $ILE;
    }

    /**
     * @return ILE
     */
    public function getILE()
    {
      return $this->ILE;
    }

    /**
     * @param ILE $ILE
     * @return \Rosetta\NAV\WS\ILE\ReadByRecId_Result
     */
    public function setILE($ILE)
    {
      $this->ILE = $ILE;
      return $this;
    }

}
