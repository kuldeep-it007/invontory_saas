<?php

namespace Rosetta\Http\Controllers;

use Rosetta\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Rosetta\Services\Invoice\InvoiceServiceInterface;
use Rosetta\Transformers\Transaction\IndexTransformer;
use Rosetta\Transformers\Invoice\InvoiceListTransformer;
use Rosetta\Transformers\Invoice\InvoiceList1Transformer;
use Rosetta\Transformers\PurchaseOrder\BlanketOrderTransformer;

class InvoiceController extends Controller
{
    protected $invoiceService;
    public function __construct(InvoiceServiceInterface $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }
    public function index(Request $request, $companyId)
    {
        if(!Gate::allows('viewInvoices'))
        {
            return response()->json("", 403);
        }
        $company = Company::find($companyId);   
        $invoices = $this->invoiceService->dealerBlanketOrders($request->all());

        $invoicesArray = fractal()
            ->collection($invoices)
          //  ->transformWith(new InvoiceList1Transformer()) //Dealer BOs
            ->transformWith(new InvoiceListTransformer()) //Distributor BOs
            ->toArray();

        if($request->get('page'))
        {
            return [
                "total" => $invoices->total(),
                "page"  => $invoices->currentPage(),
                "invoices" => $invoicesArray,
            ];
        }
        else
            return $invoicesArray;
    }
    public function getInvoice(Request $request, $companyId,$dealerBOId)
    {
        if(!Gate::allows('viewInvoices'))
        {
            return response()->json("", 403);
        }
        $invoice = $this->invoiceService->dealerBlanketOrder($dealerBOId);
        $invoiceData = null;
        $loggedInUser = Auth::user();
        if($invoice)
        {
            $invoiceData = fractal()
            ->item($invoice)
            ->parseIncludes(['customer','purchase_lines','purchase_lines.product','purchase_lines.product.category','purchase_lines.product.collection','purchase_lines.product.colour', 'purchase_lines.stock_point'])
            ->transformWith(new BlanketOrderTransformer($loggedInUser))
            ->toArray();
        }
        return ['po' => $invoiceData];
    }
    public function mark_printed(Request $request, $companyId, $orderId)
    {
        $blanketOrder = $this->invoiceService->mark_printed($companyId, $orderId, $request->all(), Auth::user());
        $blanketOrderData = null;
        if($blanketOrder)
        {
            $blanketOrderData = fractal()
            ->item($blanketOrder)
            ->parseIncludes(['customer','purchase_lines','purchase_lines.product','purchase_lines.product.category','purchase_lines.product.collection','purchase_lines.product.colour', 'purchase_lines.stock_point'])
            ->transformWith(new BlanketOrderTransformer())
            ->toArray();
        }
        return ['po' => $blanketOrderData];
    }
}
