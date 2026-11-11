<?php
namespace Rosetta\Services\Notifications\WhatsApp;

use Rosetta\Models\BlanketOrder;
use Rosetta\Models\Company;

interface WhatsAppServiceInterface
{
    public function placeOrderWhatsAppNotification($request);
    public function orderPlacedCheck($orderNumber, BlanketOrder $blanketOrder);
    public function placeOrder($requestData);
}