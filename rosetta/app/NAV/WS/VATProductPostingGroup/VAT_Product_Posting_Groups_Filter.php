<?php

namespace Rosetta\NAV\WS\VATProductPostingGroup;

class VAT_Product_Posting_Groups_Filter
{

    /**
     * @var VAT_Product_Posting_Groups_Fields $Field
     */
    protected $Field = null;

    /**
     * @var string $Criteria
     */
    protected $Criteria = null;

    /**
     * @param VAT_Product_Posting_Groups_Fields $Field
     * @param string $Criteria
     */
    public function __construct($Field, $Criteria)
    {
      $this->Field = $Field;
      $this->Criteria = $Criteria;
    }

    /**
     * @return VAT_Product_Posting_Groups_Fields
     */
    public function getField()
    {
      return $this->Field;
    }

    /**
     * @param VAT_Product_Posting_Groups_Fields $Field
     * @return \Rosetta\NAV\WS\VATProductPostingGroup\VAT_Product_Posting_Groups_Filter
     */
    public function setField($Field)
    {
      $this->Field = $Field;
      return $this;
    }

    /**
     * @return string
     */
    public function getCriteria()
    {
      return $this->Criteria;
    }

    /**
     * @param string $Criteria
     * @return \Rosetta\NAV\WS\VATProductPostingGroup\VAT_Product_Posting_Groups_Filter
     */
    public function setCriteria($Criteria)
    {
      $this->Criteria = $Criteria;
      return $this;
    }

}
