<?php

namespace Rosetta\NAV\WS\PaymentStatus;

class UpdateMultiple
{

    /**
     * @var Payment_Status_List $Payment_Status_List
     */
    protected $Payment_Status_List = null;

    /**
     * @param Payment_Status_List $Payment_Status_List
     */
    public function __construct($Payment_Status_List)
    {
      $this->Payment_Status_List = $Payment_Status_List;
    }

    /**
     * @return Payment_Status_List
     */
    public function getPayment_Status_List()
    {
      return $this->Payment_Status_List;
    }

    /**
     * @param Payment_Status_List $Payment_Status_List
     * @return \Rosetta\NAV\WS\PaymentStatus\UpdateMultiple
     */
    public function setPayment_Status_List($Payment_Status_List)
    {
      $this->Payment_Status_List = $Payment_Status_List;
      return $this;
    }

}
