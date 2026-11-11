<?php

namespace Rosetta\NAV\WS\BankReceipt;

class ReadByRecId
{

    /**
     * @var string $CurrentJnlBatchName
     */
    protected $CurrentJnlBatchName = null;

    /**
     * @var string $recId
     */
    protected $recId = null;

    /**
     * @param string $CurrentJnlBatchName
     * @param string $recId
     */
    public function __construct($CurrentJnlBatchName, $recId)
    {
      $this->CurrentJnlBatchName = $CurrentJnlBatchName;
      $this->recId = $recId;
    }

    /**
     * @return string
     */
    public function getCurrentJnlBatchName()
    {
      return $this->CurrentJnlBatchName;
    }

    /**
     * @param string $CurrentJnlBatchName
     * @return \Rosetta\NAV\WS\BankReceipt\ReadByRecId
     */
    public function setCurrentJnlBatchName($CurrentJnlBatchName)
    {
      $this->CurrentJnlBatchName = $CurrentJnlBatchName;
      return $this;
    }

    /**
     * @return string
     */
    public function getRecId()
    {
      return $this->recId;
    }

    /**
     * @param string $recId
     * @return \Rosetta\NAV\WS\BankReceipt\ReadByRecId
     */
    public function setRecId($recId)
    {
      $this->recId = $recId;
      return $this;
    }

}
