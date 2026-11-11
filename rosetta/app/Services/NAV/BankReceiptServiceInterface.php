<?php
namespace Rosetta\Services\NAV;

use Rosetta\Models\Company;

interface BankReceiptServiceInterface
{
    /**
	 * Undocumented function
	 *
	 * @param Company $company
	 * @param string $transactionRef
	 * @param string $soNumber
	 * @param string $cusomerNumber
	 * @param float $amount
	 * @return \Rosetta\NAV\WS\BankReceipt\Create_Result;
	 */
    public function create(Company $company,String $transactionRef,String $soNumber, String $cusomerNumber, float $amount);
}