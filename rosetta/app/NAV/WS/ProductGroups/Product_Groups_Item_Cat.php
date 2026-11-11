<?php

namespace Rosetta\NAV\WS\ProductGroups;

class Product_Groups_Item_Cat
{

    /**
     * @var string $Key
     */
    protected $Key = null;

    /**
     * @var string $Item_Category_Code
     */
    protected $Item_Category_Code = null;

    /**
     * @var string $Code
     */
    protected $Code = null;

    /**
     * @var string $Warehouse_Class_Code
     */
    protected $Warehouse_Class_Code = null;

    /**
     * @var string $Description
     */
    protected $Description = null;

    
    public function __construct()
    {
    
    }

    /**
     * @return string
     */
    public function getKey()
    {
      return $this->Key;
    }

    /**
     * @param string $Key
     * @return \Rosetta\NAV\WS\ProductGroups\Product_Groups_Item_Cat
     */
    public function setKey($Key)
    {
      $this->Key = $Key;
      return $this;
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
     * @return \Rosetta\NAV\WS\ProductGroups\Product_Groups_Item_Cat
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
     * @return \Rosetta\NAV\WS\ProductGroups\Product_Groups_Item_Cat
     */
    public function setCode($Code)
    {
      $this->Code = $Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getWarehouse_Class_Code()
    {
      return $this->Warehouse_Class_Code;
    }

    /**
     * @param string $Warehouse_Class_Code
     * @return \Rosetta\NAV\WS\ProductGroups\Product_Groups_Item_Cat
     */
    public function setWarehouse_Class_Code($Warehouse_Class_Code)
    {
      $this->Warehouse_Class_Code = $Warehouse_Class_Code;
      return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
      return $this->Description;
    }

    /**
     * @param string $Description
     * @return \Rosetta\NAV\WS\ProductGroups\Product_Groups_Item_Cat
     */
    public function setDescription($Description)
    {
      $this->Description = $Description;
      return $this;
    }

}
