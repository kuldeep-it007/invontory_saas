<?php

namespace Rosetta\NAV\WS\ItemCard;

class CreateMultiple_Result
{

    /**
     * @var Item_Card_List $Item_Card_List
     */
    protected $Item_Card_List = null;

    /**
     * @param Item_Card_List $Item_Card_List
     */
    public function __construct($Item_Card_List)
    {
      $this->Item_Card_List = $Item_Card_List;
    }

    /**
     * @return Item_Card_List
     */
    public function getItem_Card_List()
    {
      return $this->Item_Card_List;
    }

    /**
     * @param Item_Card_List $Item_Card_List
     * @return \Rosetta\NAV\WS\ItemCard\CreateMultiple_Result
     */
    public function setItem_Card_List($Item_Card_List)
    {
      $this->Item_Card_List = $Item_Card_List;
      return $this;
    }

}
