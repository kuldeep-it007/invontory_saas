<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Rosetta\Models\Company;
use Rosetta\Models\Product\Price;
use Rosetta\Models\Product\Product;
use Rosetta\Models\User;
use Rosetta\Services\CMS\CMSServiceInterface;
use Rosetta\Services\MasterData\MasterDataServiceInterface;
use Rosetta\Services\Product\ProductsServiceInterface;

class ImportProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:products {filename}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import products from XLS into ROS and create them in NAV as well. Category, Collection, Colour should exist in ROS & NAV before import.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $itemCardService, $masterDataService, $cmsService;
    public function __construct(ProductsServiceInterface $productsService, MasterDataServiceInterface $masterDataService, CMSServiceInterface $cmsService)
    {
        $this->productsService = $productsService;
        $this->masterDataService = $masterDataService;
        $this->cmsService = $cmsService;
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
             //   $str ='';
                $name = $worksheet->getCell('A'.$i)->getValue();
                $description = $worksheet->getCell('B'.$i)->getValue();
                $search_description = $worksheet->getCell('C'.$i)->getValue();
                $category = $worksheet->getCell('D'.$i)->getValue();
                $collection = $worksheet->getCell('E'.$i)->getValue();
                $colour = $worksheet->getCell('F'.$i)->getValue();
                $base_unit_of_measure = $worksheet->getCell('G'.$i)->getValue();
                $purchase_unit_of_measure = $worksheet->getCell('H'.$i)->getValue();
                $sales_unit_of_measure = $worksheet->getCell('I'.$i)->getValue();
                $gen_prod_posting_group = $worksheet->getCell('AK'.$i)->getValue();//#736
                $vat_prod_posting_group = $worksheet->getCell('AL'.$i)->getValue();//#736
                $inv_posting_group = $worksheet->getCell('AM'.$i)->getValue();//#736
                
                $techspecs = [];
                $path = '';
                $image = '';
                $image_class = '';
                $masterTechSpecs = $this->masterDataService->getTechSpecs();//Master techspecs irrespective of collection in CMS mst_attributes tabble
                $productTechSpecs = $this->cmsService->getTechSpecs($category,$collection);//Techspecs in CMS specific to a Collection product_attributes table

                foreach(['K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF'] as $techSpecCell)
                {
                    if($worksheet->getCell($techSpecCell.$i)->getValue() != null)
                    {
                        $title = $worksheet->getCell("{$techSpecCell}1")->getValue();
                        $t = $productTechSpecs->where('title',$title)->first();
                        if($t)
                        {
                            $techspecs[] = [
                                'id' => $t->id,
                                'title' => $title,
                                'value' => $worksheet->getCell($techSpecCell.$i)->getValue()
                            ];
                        }
                        else
                        {
                            if($techspecId = $masterTechSpecs->where('title',$title)->first())
                            {
                                $techspecs[] = [
                                    'attribute_id' => $techspecId->id,
                                    'title' => $title,
                                    'value' => $worksheet->getCell($techSpecCell.$i)->getValue()
                                ];
                            }
                        }
                    }
                }
            //    var_dump($techspecs);
              //  continue;
                if($worksheet->getCell('AH'.$i)->getValue() != null && $worksheet->getCell('AI'.$i)->getValue() != null)
                {
                    $image_title = $worksheet->getCell('AH'.$i)->getValue();
                    $image_class = $this->masterDataService->getImageClassId($image_title);
                    $path = $worksheet->getCell('AI'.$i)->getValue();
                    $image = base64_encode(file_get_contents($path));
                }
                
               
             //   var_dump($techspecs);

           
                if($collection != null && $colour != null &&  $colour != null && $name != null && $description != null && $search_description != null)
                {
                    $product = Product::where('category_code','=',$category)->where('collection_code', '=',$collection)->where('colour_code','=',$colour)->first();
                    if(!$product) 
                    {
                        $params = [
                            'category' => $category,
                            'collection' => $collection,
                            'colour' => $colour,
                            'sales_unit_of_measure' => $sales_unit_of_measure,
                            'base_unit_of_measure' => $base_unit_of_measure,
                            'purchase_unit_of_measure' => $purchase_unit_of_measure,
                            'name' => $name,
                            'description' => $description,
                            'search_description' => $search_description,
                            'techspecs' => $techspecs,
                            'image' => $image ? $image : '',
                            'image_class' => $image_class ?  $image_class : 0,
                            'gen_prod_posting_group' => $gen_prod_posting_group,
                            'vat_prod_posting_group' => $vat_prod_posting_group,
                            'inv_posting_group' => $inv_posting_group,
                        ];
                        $result = $this->productsService->create($params);
                        $out = [];
                        $headers = ["","NAV ID"];
                        $data = [];
                        $failed = false;
                        $recs = [];
                        $defaultID = null;
                        if($result->product)
                        {
                            $defaultID = $result->product->nav_id;
                            $data[] = [
                                "SYSTEM",
                                "{$result->product->nav_id} ({$result->product->id})"
                            ];
                        }
                        else
                        {
                            $data[] = [
                                "SYSTEM",
                                "<fg=red>FAILED</>"
                            ];
                            $recs[] = "ERROR";
                            $failed = true;
                        }
                        $out['companies'] = [];
                        
                        foreach($result->companies as $companyRec)
                        {

                            $companyResult = [
                                "company" => [
                                    "id" => $companyRec->company->id,
                                    "name" => $companyRec->company->name,
                                ],
                                "item_no" => null,
                                "error" => null,
                            ];
                            if($companyRec->itemCard)
                            {
                                $col_tag = '<fg=white>';
                                if($defaultID != $companyRec->itemCard->getNo())
                                $col_tag = '<fg=red>';
                                $data[] = [
                                    $companyRec->company->name,
                                    $col_tag . $companyRec->itemCard->getNo() . '</>'
                                ];
                                $recs[] = $companyRec->itemCard->getNo();
                            }
                            else
                            {
                                $data[] = [
                                    $companyRec->company->name,
                                    "<fg=red>ERROR</>"
                                ];
                                $recs[] = "ERROR";
                                $failed = true;
                            }
                        }
                        if(collect($recs)->unique()->count()> 1)
                        {
                            $failed = true;
                        }
                        $this->table($headers,$data);
                        if($failed)
                        {
                            $this->error("Failed : Item No does not match in all companies.");
                            break;
                        }
                        else
                            $this->info("DONE");
                    }
                }
                else
                {
                   echo "Required parameter is blank on row no. ". $i ."\n";
                }
            }
            catch(\Exception $e)
            {
                echo "Error ". $e ." processing record on row no. " .$i. "\n";
            } 
        }
    }
}