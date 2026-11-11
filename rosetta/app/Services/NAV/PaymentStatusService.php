<?php
namespace Rosetta\Services\NAV;
use Rosetta\Models\Company;
use Rosetta\NAV\WS\PaymentStatus\Payment_Status_Fields;
use Rosetta\NAV\WS\PaymentStatus\Payment_Status_Filter;
use Rosetta\NAV\WS\PaymentStatus\Payment_Status_Service;
use Rosetta\NAV\WS\PaymentStatus\ReadMultiple;
use Rosetta\NAV\WS\PaymentStatus\getPayment_Status;
use Rosetta\NAV\WS\PaymentStatus\getReadMultiple_Result;
use Rosetta\Services\NAV\PaymentStatusServiceInterface;
use Rosetta\Traits\Nav;
class PaymentStatusService implements PaymentStatusServiceInterface
{
	use Nav;
	const LOCATION = 'Page/Payment_Status';
	const WSDL = 'Payment_Status';
	public function getPaymentStatus(Company $company, $si_no)
	{
		$paymentStatusService = new Payment_Status_Service(["location" => $this->getWSLocation($company). SELF::LOCATION],SELF::WSDL);
		$readMultiple = new ReadMultiple(
			[
				new Payment_Status_Filter(Payment_Status_Fields::Document_No,$si_no)
			],
			null,
			null
		);
		$payment_status = $paymentStatusService->ReadMultiple($readMultiple)->getReadMultiple_Result()->getPayment_Status();
		if(count($payment_status))
		{
			return $payment_status[0];
		}
	}
}