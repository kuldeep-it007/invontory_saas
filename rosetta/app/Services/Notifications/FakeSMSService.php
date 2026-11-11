<?php
namespace Rosetta\Services\Notifications;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Rosetta\Channels\Messages\SMSMessage;
use Rosetta\Services\Notifications\SMSServiceInterface;
class FakeSMSService implements SMSServiceInterface
{
	public function send(SMSMessage $message)
	{
		$client = new Client;
		try{
			$client->post(config('sms.fake.url'),[
				'json' => [
					"to" => $message->getTo(),
					"text" => $message->getMessage(),
				]
			]);
		}
		catch(ClientException $e)
		{


		}
		catch(ServerException $e)
		{

		}
	}
}