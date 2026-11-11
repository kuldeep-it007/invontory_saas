<?php
namespace Rosetta\Services\Notifications\SMS\Drivers;

use GuzzleHttp\Client;
use Rosetta\Classes\Settings;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;


class BhashDriver implements SMSDriverInterface
{
    /**
     *
     * @var \Rosetta\Classes\Settings
     */
    protected $settings;
    public function init(Settings $settings)
    {
        $this->settings = $settings;
        return $this;
    }
    public function send($to, $text)
    {
        $client = new Client;
        /*	http://bhashsms.com/api/sendmsg.php?
        user=ROSEPRODUCTS
        pass=********
        sender=Sender ID
        phone=Mobile No
        text=Test SMS
        priority=Priority
        stype=smstype*/
    //    var_dump($this->settings->get('url'));
		try{
			$response = $client->post($this->settings->get('url'),[
				'query' => [
					"user" => $this->settings->get('user'),
                    "pass" => $this->settings->get('pass'),
                    "sender" => $this->settings->get('sender'),
                    "phone" => $to,
                    "text" => $text,
                    "priority" => $this->settings->get('priority'),
                    "stype" =>'normal'
				]
			]);
		}
		catch(ClientException $e)
		{

			$response = $e->getResponse();
        //    var_dump(1,strval($response->getBody()));
		}
		catch(ServerException $e)
		{
			$response = $e->getResponse();
    //        var_dump(2,strval($response->getBody()));
		}

    //    var_dump(1,strval($response->getBody()));
    }
}