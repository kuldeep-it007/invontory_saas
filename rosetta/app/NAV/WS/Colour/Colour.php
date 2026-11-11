<?php

namespace Rosetta\NAV\WS\Colour;

class Colour
{

    /**
     * @var string $Key
     */
    protected $Key = null;

    /**
     * @var string $Collection_Code
     */
    protected $Collection_Code = null;

    /**
     * @var string $Code
     */
    protected $Code = null;

    
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
     * @return \Rosetta\NAV\WS\Colour\Colour
     */
    public function setKey($Key)
    {
      $this->Key = $Key;
      return $this;
    }

    /**
     * @return string
     */
    public function getCollection_Code()
    {
      return $this->Collection_Code;
    }

    /**
     * @param string $Collection_Code
     * @return \Rosetta\NAV\WS\Colour\Colour
     */
    public function setCollection_Code($Collection_Code)
    {
      $this->Collection_Code = $Collection_Code;
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
     * @return \Rosetta\NAV\WS\Colour\Colour
     */
    public function setCode($Code)
    {
      $this->Code = $Code;
      return $this;
    }

}
