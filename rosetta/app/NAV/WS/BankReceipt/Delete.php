<?php

namespace Rosetta\NAV\WS\BankReceipt;

class Delete
{

    /**
     * @var string $CurrentJnlBatchName
     */
    protected $CurrentJnlBatchName = null;

    /**
     * @var string $Key
     */
    protected $Key = null;

    /**
     * @param string $CurrentJnlBatchName
     * @param string $Key
     */
    public function __construct($CurrentJnlBatchName, $Key)
    {
      $this->CurrentJnlBatchName = $CurrentJnlBatchName;
      $this->Key = $Key;
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
     * @return \Rosetta\NAV\WS\BankReceipt\Delete
     */
    public function setCurrentJnlBatchName($CurrentJnlBatchName)
    {
      $this->CurrentJnlBatchName = $CurrentJnlBatchName;
      return $this;
    }

    /**
     * @return string
     */
    public function getKey()
    {
      return $this->Key;
    }

    /**
     * @param string $Key
     * @return \Rosetta\NAV\WS\BankReceipt\Delete
     */
    public function setKey($Key)
    {
      $this->Key = $Key;
      return $this;
    }

}
