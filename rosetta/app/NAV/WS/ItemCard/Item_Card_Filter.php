<?php

namespace Rosetta\NAV\WS\ItemCard;

class Item_Card_Filter
{

    /**
     * @var Item_Card_Fields $Field
     */
    protected $Field = null;

    /**
     * @var string $Criteria
     */
    protected $Criteria = null;

    /**
     * @param Item_Card_Fields $Field
     * @param string $Criteria
     */
    public function __construct($Field, $Criteria)
    {
      $this->Field = $Field;
      $this->Criteria = $Criteria;
    }

    /**
     * @return Item_Card_Fields
     */
    public function getField()
    {
      return $this->Field;
    }

    /**
     * @param Item_Card_Fields $Field
     * @return \Rosetta\NAV\WS\ItemCard\Item_Card_Filter
     */
    public function setField($Field)
    {
      $this->Field = $Field;
      return $this;
    }

    /**
     * @return string
     */
    public function getCriteria()
    {
      return $this->Criteria;
    }

    /**
     * @param string $Criteria
     * @return \Rosetta\NAV\WS\ItemCard\Item_Card_Filter
     */
    public function setCriteria($Criteria)
    {
      $this->Criteria = $Criteria;
      return $this;
    }

}
