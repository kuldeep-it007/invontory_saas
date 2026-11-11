<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Rosetta\Models\Company;
use Rosetta\Models\Product\Colour;
use Rosetta\Models\User;
use Rosetta\Services\Colours\RosColourServiceInterface;
use Rosetta\Services\NAV\ColourServiceInterface;

class ImportColours extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:colours {filename}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Colours for specific Collection from XLS into ROS and create in NAV as well';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $colourService;
    public function __construct(ColourServiceInterface $colourService, RosColourServiceInterface $RosColourService)
    {
        $this->colourService = $colourService;
        $this->RosColourService = $RosColourService;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $user = User::find(1);
        $file = $this->argument('filename');
      //  PHPExcel_Settings::setZipClass(PHPExcel_Settings::PCLZIP);
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
            echo "----- Processing row $i ----- \n";
            try
            {
                $collection_code = $worksheet->getCell('A'.$i)->getValue();
                $colour_code = $worksheet->getCell('B'.$i)->getValue();
                if($collection_code != null && $colour_code != null)
                {
                    $colour = Colour::where('code','=',$colour_code)->where('collection_code', '=',$collection_code)->first();
                    if(!$colour) 
                    {
                        $colour = $this->RosColourService->create($user,['code' => $colour_code],$collection_code);
                    }
                }
                else
                {
                   echo "Collection or Colour is blank on row no. ". $i ."\n";
                }
            }
            catch(\Exception $e)
            {
                echo "Error ". $e ." processing record on row no. " .$i. "\n";
            } 
        }
    }
}
