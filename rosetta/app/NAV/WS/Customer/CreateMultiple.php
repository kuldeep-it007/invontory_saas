<?php

namespace Rosetta\NAV\WS\Customer;

class CreateMultiple
{

    /**
     * @var Customer_Card_List $Customer_Card_List
     */
    protected $Customer_Card_List = null;

    /**
     * @param Customer_Card_List $Customer_Card_List
     */
    public function __construct($Customer_Card_List)
    {
      $this->Customer_Card_List = $Customer_Card_List;
    }

    /**
     * @return Customer_Card_List
     */
    public function getCustomer_Card_List()
    {
      return $this->Customer_Card_List;
    }

    /**
     * @param Customer_Card_List $Customer_Card_List
     * @return \Rosetta\NAV\WS\Customer\CreateMultiple
     */
    public function setCustomer_Card_List($Customer_Card_List)
    {
      $this->Customer_Card_List = $Customer_Card_List;
      return $this;
    }

}
