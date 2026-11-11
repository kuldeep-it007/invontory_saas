<?php

namespace Rosetta\NAV\WS\ILE;

class ILE_Filter
{

    /**
     * @var ILE_Fields $Field
     */
    protected $Field = null;

    /**
     * @var string $Criteria
     */
    protected $Criteria = null;

    /**
     * @param ILE_Fields $Field
     * @param string $Criteria
     */
    public function __construct($Field, $Criteria)
    {
      $this->Field = $Field;
      $this->Criteria = $Criteria;
    }

    /**
     * @return ILE_Fields
     */
    public function getField()
    {
      return $this->Field;
    }

    /**
     * @param ILE_Fields $Field
     * @return \Rosetta\NAV\WS\ILE\ILE_Filter
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
     * @return \Rosetta\NAV\WS\ILE\ILE_Filter
     */
    public function setCriteria($Criteria)
    {
      $this->Criteria = $Criteria;
      return $this;
    }

}
