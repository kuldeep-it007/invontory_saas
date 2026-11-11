<?php

namespace Rosetta\Http\Controllers;

use Carbon\Carbon;
use Rosetta\Models\SalesManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Rosetta\Transformers\SalesManager\SalesManagerTransformer;

class SalesManagerController extends Controller
{

    public function getActiveSalesManager(Request $request)
    {
        $salesmanager = SalesManager::where('status', 1)->get();
        return fractal()
            ->collection($salesmanager)
            ->transformWith(new SalesManagerTransformer)
            ->toArray();
    }
}
