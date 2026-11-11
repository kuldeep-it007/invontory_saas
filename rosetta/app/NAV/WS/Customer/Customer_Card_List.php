<?php

namespace Rosetta\NAV\WS\Customer;

class Customer_Card_List
{

    /**
     * @var Customer_Card[] $Customer_Card
     */
    protected $Customer_Card = null;

    /**
     * @param Customer_Card[] $Customer_Card
     */
    public function __construct(array $Customer_Card)
    {
      $this->Customer_Card = $Customer_Card;
    }

    /**
     * @return Customer_Card[]
     */
    public function getCustomer_Card()
    {
      return $this->Customer_Card;
    }

    /**
     * @param Customer_Card[] $Customer_Card
     * @return \Rosetta\NAV\WS\Customer\Customer_Card_List
     */
    public function setCustomer_Card(array $Customer_Card)
    {
      $this->Customer_Card = $Customer_Card;
      return $this;
    }

}
