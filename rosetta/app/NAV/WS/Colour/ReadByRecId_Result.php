<?php

namespace Rosetta\NAV\WS\Colour;

class ReadByRecId_Result
{

    /**
     * @var Colour $Colour
     */
    protected $Colour = null;

    /**
     * @param Colour $Colour
     */
    public function __construct($Colour)
    {
      $this->Colour = $Colour;
    }

    /**
     * @return Colour
     */
    public function getColour()
    {
      return $this->Colour;
    }

    /**
     * @param Colour $Colour
     * @return \Rosetta\NAV\WS\Colour\ReadByRecId_Result
     */
    public function setColour($Colour)
    {
      $this->Colour = $Colour;
      return $this;
    }

}
