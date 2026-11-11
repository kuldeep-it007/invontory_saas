<?php
namespace Rosetta\Services\Notifications;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Rosetta\Channels\Messages\SMSMessage;
use Rosetta\Services\Notifications\SMSServiceInterface;
class SSDSMSService implements SMSServiceInterface
{
	public function send(SMSMessage $message)
	{
		//http://sms.ssdindia.com/api/sendhttp.php?authkey=2054AybZBdnISx591c2a6b&mobiles=9741999114&message=test&sender=ROSETA&route=4&country=91
		$client = new Client;
		try{
			$response = $client->get(config('sms.ssd.url'),[
				'query' => [
					"authkey" => config('sms.ssd.authkey'),
					"mobiles" => implode(',',$message->getTo()),
					"message" => urlencode($message->getMessage()),
					"sender" => config('sms.ssd.sender'),
					"route" => config('sms.ssd.route'),
					"country" => 91,
				]
			]);
		}
		catch(ClientException $e)
		{
			$response = $e->getResponse();
		}
		catch(ServerException $e)
		{
			$response = $e->getResponse();
		}
	}
}