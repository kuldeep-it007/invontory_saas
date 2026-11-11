<?php

namespace Rosetta\Console\Commands;

use Illuminate\Console\Command;
use Rosetta\Models\Company;
use Rosetta\Models\Dealer;
use Rosetta\Models\Distributor;
use Rosetta\Models\Product\ActiveProductAttributes;
use Rosetta\Models\Product\Category;
use Rosetta\Models\Product\Collection;
use Rosetta\Models\Product\Colour;
use Rosetta\Models\User;
use Rosetta\Services\ActiveProductAttributes\ActiveProductAttributesServiceInterface;


class AddCustomerActiveProductAttributes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'add:customer_active_product_attributes {companyId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Adds Active Product Attributes for Distributors and Dealers based on Active Product Attributes of Company';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $activeProductAttributesService;
    public function __construct(ActiveProductAttributesServiceInterface $activeProductAttributesService)
    {
        $this->activeProductAttributesService = $activeProductAttributesService;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $companyId = $this->argument('companyId');
        $distributorIds = User::whereNotNull('distributor_id')->whereNull('deleted_at')->distinct()->get(['distributor_id'])->pluck('distributor_id')->toArray();
        foreach($distributorIds as $distributorId)
        {
            $this->activeProductAttributesService->load($companyId, 'distributor', $distributorId);
        }
        $dealerIds = User::whereNotNull('dealer_id')->whereNull('deleted_at')->distinct()->get(['dealer_id'])->pluck('dealer_id')->toArray();
        foreach($dealerIds as $dealerId)
        {
            $this->activeProductAttributesService->load($companyId, 'dealer', $dealerId);
        }
    }
}
