<?php

namespace Rosetta\NAV\WS\GeneralProductPostingGroup;

class CreateMultiple_Result
{

    /**
     * @var Gen_Product_Posting_Groups_List $Gen_Product_Posting_Groups_List
     */
    protected $Gen_Product_Posting_Groups_List = null;

    /**
     * @param Gen_Product_Posting_Groups_List $Gen_Product_Posting_Groups_List
     */
    public function __construct($Gen_Product_Posting_Groups_List)
    {
      $this->Gen_Product_Posting_Groups_List = $Gen_Product_Posting_Groups_List;
    }

    /**
     * @return Gen_Product_Posting_Groups_List
     */
    public function getGen_Product_Posting_Groups_List()
    {
      return $this->Gen_Product_Posting_Groups_List;
    }

    /**
     * @param Gen_Product_Posting_Groups_List $Gen_Product_Posting_Groups_List
     * @return \Rosetta\NAV\WS\GeneralProductPostingGroup\CreateMultiple_Result
     */
    public function setGen_Product_Posting_Groups_List($Gen_Product_Posting_Groups_List)
    {
      $this->Gen_Product_Posting_Groups_List = $Gen_Product_Posting_Groups_List;
      return $this;
    }

}
