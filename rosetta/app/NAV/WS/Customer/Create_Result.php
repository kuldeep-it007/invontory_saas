<?php

namespace Rosetta\NAV\WS\Customer;

class Create_Result
{

    /**
     * @var Customer_Card $Customer_Card
     */
    protected $Customer_Card = null;

    /**
     * @param Customer_Card $Customer_Card
     */
    public function __construct($Customer_Card)
    {
      $this->Customer_Card = $Customer_Card;
    }

    /**
     * @return Customer_Card
     */
    public function getCustomer_Card()
    {
      return $this->Customer_Card;
    }

    /**
     * @param Customer_Card $Customer_Card
     * @return \Rosetta\NAV\WS\Customer\Create_Result
     */
    public function setCustomer_Card($Customer_Card)
    {
      $this->Customer_Card = $Customer_Card;
      return $this;
    }

}
