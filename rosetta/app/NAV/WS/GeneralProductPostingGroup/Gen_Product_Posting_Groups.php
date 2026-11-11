<?php

namespace Rosetta\NAV\WS\GeneralProductPostingGroup;

class Gen_Product_Posting_Groups
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
     * @var string $Def_VAT_Prod_Posting_Group
     */
    protected $Def_VAT_Prod_Posting_Group = null;

    /**
     * @var boolean $Auto_Insert_Default
     */
    protected $Auto_Insert_Default = null;

    
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
     * @return \Rosetta\NAV\WS\GeneralProductPostingGroup\Gen_Product_Posting_Groups
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
     * @return \Rosetta\NAV\WS\GeneralProductPostingGroup\Gen_Product_Posting_Groups
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
     * @return \Rosetta\NAV\WS\GeneralProductPostingGroup\Gen_Product_Posting_Groups
     */
    public function setDescription($Description)
    {
      $this->Description = $Description;
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
     * @return \Rosetta\NAV\WS\GeneralProductPostingGroup\Gen_Product_Posting_Groups
     */
    public function setDef_VAT_Prod_Posting_Group($Def_VAT_Prod_Posting_Group)
    {
      $this->Def_VAT_Prod_Posting_Group = $Def_VAT_Prod_Posting_Group;
      return $this;
    }

    /**
     * @return boolean
     */
    public function getAuto_Insert_Default()
    {
      return $this->Auto_Insert_Default;
    }

    /**
     * @param boolean $Auto_Insert_Default
     * @return \Rosetta\NAV\WS\GeneralProductPostingGroup\Gen_Product_Posting_Groups
     */
    public function setAuto_Insert_Default($Auto_Insert_Default)
    {
      $this->Auto_Insert_Default = $Auto_Insert_Default;
      return $this;
    }

}
