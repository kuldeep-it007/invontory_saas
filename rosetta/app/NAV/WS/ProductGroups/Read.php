<?php

namespace Rosetta\NAV\WS\ProductGroups;

class Read
{

    /**
     * @var string $Item_Category_Code
     */
    protected $Item_Category_Code = null;

    /**
     * @var string $Code
     */
    protected $Code = null;

    /**
     * @param string $Item_Category_Code
     * @param string $Code
     */
    public function __construct($Item_Category_Code, $Code)
    {
      $this->Item_Category_Code = $Item_Category_Code;
      $this->Code = $Code;
    }

    /**
     * @return string
     */
    public function getItem_Category_Code()
    {
      return $this->Item_Category_Code;
    }

    /**
     * @param string $Item_Category_Code
     * @return \Rosetta\NAV\WS\ProductGroups\Read
     */
    public function setItem_Category_Code($Item_Category_Code)
    {
      $this->Item_Category_Code = $Item_Category_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getCode()
    {
      return $this->Code;
    }

    /**
     * @param string $Code
     * @return \Rosetta\NAV\WS\ProductGroups\Read
     */
    public function setCode($Code)
    {
      $this->Code = $Code;
      return $this;
    }

}
