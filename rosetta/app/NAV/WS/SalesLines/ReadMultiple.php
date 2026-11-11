<?php

namespace Rosetta\NAV\WS\SalesLines;

class ReadMultiple
{

    /**
     * @var SalesLines_Filter $filter
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
     * @param SalesLines_Filter $filter
     * @param string $bookmarkKey
     * @param int $setSize
     */
    public function __construct($filter, $bookmarkKey, $setSize)
    {
      $this->filter = $filter;
      $this->bookmarkKey = $bookmarkKey;
      $this->setSize = $setSize;
    }

    /**
     * @return SalesLines_Filter
     */
    public function getFilter()
    {
      return $this->filter;
    }

    /**
     * @param SalesLines_Filter $filter
     * @return \Rosetta\NAV\WS\SalesLines\ReadMultiple
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
     * @return \Rosetta\NAV\WS\SalesLines\ReadMultiple
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
     * @return \Rosetta\NAV\WS\SalesLines\ReadMultiple
     */
    public function setSetSize($setSize)
    {
      $this->setSize = $setSize;
      return $this;
    }

}
