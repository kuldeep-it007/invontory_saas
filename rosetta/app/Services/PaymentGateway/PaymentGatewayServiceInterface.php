<?php
namespace Rosetta\Services\PaymentGateway;

use Rosetta\Models\Company;

interface PaymentGatewayServiceInterface
{
    /**
     * Initiate payment gateway transaction
     *
     * @param Company $company
     * @param Distributor/Dealer $customer
     * @param float $amount
     * @param String $transaction_id
     * @return \Rosetta\Services\PaymentGateway\Models\CreateResponse
     */
    public function initiate(Company $company, $customer, float $amount,String $transaction_id);

    /**
     * Get transaction details from PG
     *
     * @param String $transaction_id
     * @return \Rosetta\Services\PaymentGateway\Models\OrderResponse;
     */
    public function getTransaction($transaction_id);
}