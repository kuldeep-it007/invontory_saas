<?php

namespace Rosetta\NAV\WS\VATProductPostingGroup;

class ReadByRecId_Result
{

    /**
     * @var VAT_Product_Posting_Groups $VAT_Product_Posting_Groups
     */
    protected $VAT_Product_Posting_Groups = null;

    /**
     * @param VAT_Product_Posting_Groups $VAT_Product_Posting_Groups
     */
    public function __construct($VAT_Product_Posting_Groups)
    {
      $this->VAT_Product_Posting_Groups = $VAT_Product_Posting_Groups;
    }

    /**
     * @return VAT_Product_Posting_Groups
     */
    public function getVAT_Product_Posting_Groups()
    {
      return $this->VAT_Product_Posting_Groups;
    }

    /**
     * @param VAT_Product_Posting_Groups $VAT_Product_Posting_Groups
     * @return \Rosetta\NAV\WS\VATProductPostingGroup\ReadByRecId_Result
     */
    public function setVAT_Product_Posting_Groups($VAT_Product_Posting_Groups)
    {
      $this->VAT_Product_Posting_Groups = $VAT_Product_Posting_Groups;
      return $this;
    }

}
