<?php

namespace Rosetta\Console\Commands;

use Auth;
use Carbon\Carbon;
use Rosetta\Models\SuperStockist\SuperStockist;
use Rosetta\Services\BlanketOrders\BlanketOrdersServiceInterface;
use Rosetta\Transformers\PurchaseOrder\BlanketOrderListSsReportTransformer;
use Illuminate\Console\Command;

class SuperStokistSalesReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'superstokist:sales-report {email} {start_date?} {end_date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Super Stokist Sales Report. {month} can be Jan and {year} can be 2023. month and year should be last month and corresponding year. If you are executing the report on 1st April 2023 then month will be Mar and year will be 2023. If run on 01-01-2023 then month will be Dec and year will be 2022.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $blanketOrdersService;

    public function __construct(BlanketOrdersServiceInterface $blanketOrdersService)
    {
        parent::__construct();
        $this->blanketOrdersService = $blanketOrdersService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */

    public function handle()
    {
        $email = $this->argument('email');
        ini_set('display_errors', 1);
        ini_set('memory_limit', '-1');
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);

        // Resolve the reporting date range. When start_date / end_date are not
        // provided, fall back to the same default that has always been used:
        // the previous month (start-of-month to end-of-month).
        $previousMonth = Carbon::now()->subMonthNoOverflow();
        $startDateArg = $this->argument('start_date');
        $endDateArg = $this->argument('end_date');

        $startDate = $startDateArg
            ? Carbon::parse($startDateArg)->startOfDay()->timestamp
            : $previousMonth->copy()->startOfMonth()->timestamp;
        $endDate = $endDateArg
            ? Carbon::parse($endDateArg)->endOfDay()->timestamp
            : $previousMonth->copy()->endOfMonth()->timestamp;

        $request = [
            'page' => 1,
            'per_page' => 100000,
            'show_all' => true,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        $superStokists = SuperStockist::all();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheetIndex = 0;

        foreach ($superStokists as $superStokist) {
            $superstockist_id = $superStokist->id;
            $blanketOrders = $this->blanketOrdersService
                ->superStockistBlanketOrders($superstockist_id, $request);

            $blanketOrdersArray = fractal()
                ->collection($blanketOrders)
                ->transformWith(new BlanketOrderListSsReportTransformer())
                ->toArray();

            if (empty($blanketOrdersArray)) {
                continue;
            }

            if ($sheetIndex === 0) {
                $activeSheet = $spreadsheet->getActiveSheet();
            } else {
                $activeSheet = $spreadsheet->createSheet();
            }

            $sheetName = 'SS_' . str_slug($superStokist->name) . '_' . $superstockist_id;
            $activeSheet->setTitle(substr($sheetName, 0, 31));

            $activeSheet->setCellValue('A1', 'Month');
            $activeSheet->setCellValue('B1', 'Date');
            $activeSheet->setCellValue('C1', 'StockPoint');
            $activeSheet->setCellValue('D1', 'BilledBy');
            $activeSheet->setCellValue('E1', 'BilledTo');
            $activeSheet->setCellValue('F1', 'Category');
            $activeSheet->setCellValue('G1', 'Collection');
            $activeSheet->setCellValue('H1', 'Colour');
            $activeSheet->setCellValue('I1', 'Qty');
            $activeSheet->setCellValue('J1', 'Sale-Sqft');
            $activeSheet->setCellValue('K1', 'NetAmount');
            $activeSheet->setCellValue('L1', 'OrderID');
            $activeSheet->setCellValue('M1', 'OrderStatus');

            $activeSheet->getStyle('A1:M1')->getFont()->setBold(true);

            $row = 2;

            foreach ($blanketOrdersArray as $orders) {
                if ($orders['status'] == "Cancelled") {
                    continue;
                }

                $stockPoint = trim(implode(',', $orders['stock_points']), ',');
                $netAmount = 0;
                $category = [];
                $collection = [];
                $colour = [];
                $quantity = 0;
                $finalPrice = 0;

                foreach ($orders['purchase_lines'] as $purchase_line) {
                    $netAmount += $purchase_line['amount'] ?? 0;
                    $quantity += $purchase_line['quantity'] ?? 0;

                    $unitPrice = $purchase_line['unit_price'] ?? 0;
                    $discountPercentage = $purchase_line['discount'] ?? 0;
                    $discountAmount = ($unitPrice * $discountPercentage) / 100;
                    $finalPrice = round(($unitPrice - $discountAmount), 2);

                    if (!empty($purchase_line['product']['category']['code'])) {
                        $category[] = $purchase_line['product']['category']['code'];
                    }

                    if (!empty($purchase_line['product']['collection']['code'])) {
                        $collection[] = $purchase_line['product']['collection']['code'];
                    }

                    if (!empty($purchase_line['product']['colour']['code'])) {
                        $colour[] = $purchase_line['product']['colour']['code'];
                    }
                }

                $activeSheet->setCellValue('A' . $row, date('M', $orders['created_at']));
                $activeSheet->setCellValue('B' . $row, date('d, M Y', $orders['created_at']));
                $activeSheet->setCellValue('C' . $row, $stockPoint);
                $activeSheet->setCellValue('D' . $row, $stockPoint);
                $activeSheet->setCellValue('E' . $row, $orders['customer']['name']);
                $activeSheet->setCellValue('F' . $row, trim(implode(',', array_unique($category)), ','));
                $activeSheet->setCellValue('G' . $row, trim(implode(',', array_unique($collection)), ','));
                $activeSheet->setCellValue('H' . $row, trim(implode(',', array_unique($colour)), ','));
                $activeSheet->setCellValue('I' . $row, $quantity);
                $activeSheet->setCellValue('J' . $row, $finalPrice);
                $activeSheet->setCellValue('K' . $row, $netAmount);
                $activeSheet->setCellValue('L' . $row, $orders['id']);
                $activeSheet->setCellValue('M' . $row, $orders['status']);

                $row++;
            }

            for ($col = 'A'; $col !== 'N'; $col++) {
                $activeSheet->getColumnDimension($col)->setAutoSize(true);
            }

            $sheetIndex++;
        }

        if ($sheetIndex > 0) {
            $spreadsheet->setActiveSheetIndex(0);
            // Label the file with the actual reporting range. For the default
            // (previous month) range this stays as the month name; for a custom
            // range it reflects the chosen start and end dates.
            if ($startDateArg || $endDateArg) {
                $rangeLabel = date('Y-m-d', $startDate) . '_to_' . date('Y-m-d', $endDate);
            } else {
                $rangeLabel = $previousMonth->format('M');
            }
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $file = storage_path() . '/app/SuperStockistStock_All_' . $rangeLabel . '.xlsx';

            $writer->save($file);
            //$this->sendReportEmail($email, $file);
            $this->downloadReport($file);
        }
    }

 private function sendReportEmail($email, $file)
{
    \Mail::raw('Please find attached the Super Stockist Sales Report.', function ($message) use ($email, $file) {
        $message->to($email)
            ->subject('Super Stockist Sales Report')
            ->attach($file, [
                'as' => basename($file),
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
    });
} 

private function downloadReport($file)
{
    if (empty($file) || !file_exists($file)) {
        $this->error('Report file not found: ' . $file);
        return false;
    }

    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    if (!in_array($extension, ['xlsx', 'xls', 'csv'])) {
        $this->error('Invalid report file type: ' . $extension);
        return false;
    }

    // A console command runs in the CLI and has no HTTP response object,
    // so the file itself cannot be streamed to the browser from here.
    // Instead we output the generated file path so the caller (the
    // /get-ss-stock-all web route) can return a browser download response.
    $this->info('Report generated successfully.');
    $this->line($file);

    return $file;
}

    public function handle_bck_org()
    {
        ini_set('display_errors', 1);
        ini_set('memory_limit', '-1');
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);

        $request = [
            'page' => 1,
            'per_page' => 100000,
            'show_all' => true,
            'start_date' => Carbon::now()->subMonthNoOverflow()->startOfMonth()->timestamp,
            'end_date' => Carbon::now()->subMonthNoOverflow()->endOfMonth()->timestamp,
        ];

        $superStokists = SuperStockist::all();

        foreach ($superStokists as $superStokist) {
            $superstockist_id = $superStokist->id;

            $blanketOrders = $this->blanketOrdersService->superStockistBlanketOrders($superstockist_id, $request);

            $blanketOrdersArray = fractal()
                ->collection($blanketOrders)
                ->transformWith(new BlanketOrderListSsReportTransformer())
                ->toArray();
            // dd($blanketOrdersArray);

            if ($blanketOrdersArray) {
                $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $activeSheet = $spreadsheet->getActiveSheet()->setTitle("My Stock");;
                $activeSheet->setCellValue('A1', 'Month')->getStyle('B1')->getFont()->setBold(true);
                $activeSheet->setCellValue('B1', 'Date')->getStyle('C1')->getFont()->setBold(true);
                $activeSheet->setCellValue('C1', 'StockPoint')->getStyle('D1')->getFont()->setBold(true);
                $activeSheet->setCellValue('D1', 'BilledBy')->getStyle('E1')->getFont()->setBold(true);
                $activeSheet->setCellValue('E1', 'BilledTo')->getStyle('F1')->getFont()->setBold(true);
                $activeSheet->setCellValue('F1', 'Category')->getStyle('G1')->getFont()->setBold(true);
                $activeSheet->setCellValue('G1', 'Collection')->getStyle('H1')->getFont()->setBold(true);
                $activeSheet->setCellValue('H1', 'Colour')->getStyle('I1')->getFont()->setBold(true);
                $activeSheet->setCellValue('I1', 'Qty')->getStyle('J1')->getFont()->setBold(true);

                $activeSheet->setCellValue('J1', 'Sale-Sqft')->getStyle('K1')->getFont()->setBold(true);
                // $activeSheet->setCellValue('K1', 'TaxPercent')->getStyle('L1')->getFont()->setBold(true);
                // $activeSheet->setCellValue('L1', 'TaxAmount')->getStyle('M1')->getFont()->setBold(true);
                $activeSheet->setCellValue('K1', 'NetAmount')->getStyle('L1')->getFont()->setBold(true);
                $activeSheet->setCellValue('L1', 'OrderID')->getStyle('M1')->getFont()->setBold(true);
                $row = 2;
                $activeSheet->setCellValue('M1', 'OrderStatus')->getStyle('N1')->getFont()->setBold(true);
                $row = 2;

                foreach ($blanketOrdersArray as $orders) {
                    if ($orders['status'] == "Cancelled") {
                        continue;
                    }
                    $stockPoint = trim(implode(',', $orders['stock_points']), ',');
                    $netAmount = 0;
                    $category = [];
                    $collection = [];
                    $colour = [];
                    $quantity = 0;
                    $unitPrice = 0;
                    // $TaxPercent = 0;
                    // $TaxAmount = 0;

                    foreach ($orders['purchase_lines'] as $purchase_line) {
                        $netAmount += $purchase_line['amount'] ?? 0; //$purchase_line['net_amount']??0;

                        $quantity += $purchase_line['quantity'] ?? 0;
                        // $unitPrice = $purchase_line['unit_price']??0;
                        $unitPrice = $purchase_line['unit_price'] ?? 0;
                        $discountPercentage = $purchase_line['discount'] ?? 0;
                        $discountAmount = ($unitPrice * $discountPercentage) / 100;
                        $finalPrice = round(($unitPrice - $discountAmount), 2);
                        // $TaxPercent+= $purchase_line['tax_percent']??0;
                        //  $TaxAmount+= $purchase_line['tax_amount']??0;
                        if (!empty($purchase_line['product']['category']['code'])) {
                            $category[] = $purchase_line['product']['category']['code'];
                        }
                        if (!empty($purchase_line['product']['collection']['code'])) {
                            $collection[] = $purchase_line['product']['collection']['code'];
                        }
                        if (!empty($purchase_line['product']['colour']['code'])) {
                            $colour[] = $purchase_line['product']['colour']['code'];
                        }
                    }
                    $activeSheet->setCellValue('A' . $row, date('M', ($orders['created_at'])));
                    $activeSheet->setCellValue('B' . $row, date('d, M Y', ($orders['created_at'])));
                    $activeSheet->setCellValue('C' . $row, $stockPoint);
                    $activeSheet->setCellValue('D' . $row, $stockPoint);
                    $activeSheet->setCellValue('E' . $row, $orders['customer']['name']);
                    $activeSheet->setCellValue('F' . $row, trim(implode(',', array_unique($category)), ','));
                    $activeSheet->setCellValue('G' . $row, trim(implode(',', array_unique($collection)), ','));
                    $activeSheet->setCellValue('H' . $row, trim(implode(',', array_unique($colour)), ','));
                    $activeSheet->setCellValue('I' . $row, $quantity);
                    $activeSheet->setCellValue('J' . $row, $finalPrice);
                    // $activeSheet->setCellValue('K' . $row, implode(',', $TaxPercent)); 
                    // $activeSheet->setCellValue('L' . $row, implode(',', $TaxAmount));  
                    $activeSheet->setCellValue('K' . $row, $netAmount);
                    $activeSheet->setCellValue('L' . $row, $orders['id']);
                    $activeSheet->setCellValue('M' . $row, $orders['status']);
                    $row++;
                }

                for ($col = 'A'; $col !== 'N'; $col++) {
                    $activeSheet->getColumnDimension($col)->setAutoSize(true);
                }

                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                $file = storage_path() . '/app/SuperStockistStock_' . $superstockist_id . '.xlsx';

                $writer->save($file);
            }
        }

        //return $file;
    }
}
