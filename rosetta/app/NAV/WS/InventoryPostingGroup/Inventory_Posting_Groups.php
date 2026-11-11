<?php

namespace Rosetta\NAV\WS\InventoryPostingGroup;

class Inventory_Posting_Groups
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
     * @return \Rosetta\NAV\WS\InventoryPostingGroup\Inventory_Posting_Groups
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
     * @return \Rosetta\NAV\WS\InventoryPostingGroup\Inventory_Posting_Groups
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
     * @return \Rosetta\NAV\WS\InventoryPostingGroup\Inventory_Posting_Groups
     */
    public function setDescription($Description)
    {
      $this->Description = $Description;
      return $this;
    }

}
