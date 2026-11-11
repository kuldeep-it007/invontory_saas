<?php

namespace Rosetta\NAV\WS\ItemCard\Picture;

class SetItemPicture
{

    /**
     * @var string $itemNo
     */
    protected $itemNo = null;

    /**
     * @var string $picture
     */
    protected $picture = null;

    /**
     * @param string $itemNo
     * @param string $picture
     */
    public function __construct($itemNo, $picture)
    {
      $this->itemNo = $itemNo;
      $this->picture = $picture;
    }

    /**
     * @return string
     */
    public function getItemNo()
    {
      return $this->itemNo;
    }

    /**
     * @param string $itemNo
     * @return \Rosetta\NAV\WS\ItemCard\Picture\SetItemPicture
     */
    public function setItemNo($itemNo)
    {
      $this->itemNo = $itemNo;
      return $this;
    }

    /**
     * @return string
     */
    public function getPicture()
    {
      return $this->picture;
    }

    /**
     * @param string $picture
     * @return \Rosetta\NAV\WS\ItemCard\Picture\SetItemPicture
     */
    public function setPicture($picture)
    {
      $this->picture = $picture;
      return $this;
    }

}
