<?php

namespace Rosetta\NAV\WS\VATProductPostingGroup;

class UpdateMultiple_Result
{

    /**
     * @var VAT_Product_Posting_Groups_List $VAT_Product_Posting_Groups_List
     */
    protected $VAT_Product_Posting_Groups_List = null;

    /**
     * @param VAT_Product_Posting_Groups_List $VAT_Product_Posting_Groups_List
     */
    public function __construct($VAT_Product_Posting_Groups_List)
    {
      $this->VAT_Product_Posting_Groups_List = $VAT_Product_Posting_Groups_List;
    }

    /**
     * @return VAT_Product_Posting_Groups_List
     */
    public function getVAT_Product_Posting_Groups_List()
    {
      return $this->VAT_Product_Posting_Groups_List;
    }

    /**
     * @param VAT_Product_Posting_Groups_List $VAT_Product_Posting_Groups_List
     * @return \Rosetta\NAV\WS\VATProductPostingGroup\UpdateMultiple_Result
     */
    public function setVAT_Product_Posting_Groups_List($VAT_Product_Posting_Groups_List)
    {
      $this->VAT_Product_Posting_Groups_List = $VAT_Product_Posting_Groups_List;
      return $this;
    }

}
