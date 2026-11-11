<?php

namespace Rosetta\NAV\WS\VATProductPostingGroup;

class ReadMultiple_Result
{

    /**
     * @var VAT_Product_Posting_Groups_List $ReadMultiple_Result
     */
    protected $ReadMultiple_Result = null;

    /**
     * @param VAT_Product_Posting_Groups_List $ReadMultiple_Result
     */
    public function __construct($ReadMultiple_Result)
    {
      $this->ReadMultiple_Result = $ReadMultiple_Result;
    }

    /**
     * @return VAT_Product_Posting_Groups_List
     */
    public function getReadMultiple_Result()
    {
      return $this->ReadMultiple_Result;
    }

    /**
     * @param VAT_Product_Posting_Groups_List $ReadMultiple_Result
     * @return \Rosetta\NAV\WS\VATProductPostingGroup\ReadMultiple_Result
     */
    public function setReadMultiple_Result($ReadMultiple_Result)
    {
      $this->ReadMultiple_Result = $ReadMultiple_Result;
      return $this;
    }

}
