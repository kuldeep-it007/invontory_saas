<?php

namespace Rosetta\NAV\WS\GeneralProductPostingGroup;

class Gen_Product_Posting_Groups_Filter
{

    /**
     * @var Gen_Product_Posting_Groups_Fields $Field
     */
    protected $Field = null;

    /**
     * @var string $Criteria
     */
    protected $Criteria = null;

    /**
     * @param Gen_Product_Posting_Groups_Fields $Field
     * @param string $Criteria
     */
    public function __construct($Field, $Criteria)
    {
      $this->Field = $Field;
      $this->Criteria = $Criteria;
    }

    /**
     * @return Gen_Product_Posting_Groups_Fields
     */
    public function getField()
    {
      return $this->Field;
    }

    /**
     * @param Gen_Product_Posting_Groups_Fields $Field
     * @return \Rosetta\NAV\WS\GeneralProductPostingGroup\Gen_Product_Posting_Groups_Filter
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
     * @return \Rosetta\NAV\WS\GeneralProductPostingGroup\Gen_Product_Posting_Groups_Filter
     */
    public function setCriteria($Criteria)
    {
      $this->Criteria = $Criteria;
      return $this;
    }

}
