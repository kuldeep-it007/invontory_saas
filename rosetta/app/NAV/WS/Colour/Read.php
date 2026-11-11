<?php

namespace Rosetta\NAV\WS\Colour;

class Read
{

    /**
     * @var string $Collection_Code
     */
    protected $Collection_Code = null;

    /**
     * @var string $Code
     */
    protected $Code = null;

    /**
     * @param string $Collection_Code
     * @param string $Code
     */
    public function __construct($Collection_Code, $Code)
    {
      $this->Collection_Code = $Collection_Code;
      $this->Code = $Code;
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
     * @return \Rosetta\NAV\WS\Colour\Read
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
     * @return \Rosetta\NAV\WS\Colour\Read
     */
    public function setCode($Code)
    {
      $this->Code = $Code;
      return $this;
    }

}
