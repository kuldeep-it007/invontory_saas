<?php

namespace Rosetta\NAV\WS\ItemCard;

class Create_Result
{

    /**
     * @var Item_Card $Item_Card
     */
    protected $Item_Card = null;

    /**
     * @param Item_Card $Item_Card
     */
    public function __construct($Item_Card)
    {
      $this->Item_Card = $Item_Card;
    }

    /**
     * @return Item_Card
     */
    public function getItem_Card()
    {
      return $this->Item_Card;
    }

    /**
     * @param Item_Card $Item_Card
     * @return \Rosetta\NAV\WS\ItemCard\Create_Result
     */
    public function setItem_Card($Item_Card)
    {
      $this->Item_Card = $Item_Card;
      return $this;
    }

}
