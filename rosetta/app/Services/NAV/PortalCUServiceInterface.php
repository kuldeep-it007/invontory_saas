<?php
namespace Rosetta\Services\NAV;

use Rosetta\Models\Company;
interface PortalCUServiceInterface
{
	public function insertBatchNo(Company $company, $no, $line_id, $quantity, $batch_no);	
}