<?php

namespace Rosetta\NAV\WS\BankReceipt;

class ReadMultiple
{

    /**
     * @var string $CurrentJnlBatchName
     */
    protected $CurrentJnlBatchName = null;

    /**
     * @var bankreceipt_Filter $filter
     */
    protected $filter = null;

    /**
     * @var string $bookmarkKey
     */
    protected $bookmarkKey = null;

    /**
     * @var int $setSize
     */
    protected $setSize = null;

    /**
     * @param string $CurrentJnlBatchName
     * @param bankreceipt_Filter $filter
     * @param string $bookmarkKey
     * @param int $setSize
     */
    public function __construct($CurrentJnlBatchName, $filter, $bookmarkKey, $setSize)
    {
      $this->CurrentJnlBatchName = $CurrentJnlBatchName;
      $this->filter = $filter;
      $this->bookmarkKey = $bookmarkKey;
      $this->setSize = $setSize;
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
     * @return \Rosetta\NAV\WS\BankReceipt\ReadMultiple
     */
    public function setCurrentJnlBatchName($CurrentJnlBatchName)
    {
      $this->CurrentJnlBatchName = $CurrentJnlBatchName;
      return $this;
    }

    /**
     * @return bankreceipt_Filter
     */
    public function getFilter()
    {
      return $this->filter;
    }

    /**
     * @param bankreceipt_Filter $filter
     * @return \Rosetta\NAV\WS\BankReceipt\ReadMultiple
     */
    public function setFilter($filter)
    {
      $this->filter = $filter;
      return $this;
    }

    /**
     * @return string
     */
    public function getBookmarkKey()
    {
      return $this->bookmarkKey;
    }

    /**
     * @param string $bookmarkKey
     * @return \Rosetta\NAV\WS\BankReceipt\ReadMultiple
     */
    public function setBookmarkKey($bookmarkKey)
    {
      $this->bookmarkKey = $bookmarkKey;
      return $this;
    }

    /**
     * @return int
     */
    public function getSetSize()
    {
      return $this->setSize;
    }

    /**
     * @param int $setSize
     * @return \Rosetta\NAV\WS\BankReceipt\ReadMultiple
     */
    public function setSetSize($setSize)
    {
      $this->setSize = $setSize;
      return $this;
    }

}
