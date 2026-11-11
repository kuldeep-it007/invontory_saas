<?php

namespace Rosetta\NAV\WS\ILE;

class ILE_List
{

    /**
     * @var ILE[] $ILE
     */
    protected $ILE = null;

    /**
     * @param ILE[] $ILE
     */
    public function __construct(array $ILE)
    {
      $this->ILE = $ILE;
    }

    /**
     * @return ILE[]
     */
    public function getILE()
    {
      return $this->ILE;
    }

    /**
     * @param ILE[] $ILE
     * @return \Rosetta\NAV\WS\ILE\ILE_List
     */
    public function setILE(array $ILE)
    {
      $this->ILE = $ILE;
      return $this;
    }

}
