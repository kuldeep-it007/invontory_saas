<?php
namespace Rosetta\Services\Notifications\SMS;

use Rosetta\Models\Company;

class SMSGatewayService implements SMSGatewayServiceInterface
{
    public function send(Company $company,$to,$text)
    {
        $settings = $company->getSettingsForType('sms_gateway');
        if(empty($settings->all()))
        {
            return;
        }
      //  var_dump($settings);
        $driver = (new SMSGatewayManager(app()))
        ->gateway($settings->get('gateway'));//driver name
        
        $driver->init($settings)
            ->send($to,$text);
    }
}