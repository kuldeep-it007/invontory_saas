<?php
namespace Rosetta\Traits;

use Rosetta\Models\Transaction;

Trait TransactionTrait
{
    public function transactions()
    {
        return $this->morphMany(Transaction::class,'transactionable');
    }
}