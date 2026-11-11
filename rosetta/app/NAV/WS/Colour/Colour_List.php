<?php

namespace Rosetta\NAV\WS\Colour;

class Colour_List
{

    /**
     * @var Colour[] $Colour
     */
    protected $Colour = null;

    /**
     * @param Colour[] $Colour
     */
    public function __construct(array $Colour)
    {
      $this->Colour = $Colour;
    }

    /**
     * @return Colour[]
     */
    public function getColour()
    {
      return $this->Colour;
    }

    /**
     * @param Colour[] $Colour
     * @return \Rosetta\NAV\WS\Colour\Colour_List
     */
    public function setColour(array $Colour)
    {
      $this->Colour = $Colour;
      return $this;
    }

}
