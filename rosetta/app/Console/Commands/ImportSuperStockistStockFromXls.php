<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Rosetta\Models\Product\Product;
use Rosetta\Models\Product\Stock;
use Rosetta\Models\SuperStockist\SuperStockist;

class ImportSuperStockistStockFromXls extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:super_stockist_stock {file} {superStockistId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Super Stockist Stock for products from xls';

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
        $file = $this->argument('file');
        $superStockistId = $this->argument('superStockistId');   
        $superStockist = SuperStockist::find($superStockistId);
        $spreadsheet = IOFactory::load($file);
        if(!file_exists($file))
        {
            echo "file $file does not exist\n";
            exit(0);
        }
        $worksheet = $spreadsheet->getActiveSheet();
        $maxRow = $worksheet->getHighestRow();
        for($i=2;$i<=$maxRow;$i++)
        {
            $nav_id = $worksheet->getCell('A'.$i)->getValue();
            $quantity = $worksheet->getCell('C'.$i)->getCalculatedValue();
            $batch_no = $worksheet->getCell('B'.$i)->getValue();
            $product = Product::where('nav_id','=',$nav_id)->first();
            if(!$product)
                continue;
            /*find existing stock if any */
            echo "$nav_id, $quantity\n";
            $stock = $superStockist->stocks()->where('product_id', '=', $product->id)
                        ->where('batch_no', '=', $batch_no)->first();

            if(!$stock)
            {
                if($quantity < 0)
                {
                    echo "$nav_id, quantity can not be less than 0\n";
                }
                else
                {
                    $stock = new Stock;
                    $stock->product_id = $product->id;
                    $stock->batch_no = $batch_no;
                    if($quantity == null)
                        $stock->quantity = 0;
                    else
                        $stock->quantity = $quantity;
                    $superStockist->stocks()->save($stock);
                }
            }
            else
            {
                $stock->quantity += $quantity;
                $stock->save();
            }
        }
    }
}
