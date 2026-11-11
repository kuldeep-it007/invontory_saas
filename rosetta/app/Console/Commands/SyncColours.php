<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use Rosetta\Models\Company;
use Rosetta\Models\Product\Colour;
use Rosetta\Services\NAV\ColourServiceInterface;

class SyncColours extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:colours';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Colours from Navision';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $colourService;
    public function __construct(ColourServiceInterface $colourService)
    {
        $this->colourService = $colourService;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $companyId = config('nav.default_company');
        $company = Company::find($companyId);
        $navColours = $this->colourService->getColours($company);
        foreach ($navColours as $navColour)
        {
            if($navColour->getCollection_Code() != null && $navColour->getCode() != null)
            {
                $colour = Colour::where('code','=',$navColour->getCode())->where('collection_code', '=',$navColour->getCollection_Code())->first();
                if(!$colour) 
                {
                    $colour = new Colour;
                }
                $colour->code = $navColour->getCode();
                $colour->collection_code = $navColour->getCollection_Code();
                $colour->save();
            }
        }
    }
}
