<?php
namespace Rosetta\Services\Transaction;

use Rosetta\Models\Company;

interface TransactionServiceInterface
{
    public function all(Company $company,Array $params = []);
}