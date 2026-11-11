<?php
namespace Rosetta\Services\PaymentGateway\Drivers;

use GuzzleHttp\Client;
use Rosetta\Classes\Settings;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Rosetta\Services\PaymentGateway\Models\Customer;
use Rosetta\Services\PaymentGateway\Models\OrderResponse;
use Rosetta\Services\PaymentGateway\Models\CreateResponse;
use Rosetta\Services\PaymentGateway\Drivers\DriverInterface;


class Telr implements DriverInterface
{
    /**
     * @var \Rosetta\Classes\Settings
     */
    protected $config;
    /**
     * @var \GuzzleHttp\Client
     */
    protected $httpClient;
    public function init(Settings $config = null)
    {
        $this->config = $config;
        $this->httpClient = new Client([
			'base_uri' => $this->config->get('base_uri'),
		]);
        return $this;
    }
    public function create(Customer $customer,float $amount,String $transaction_id, Array $urls)
    {
        try{
            $response = $this->httpClient->post('gateway/order.json',[
                'form_params' => [
                    'ivp_method'   => 'create',
                    'ivp_store'    => $this->config->get('store_id'),
                    'ivp_authkey'  => $this->config->get('auth_key'),
                    'ivp_amount'   => $amount,
                    'ivp_currency' => $this->config->get('currency'),
                    'ivp_test'     => $this->config->get('live',false)  ? 0 : 1,
                    'ivp_cart'     => $transaction_id,
                    'ivp_desc'     => $this->config->get('description'),
                    'return_auth'  => $urls['success'],
                    'return_decl'  => $urls['decline'],
                    'return_can'   => $urls['cancel'],

                ]
            ]);
        }
        catch(ClientException $e)
        {

        }
        catch(ServerException $e)
        {

        }
        $json = json_decode(strval($response->getBody()));
        return new CreateResponse(
            [
                'gateway' => 'telr',
                'url' => $json->order->url,
                'ref' => $json->order->ref,
                'response' => $json,
            ]
        );
    }
    public function getTransaction($transaction_id)
    {
        try{
            $response = $this->httpClient->post('gateway/order.json',[
                'form_params' => [
                    'ivp_method'   => 'check',
                    'ivp_store'    => $this->config->get('store_id'),
                    'ivp_authkey'  => $this->config->get('auth_key'),
                    'order_ref' => $transaction_id,
                ]
            ]);
        }
        catch(ClientException $e)
        {

        }
        catch(ServerException $e)
        {

        }
        $json = json_decode(strval($response->getBody()),true);
        $status = "pending";
        switch(array_get($json,'order.status.code'))
        {
            case 1:
                $status = 'pending';
            break;
            case 2:
                $status = 'on_hold';
            break;
            case 3:
                $status = 'paid';
            break;
            case  -1:
                $status = 'expired';
            break;
            case -2:
                $status = 'cancelled';
            break;
            case -3:
                $status = 'declined';
            break;
        }
        $data = [
            'status'   => $status,
            'amount'   => (float) array_get($json,'order.amount'),
            'currency' => array_get($json,'order.currency'),
            'payment'  => [
                'method' => 'card',
                'ref'    => array_get($json,'order.transaction.ref'),
                'card'   => [
                    'type'        => array_get($json,'order.card.type'),
                    'number' => "XXXX-XXXX-XXXX-" . array_get($json,'order.card.last4')
                ],
                'message' => array_get($json,'order.transaction.message'),
            ],
            'original' => $json
        ];
        return new OrderResponse($data);

    }
}