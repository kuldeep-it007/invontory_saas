<?php
namespace Rosetta\Services\PaymentGateway\Drivers;

use Rosetta\Classes\Settings;
use Rosetta\Services\PaymentGateway\Drivers\DriverInterface;

class NullDriver implements DriverInterface
{
    public function init(Settings $config)
    {
        return $this;
    }
}