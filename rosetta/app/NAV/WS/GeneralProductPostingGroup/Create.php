<?php

namespace Rosetta\NAV\WS\GeneralProductPostingGroup;

class Create
{

    /**
     * @var Gen_Product_Posting_Groups $Gen_Product_Posting_Groups
     */
    protected $Gen_Product_Posting_Groups = null;

    /**
     * @param Gen_Product_Posting_Groups $Gen_Product_Posting_Groups
     */
    public function __construct($Gen_Product_Posting_Groups)
    {
      $this->Gen_Product_Posting_Groups = $Gen_Product_Posting_Groups;
    }

    /**
     * @return Gen_Product_Posting_Groups
     */
    public function getGen_Product_Posting_Groups()
    {
      return $this->Gen_Product_Posting_Groups;
    }

    /**
     * @param Gen_Product_Posting_Groups $Gen_Product_Posting_Groups
     * @return \Rosetta\NAV\WS\GeneralProductPostingGroup\Create
     */
    public function setGen_Product_Posting_Groups($Gen_Product_Posting_Groups)
    {
      $this->Gen_Product_Posting_Groups = $Gen_Product_Posting_Groups;
      return $this;
    }

}
