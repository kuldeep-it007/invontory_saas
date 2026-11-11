<?php

namespace Rosetta\NAV\WS\STA;

class UpdateMultiple
{

    /**
     * @var STA_List $STA_List
     */
    protected $STA_List = null;

    /**
     * @param STA_List $STA_List
     */
    public function __construct($STA_List)
    {
      $this->STA_List = $STA_List;
    }

    /**
     * @return STA_List
     */
    public function getSTA_List()
    {
      return $this->STA_List;
    }

    /**
     * @param STA_List $STA_List
     * @return \Rosetta\NAV\WS\STA\UpdateMultiple
     */
    public function setSTA_List($STA_List)
    {
      $this->STA_List = $STA_List;
      return $this;
    }

}
