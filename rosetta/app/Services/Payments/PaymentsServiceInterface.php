<?php
namespace Rosetta\Services\Payments;

use Rosetta\Models\BlanketOrder;
use Rosetta\Models\Transaction;

interface PaymentsServiceInterface 
{
    public function recordPayment(BlanketOrder $blanketOrder,Transaction $transaction);
    public function getPaymentStatus($company,$blanketOrder);
}