<?php

namespace Rosetta\NAV\WS\SalesOrder;

class GetRecIdFromKey_Result
{

    /**
     * @var string $GetRecIdFromKey_Result
     */
    protected $GetRecIdFromKey_Result = null;

    /**
     * @param string $GetRecIdFromKey_Result
     */
    public function __construct($GetRecIdFromKey_Result)
    {
      $this->GetRecIdFromKey_Result = $GetRecIdFromKey_Result;
    }

    /**
     * @return string
     */
    public function getGetRecIdFromKey_Result()
    {
      return $this->GetRecIdFromKey_Result;
    }

    /**
     * @param string $GetRecIdFromKey_Result
     * @return \Rosetta\NAV\WS\SalesOrder\GetRecIdFromKey_Result
     */
    public function setGetRecIdFromKey_Result($GetRecIdFromKey_Result)
    {
      $this->GetRecIdFromKey_Result = $GetRecIdFromKey_Result;
      return $this;
    }

}
