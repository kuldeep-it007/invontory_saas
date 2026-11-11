<?php

namespace Rosetta\NAV\WS\VATProductPostingGroup;

class Delete_Result
{

    /**
     * @var boolean $Delete_Result
     */
    protected $Delete_Result = null;

    /**
     * @param boolean $Delete_Result
     */
    public function __construct($Delete_Result)
    {
      $this->Delete_Result = $Delete_Result;
    }

    /**
     * @return boolean
     */
    public function getDelete_Result()
    {
      return $this->Delete_Result;
    }

    /**
     * @param boolean $Delete_Result
     * @return \Rosetta\NAV\WS\VATProductPostingGroup\Delete_Result
     */
    public function setDelete_Result($Delete_Result)
    {
      $this->Delete_Result = $Delete_Result;
      return $this;
    }

}
