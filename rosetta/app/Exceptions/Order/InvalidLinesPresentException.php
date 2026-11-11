<?php
namespace Rosetta\Exceptions\Order;

use Exception;
class InvalidLinesPresentException extends Exception
{
    protected $invalid = [];
    public function setInvalid($invalid)
    {
        $this->invalid = $invalid;
    }
    public function getInvalid()
    {
        return $this->invalid;
    }
}