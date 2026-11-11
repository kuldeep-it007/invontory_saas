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


class Fatoorah implements DriverInterface
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
        $token = $this->config->get('api_key');
        $base_uri = $this->config->get('base_uri');
        $this->httpClient = new Client([
                			'base_uri' => $base_uri,
                            'headers' => array('Authorization' => 'bearer ' . $token,        
                                                'Accept'        => 'application/json',
                                                'Content-Type'  => 'application/json'),
		                  ]);
        return $this;
    }
    public function create(Customer $customer,float $amount,String $transaction_id, Array $urls)
    {
        try{
            $options = [
                'json' => [
                    "PaymentMethodId" => $this->config->get('payment_method_id'),
                    "CustomerName" => $customer->name,
                    "InvoiceValue" => $amount,
                    "CallBackUrl" => $urls['success'],
                    "ErrorUrl" => $urls['decline'],
                   ]
               ]; 
            $response = $this->httpClient->post("/v2/ExecutePayment", $options);
            $body = $response->getBody();
            $json = json_decode($body);
            return new CreateResponse(
                [
                    'gateway' => 'fatoorah',
                    'url' => $json->Data->PaymentURL,
                    'ref' => $json->Data->InvoiceId,
                    'response' => $json,
                ]
            );           
        }
        catch(ClientException $e)
        {
        }
        catch(ServerException $e)
        {
        }
    }
    public function getTransaction($transaction_id)
    {
        try{
            $options = [
                'json' => [
                    "Key" => $transaction_id,
                    "KeyType" => 'InvoiceId',
                   ]
               ]; 
            $response = $this->httpClient->post("/v2/GetPaymentStatus", $options);
        }
        catch(ClientException $e)
        {

        }
        catch(ServerException $e)
        {

        }
        $json = $response->getBody();
        $body = json_decode($json);
        $InvoiceTransactions = $body->Data->InvoiceTransactions[0];
        $status = "pending";
        switch($InvoiceTransactions->TransactionStatus)
        {
            case 'Failed':
                $status = 'declined';
            break;
            case 'Succss':
                $status = 'paid';
            break;
        }
        $data = [
            'status' => $status,
            'transactionStatus'   => $body->Data->InvoiceStatus,
            'amount'   => $InvoiceTransactions->PaidCurrencyValue,
            'currency' => $InvoiceTransactions->Currency,
            'payment'  => [
                'method' => 'card',
                'ref'    => $InvoiceTransactions->TransactionId,
                'card'   => [
                    'type'   => 'VISA/MASTER',
                    'number' => $InvoiceTransactions->CardNumber,
                ],
                'message' => $InvoiceTransactions->Error,
            ],
            'original' => $body
        ];
        return new OrderResponse($data);
    }
}