<?php
namespace Rosetta\Services\PaymentGateway\Drivers;

use Rosetta\Classes\Settings;
use Rosetta\Services\PaymentGateway\Models\Customer;

interface DriverInterface
{
    /**
     * Initialize Payment gateway with config values
     *
     * @param Settings $config
     * @return \Rosetta\Services\PaymentGateway\Drivers\DriverInterface
     */
    public function init(Settings $config = null);
    /**
     * Initiate online transaction with payment gateway
     *
     * @param Customer $customer
     * @param float $amount
     * @param String $currency
     * @param String $transaction_id
     * @param Array $urls
     * @return \Rosetta\Services\PaymentGateway\Models\CreateResponse
     */
    public function create(Customer $customer,float $amount,String $transaction_id,Array $urls);

    /**
     * Get transaction details from payment gateway
     *
     * @param String $transaction_id
     * @return \Rosetta\Services\PaymentGateway\Models\OrderResponse;
     */
    public function getTransaction($transaction_id);
}