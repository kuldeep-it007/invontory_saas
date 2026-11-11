<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use Rosetta\Models\SalesManager;
use Rosetta\Models\Dealer;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;

class UpdateSalesManagerWithDealers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    // protected $signature = 'update:sales-manager-with-dealers';
    protected $signature = 'dealers:update-sales-manager {file : Path to the Excel file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import dealers from Excel and update sales manager assignments';

    /**
     * Create a new command instance.
     *
     * @return void
     */

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
public function handle()
    {
        //excel/dealers-with-sales-manager.xlsx
        $filePath = storage_path($this->argument('file'));

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return Command::FAILURE;
        }

        $this->info('Starting dealer import process...');

        try {
            $data = Excel::toArray([], $filePath);
            
            if (empty($data) || empty($data[0])) {
                $this->error('Excel file is empty or invalid');
                return Command::FAILURE;
            }

            $rows = $data[0];
            $headers = array_shift($rows); // First row as headers
            $headers = array_map('trim', $headers);

            $this->info('Found ' . count($rows) . ' rows to process');
            
            $successCount = 0;
            $errorCount = 0;
            $createdManagers = 0;

            DB::beginTransaction();

            foreach ($rows as $index => $row) {
                try {
                    $rowData = array_combine($headers, $row);
                    
                    // Process each row
                    $result = $this->processRow($rowData);
                    
                    if ($result['success']) {
                        $successCount++;
                        if ($result['manager_created']) {
                            $createdManagers++;
                        }
                    } else {
                        $errorCount++;
                        $this->warn("Row " . ($index + 2) . ": " . $result['message']);
                    }
                    
                } catch (\Exception $e) {
                    $errorCount++;
                    $this->error("Row " . ($index + 2) . " error: " . $e->getMessage());
                }
            }

            DB::commit();

            $this->newLine();
            $this->info('Import completed successfully!');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Dealers Updated', $successCount],
                    ['New Sales Managers Created', $createdManagers],
                    ['Errors', $errorCount],
                    ['Total Rows', count($rows)]
                ]
            );

            return Command::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Import failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Process a single row from the Excel file
     */
    private function processRow(array $rowData): array
    {
        // Extract dealer identifier (adjust column names as needed)
        $dealerCode = $rowData['nav_id'] ?? $rowData['Nav Id'] ?? null;
        
        // Extract sales manager information
        $managerName = $rowData['Sales Manager'] ?? $rowData['Sales Manager'] ?? null;
        $Region = $rowData['Region'] ?? $rowData['Region'] ?? null;
        $Priority = $rowData['Priority'] ?? $rowData['Priority'] ?? null;
        
        if (empty($dealerCode)) {
            return ['success' => false, 'message' => 'Missing dealer identifier'];
        }

        if (empty($managerName)) {
            return ['success' => false, 'message' => 'Missing sales manager name'];
        }

        // Find the dealer
        $dealer = Dealer::where('nav_id', $dealerCode)->first();

        if (!$dealer) {
            return ['success' => false, 'message' => "Dealer not found: {$dealerCode}"];
        }

        // Find or create sales manager
        $managerCreated = false;
        $salesManager = SalesManager::where('name', $managerName)->first();

        if (!$salesManager) {
            $salesManager = SalesManager::create([
                'name' => $managerName
            ]);
            $managerCreated = true;
        } else {
            // Update existing manager if new data provided
            $salesManager->status = 1;
            $salesManager->save();
        }

        // Update dealer with sales manager id
        $dealer->sales_manager_id = $salesManager->id;
        $dealer->region = $Region;
        $dealer->priority = $Priority;
        $dealer->save();

        return [
            'success' => true,
            'manager_created' => $managerCreated,
            'message' => "Updated dealer {$dealer->name}"
        ];
    }
}
