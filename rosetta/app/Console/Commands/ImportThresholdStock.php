<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Rosetta\Models\Company;
use Rosetta\Models\Company\StockPoint;
use Rosetta\Models\Product\AvailableStockThreshold;
use Rosetta\Models\Product\Product;
use Rosetta\Services\NAV\ItemCardIntransitServiceInterface;
use Rosetta\Services\StockThreshold\StockThresholdServiceInterface;


class ImportThresholdStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:thresholdStock {companyId} {tenant} {stockpointId} {filename}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import threshold Stock';
    protected $stockThresholdService;
    public function __construct(StockThresholdServiceInterface $stockThresholdService)
    {
        $this->stockThresholdService = $stockThresholdService;
        parent::__construct();
    }
    /**
     * Create a new command instance.
     *
     * @return void
     */
   
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->stockThresholdService->importThreshold($this->argument('companyId'),$this->argument('tenant'),$this->argument('stockpointId'),new UploadedFile($this->argument('filename'),'file'));
    }
}
