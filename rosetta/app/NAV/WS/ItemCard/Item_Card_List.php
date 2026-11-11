<?php

namespace Rosetta\NAV\WS\ItemCard;

class Item_Card_List
{

    /**
     * @var Item_Card[] $Item_Card
     */
    protected $Item_Card = null;

    /**
     * @param Item_Card[] $Item_Card
     */
    public function __construct(array $Item_Card)
    {
      $this->Item_Card = $Item_Card;
    }

    /**
     * @return Item_Card[]
     */
    public function getItem_Card()
    {
      return $this->Item_Card;
    }

    /**
     * @param Item_Card[] $Item_Card
     * @return \Rosetta\NAV\WS\ItemCard\Item_Card_List
     */
    public function setItem_Card(array $Item_Card)
    {
      $this->Item_Card = $Item_Card;
      return $this;
    }

}
