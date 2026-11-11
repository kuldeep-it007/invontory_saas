<?php
namespace Rosetta\Services\Notifications\SMS;

use Illuminate\Support\Manager;
use Rosetta\Services\Notifications\SMS\Drivers\BhashDriver;
use Rosetta\Services\Notifications\SMS\Drivers\NullDriver;

class SMSGatewayManager extends Manager
{
    public function getDefaultDriver()
    {
        return "null";
    }

    public function createBhashDriver()
    {
        return new BhashDriver;
    }

    public function createNullDriver()
    {
        return new NullDriver;
    }

    /**
     * return gateway to use
     *
     * @param String $name
     * @return \Rosetta\Services\Notifications\SMS\Drivers\SMSDriveInterface
     */
    public function gateway($name = null)
    {
        return $this->driver($name);
    }
}