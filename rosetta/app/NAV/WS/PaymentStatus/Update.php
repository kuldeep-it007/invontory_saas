<?php

namespace Rosetta\NAV\WS\PaymentStatus;

class Update
{

    /**
     * @var Payment_Status $Payment_Status
     */
    protected $Payment_Status = null;

    /**
     * @param Payment_Status $Payment_Status
     */
    public function __construct($Payment_Status)
    {
      $this->Payment_Status = $Payment_Status;
    }

    /**
     * @return Payment_Status
     */
    public function getPayment_Status()
    {
      return $this->Payment_Status;
    }

    /**
     * @param Payment_Status $Payment_Status
     * @return \Rosetta\NAV\WS\PaymentStatus\Update
     */
    public function setPayment_Status($Payment_Status)
    {
      $this->Payment_Status = $Payment_Status;
      return $this;
    }

}
