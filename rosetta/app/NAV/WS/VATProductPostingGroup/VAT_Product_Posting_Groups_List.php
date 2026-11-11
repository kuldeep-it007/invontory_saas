<?php

namespace Rosetta\NAV\WS\VATProductPostingGroup;

class VAT_Product_Posting_Groups_List
{

    /**
     * @var VAT_Product_Posting_Groups[] $VAT_Product_Posting_Groups
     */
    protected $VAT_Product_Posting_Groups = null;

    /**
     * @param VAT_Product_Posting_Groups[] $VAT_Product_Posting_Groups
     */
    public function __construct(array $VAT_Product_Posting_Groups)
    {
      $this->VAT_Product_Posting_Groups = $VAT_Product_Posting_Groups;
    }

    /**
     * @return VAT_Product_Posting_Groups[]
     */
    public function getVAT_Product_Posting_Groups()
    {
      return $this->VAT_Product_Posting_Groups;
    }

    /**
     * @param VAT_Product_Posting_Groups[] $VAT_Product_Posting_Groups
     * @return \Rosetta\NAV\WS\VATProductPostingGroup\VAT_Product_Posting_Groups_List
     */
    public function setVAT_Product_Posting_Groups(array $VAT_Product_Posting_Groups)
    {
      $this->VAT_Product_Posting_Groups = $VAT_Product_Posting_Groups;
      return $this;
    }

}
