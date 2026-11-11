<?php
namespace Rosetta\Services\NAV;

use Carbon\Carbon;
use Rosetta\Traits\Nav;
use Rosetta\Models\Company;
use Rosetta\NAV\WS\BankReceipt\bankreceipt;
use Rosetta\NAV\WS\BankReceipt\Bankreceipt_Service;
use Rosetta\NAV\WS\BankReceipt\Create;
use Rosetta\Services\NAV\BankReceiptServiceInterface;

class BankReceiptService implements BankReceiptServiceInterface
{
    use Nav;
	const LOCATION = 'Page/bankreceipt';
	const WSDL = 'BankReceipt';

	public function create(Company $company,String $transactionRef,String $soNumber, String $cusomerNumber, float $amount)
	{
		$bankReceiptService = new Bankreceipt_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$bankReceiptSettings = $company->getSettingsForType('bank_receipt');
		$bankReceipt = new bankreceipt;
		$bankReceipt->setPosting_Date(Carbon::now()->format('Y-m-d'));
		$bankReceipt->setDocument_Type('Payment');
		$bankReceipt->setPayment_to_Made(0);
		$bankReceipt->setServ_Tax_on_Advance_Payment(false);
		$bankReceipt->setAccount_Type($bankReceiptSettings->get('account_type'));
		$bankReceipt->setAccount_No($bankReceiptSettings->get('account_number'));
		$bankReceipt->setAmount($amount);
		$bankReceipt->setBal_Account_Type('Customer');
		$bankReceipt->setBal_Account_No($cusomerNumber);
		$bankReceipt->setGST_on_Advance_Payment(false);
		$bankReceipt->setService_Tax_Rounding_Precision(0);
		$bankReceipt->setService_Tax_Rounding_Type('Nearest');
		$bankReceipt->setSales_Order_No($soNumber);
		$bankReceipt->setExternal_Document_No($transactionRef);
		$bankReceipt->setApply(false);
		$createRequest = new Create($bankReceiptSettings->get('journal_batch_name'),$bankReceipt);
		$response = $bankReceiptService->Create($createRequest);
		return $response;
	}
}