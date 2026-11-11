<?php
namespace Rosetta\Services\PaymentGateway;

use Illuminate\Support\Manager;
use Rosetta\Services\PaymentGateway\Drivers\Fatoorah;
use Rosetta\Services\PaymentGateway\Drivers\Telr;

class PaymentGatewayManager extends Manager
{
    public function getDefaultDriver()
    {
        return "null";
    }

    public function createTelrDriver()
    {
        return new Telr;
    }
    public function createFatoorahDriver()
    {
        return new Fatoorah;
    }

    /**
     * return gateway to use
     *
     * @param String $name
     * @return \Rosetta\Services\PaymentGateway\Drivers\DriverInterface
     */
    public function gateway($name = null)
    {
        return $this->driver($name);
    }
}