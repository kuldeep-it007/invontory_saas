<?php

namespace Rosetta\Http\Controllers;

use Rosetta\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Rosetta\Services\Transaction\TransactionServiceInterface;
use Rosetta\Transformers\Transaction\IndexTransformer;

class TransactionsController extends Controller
{
    protected $transactionService;
    public function __construct(TransactionServiceInterface $transactionService)
    {
        $this->transactionService = $transactionService;
    }
    public function index(Request $request,$companyID)
    {
        if(!Gate::allows('manageTransactions'))
        {
            return response()->json("", 403);
        }
        $company = Company::find($companyID);
        $transactions = $this->transactionService->all($company,$request->all());
        $transactionArray =  fractal()
            ->collection($transactions)
            ->parseIncludes(['order','customer'])
            ->transformWith(new IndexTransformer())
            ->toArray();


        if($request->get('page'))
        {
            return [
                "total" => $transactions->total(),
                "page"  => $transactions->currentPage(),
                "transactions" => $transactionArray,
            ];
        }
        else
            return $transactionArray;
    }
}
