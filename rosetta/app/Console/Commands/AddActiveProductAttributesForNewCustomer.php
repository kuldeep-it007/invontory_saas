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


class AddActiveProductAttributesForNewCustomer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'add:customer_active_product_attributes_new_customer {companyId} {customerType} {customerId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Adds Active Product Attributes for a new Customer (customerType should be either distributor or dealer) based on Active Product Attributes of Company';

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
        $customerType = $this->argument('customerType');
        $customerId = $this->argument('customerId');
        $this->activeProductAttributesService->load($companyId, $customerType, $customerId);
    }
}
