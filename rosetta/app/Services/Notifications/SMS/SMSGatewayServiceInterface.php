<?php
namespace Rosetta\Services\Notifications\SMS;

use Rosetta\Models\Company;

interface SMSGatewayServiceInterface
{
    public function send(Company $company,$to,$text);
}