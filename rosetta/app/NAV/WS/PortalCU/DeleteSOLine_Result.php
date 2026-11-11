<?php

namespace Rosetta\NAV\WS\PortalCU;

class DeleteSOLine_Result
{

    /**
     * @var string $return_value
     */
    protected $return_value = null;

    /**
     * @param string $return_value
     */
    public function __construct($return_value)
    {
      $this->return_value = $return_value;
    }

    /**
     * @return string
     */
    public function getReturn_value()
    {
      return $this->return_value;
    }

    /**
     * @param string $return_value
     * @return \Rosetta\NAV\WS\PortalCU\DeleteSOLine_Result
     */
    public function setReturn_value($return_value)
    {
      $this->return_value = $return_value;
      return $this;
    }

}
