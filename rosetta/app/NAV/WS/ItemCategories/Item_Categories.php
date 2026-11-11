<?php

namespace Rosetta\NAV\WS\ItemCategories;

class Item_Categories
{

    /**
     * @var string $Key
     */
    protected $Key = null;

    /**
     * @var string $Code
     */
    protected $Code = null;

    /**
     * @var string $Description
     */
    protected $Description = null;

    /**
     * @var string $Def_Gen_Prod_Posting_Group
     */
    protected $Def_Gen_Prod_Posting_Group = null;

    /**
     * @var string $Def_Inventory_Posting_Group
     */
    protected $Def_Inventory_Posting_Group = null;

    /**
     * @var string $Def_VAT_Prod_Posting_Group
     */
    protected $Def_VAT_Prod_Posting_Group = null;

    /**
     * @var Def_Costing_Method $Def_Costing_Method
     */
    protected $Def_Costing_Method = null;

    
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
     * @return \Rosetta\NAV\WS\ItemCategories\Item_Categories
     */
    public function setKey($Key)
    {
      $this->Key = $Key;
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
     * @return \Rosetta\NAV\WS\ItemCategories\Item_Categories
     */
    public function setCode($Code)
    {
      $this->Code = $Code;
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
     * @return \Rosetta\NAV\WS\ItemCategories\Item_Categories
     */
    public function setDescription($Description)
    {
      $this->Description = $Description;
      return $this;
    }

    /**
     * @return string
     */
    public function getDef_Gen_Prod_Posting_Group()
    {
      return $this->Def_Gen_Prod_Posting_Group;
    }

    /**
     * @param string $Def_Gen_Prod_Posting_Group
     * @return \Rosetta\NAV\WS\ItemCategories\Item_Categories
     */
    public function setDef_Gen_Prod_Posting_Group($Def_Gen_Prod_Posting_Group)
    {
      $this->Def_Gen_Prod_Posting_Group = $Def_Gen_Prod_Posting_Group;
      return $this;
    }

    /**
     * @return string
     */
    public function getDef_Inventory_Posting_Group()
    {
      return $this->Def_Inventory_Posting_Group;
    }

    /**
     * @param string $Def_Inventory_Posting_Group
     * @return \Rosetta\NAV\WS\ItemCategories\Item_Categories
     */
    public function setDef_Inventory_Posting_Group($Def_Inventory_Posting_Group)
    {
      $this->Def_Inventory_Posting_Group = $Def_Inventory_Posting_Group;
      return $this;
    }

    /**
     * @return string
     */
    public function getDef_VAT_Prod_Posting_Group()
    {
      return $this->Def_VAT_Prod_Posting_Group;
    }

    /**
     * @param string $Def_VAT_Prod_Posting_Group
     * @return \Rosetta\NAV\WS\ItemCategories\Item_Categories
     */
    public function setDef_VAT_Prod_Posting_Group($Def_VAT_Prod_Posting_Group)
    {
      $this->Def_VAT_Prod_Posting_Group = $Def_VAT_Prod_Posting_Group;
      return $this;
    }

    /**
     * @return Def_Costing_Method
     */
    public function getDef_Costing_Method()
    {
      return $this->Def_Costing_Method;
    }

    /**
     * @param Def_Costing_Method $Def_Costing_Method
     * @return \Rosetta\NAV\WS\ItemCategories\Item_Categories
     */
    public function setDef_Costing_Method($Def_Costing_Method)
    {
      $this->Def_Costing_Method = $Def_Costing_Method;
      return $this;
    }

}
