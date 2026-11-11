<?php

namespace Rosetta\NAV\WS\PaymentStatus;

class Update_Result
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
     * @return \Rosetta\NAV\WS\PaymentStatus\Update_Result
     */
    public function setPayment_Status($Payment_Status)
    {
      $this->Payment_Status = $Payment_Status;
      return $this;
    }

}
