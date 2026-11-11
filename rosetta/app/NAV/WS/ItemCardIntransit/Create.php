<?php

namespace Rosetta\NAV\WS\ItemCardIntransit;

class Create
{

    /**
     * @var Item_Card_Intransit $Item_Card_Intransit
     */
    protected $Item_Card_Intransit = null;

    /**
     * @param Item_Card_Intransit $Item_Card_Intransit
     */
    public function __construct($Item_Card_Intransit)
    {
      $this->Item_Card_Intransit = $Item_Card_Intransit;
    }

    /**
     * @return Item_Card_Intransit
     */
    public function getItem_Card_Intransit()
    {
      return $this->Item_Card_Intransit;
    }

    /**
     * @param Item_Card_Intransit $Item_Card_Intransit
     * @return \Rosetta\NAV\WS\ItemCardIntransit\Create
     */
    public function setItem_Card_Intransit($Item_Card_Intransit)
    {
      $this->Item_Card_Intransit = $Item_Card_Intransit;
      return $this;
    }

}
