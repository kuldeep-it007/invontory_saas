<?php
namespace Rosetta\Services\Transaction;

use Rosetta\Models\Company;
use Rosetta\Services\Transaction\TransactionServiceInterface;

class TransactionService implements TransactionServiceInterface
{
    public function all(Company $company,Array $params = [])
    {
        $transactions = $company->transactions();
        if(array_has($params,'page'))
		{
            return $transactions->orderBy('created_at','desc')->paginate($params['per_page'],['*'],'',$params['page']);	
        }
        else
        {
            return $transactions->orderBy('created_at','desc')->get();
        }
        return [];
    }
}