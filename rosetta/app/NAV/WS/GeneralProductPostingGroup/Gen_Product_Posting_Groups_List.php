<?php

namespace Rosetta\NAV\WS\GeneralProductPostingGroup;

class Gen_Product_Posting_Groups_List
{

    /**
     * @var Gen_Product_Posting_Groups[] $Gen_Product_Posting_Groups
     */
    protected $Gen_Product_Posting_Groups = null;

    /**
     * @param Gen_Product_Posting_Groups[] $Gen_Product_Posting_Groups
     */
    public function __construct(array $Gen_Product_Posting_Groups)
    {
      $this->Gen_Product_Posting_Groups = $Gen_Product_Posting_Groups;
    }

    /**
     * @return Gen_Product_Posting_Groups[]
     */
    public function getGen_Product_Posting_Groups()
    {
      return $this->Gen_Product_Posting_Groups;
    }

    /**
     * @param Gen_Product_Posting_Groups[] $Gen_Product_Posting_Groups
     * @return \Rosetta\NAV\WS\GeneralProductPostingGroup\Gen_Product_Posting_Groups_List
     */
    public function setGen_Product_Posting_Groups(array $Gen_Product_Posting_Groups)
    {
      $this->Gen_Product_Posting_Groups = $Gen_Product_Posting_Groups;
      return $this;
    }

}
