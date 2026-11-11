<?php

namespace Rosetta\NAV\WS\Colour;

class CreateMultiple
{

    /**
     * @var Colour_List $Colour_List
     */
    protected $Colour_List = null;

    /**
     * @param Colour_List $Colour_List
     */
    public function __construct($Colour_List)
    {
      $this->Colour_List = $Colour_List;
    }

    /**
     * @return Colour_List
     */
    public function getColour_List()
    {
      return $this->Colour_List;
    }

    /**
     * @param Colour_List $Colour_List
     * @return \Rosetta\NAV\WS\Colour\CreateMultiple
     */
    public function setColour_List($Colour_List)
    {
      $this->Colour_List = $Colour_List;
      return $this;
    }

}
