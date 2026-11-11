<?php

namespace Rosetta\NAV\WS\ItemCardIntransit;

class UpdateMultiple
{

    /**
     * @var Item_Card_Intransit_List $Item_Card_Intransit_List
     */
    protected $Item_Card_Intransit_List = null;

    /**
     * @param Item_Card_Intransit_List $Item_Card_Intransit_List
     */
    public function __construct($Item_Card_Intransit_List)
    {
      $this->Item_Card_Intransit_List = $Item_Card_Intransit_List;
    }

    /**
     * @return Item_Card_Intransit_List
     */
    public function getItem_Card_Intransit_List()
    {
      return $this->Item_Card_Intransit_List;
    }

    /**
     * @param Item_Card_Intransit_List $Item_Card_Intransit_List
     * @return \Rosetta\NAV\WS\ItemCardIntransit\UpdateMultiple
     */
    public function setItem_Card_Intransit_List($Item_Card_Intransit_List)
    {
      $this->Item_Card_Intransit_List = $Item_Card_Intransit_List;
      return $this;
    }

}
