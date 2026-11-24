<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockPoint;
use App\Services\SellerStockService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/*
|--------------------------------------------------------------------------
| Network stock and seller resolution (phase 3)
|--------------------------------------------------------------------------
|
| A cart line is sold by the holder of the stock points the buyer picks. A
| distributor may buy from the Company or any other distributor, never from
| itself; a dealer buys from its own distributor. Every holder of a product is
| visible as its own group of stock points. Runs the real tenant migrations on
| the in-memory SQLite test connection.
*/

beforeEach(function () {
    foreach ([
        '0001_01_01_000000_create_companies_table.php',
        '0001_01_01_000004_create_audit_logs_table.php',
        '0001_01_01_000006_create_categories_table.php',
        '0001_01_01_000006_create_distributors_table.php',
        '0001_01_01_000007_create_collections_table.php',
        '0001_01_01_000007_create_dealers_table.php',
        '0001_01_01_000008_create_colours_table.php',
        '0001_01_01_000009_create_products_table.php',
        '0001_01_01_000010_create_stock_points_table.php',
        '0001_01_01_000012_create_discounts_table.php',
        '0001_01_01_000013_create_stock_table.php',
        '0001_01_01_000018_create_stock_reservations_table.php',
        '2026_10_20_000001_add_code_active_to_catalog_tables.php',
        '2026_10_20_000002_add_scope_to_discounts_table.php',
        '2026_11_01_000001_add_price_to_products_table.php',
        '2026_11_29_000001_add_hsn_code_and_flexible_discounts.php',
        '2026_11_30_000001_add_catalog_ownership.php',
    ] as $file) {
        Artisan::call('migrate', ['--realpath' => true, '--force' => true, '--path' => [database_path('migrations/tenant/'.$file)]]);
    }

    $this->company = Company::query()->create(['name' => 'Acme']);
    $this->north = Distributor::query()->forceCreate(['company_id' => $this->company->id, 'name' => 'North']);
    $this->south = Distributor::query()->forceCreate(['company_id' => $this->company->id, 'name' => 'South']);
    $this->dealer = Dealer::query()->forceCreate(['distributor_id' => $this->north->id, 'name' => 'North Shop']);

    $categoryId = DB::table('categories')->insertGetId(['name' => 'Tiles', 'code' => 'TIL']);
    $collectionId = DB::table('collections')->insertGetId(['name' => 'Marble', 'code' => 'MAR', 'category_id' => $categoryId]);
    $colourId = DB::table('colours')->insertGetId(['name' => 'White', 'code' => 'WHT', 'collection_id' => $collectionId]);
    $make = function (?int $owner, string $sku) use ($collectionId, $colourId): Product {
        $product = new Product(['sku' => $sku, 'hsn_code' => '6907', 'name' => $sku, 'price' => 100, 'collection_id' => $collectionId, 'colour_id' => $colourId, 'tax_type' => 'gst18', 'uom' => 'box']);
        $product->owner_distributor_id = $owner;
        $product->save();

        return $product;
    };
    $this->companyProduct = $make(null, 'CO-1');
    $this->southProduct = $make($this->south->id, 'SO-1');

    $this->companyPoint = stockPointFor($this->company, 'Main warehouse');
    $this->northPoint = stockPointFor($this->north, 'North depot');
    $this->southPoint = stockPointFor($this->south, 'South depot');

    holdStock($this->company, $this->companyProduct, $this->companyPoint, 50);
    holdStock($this->south, $this->companyProduct, $this->southPoint, 8);
    holdStock($this->south, $this->southProduct, $this->southPoint, 20);

    $this->sellers = app(SellerStockService::class);
});

function stockPointFor(Model $owner, string $name): StockPoint
{
    $point = new StockPoint(['name' => $name, 'sort_order' => 0]);
    $point->owner()->associate($owner);
    $point->save();

    return $point;
}

function holdStock(Model $holder, Product $product, StockPoint $point, float $quantity): void
{
    Stock::query()->forceCreate([
        'stockable_type' => $holder->getMorphClass(),
        'stockable_id' => $holder->getKey(),
        'product_id' => $product->id,
        'stock_point_id' => $point->id,
        'quantity' => $quantity,
    ]);
}

describe('default seller', function () {
    it('sells a distributor the product from its owner', function () {
        expect($this->sellers->defaultSeller($this->north, $this->companyProduct->id)->is($this->company))->toBeTrue()
            ->and($this->sellers->defaultSeller($this->north, $this->southProduct->id)->is($this->south))->toBeTrue();
    });

    it('sells a dealer from its own distributor', function () {
        expect($this->sellers->defaultSeller($this->dealer, $this->southProduct->id)->is($this->north))->toBeTrue();
    });
});

describe('chosen stock points', function () {
    it('makes the holder of the chosen points the seller (distributor to distributor)', function () {
        $seller = $this->sellers->sellerFor($this->north, $this->companyProduct->id, [$this->southPoint->id]);

        expect($seller->is($this->south))->toBeTrue()
            ->and($this->sellers->totalAvailableAt($seller, $this->companyProduct->id))->toBe(8.0);
    });

    it('refuses buying from your own stock', function () {
        $this->sellers->sellerFor($this->north, $this->companyProduct->id, [$this->northPoint->id]);
    })->throws(UnprocessableEntityHttpException::class);

    it('keeps a dealer buying from its distributor and makes another holder the chain source', function () {
        [$seller, $source] = $this->sellers->resolveLine($this->dealer, $this->companyProduct->id, [$this->southPoint->id]);
        [$ownSeller, $ownSource] = $this->sellers->resolveLine($this->dealer, $this->companyProduct->id, [$this->northPoint->id]);

        expect($seller->is($this->north))->toBeTrue()
            ->and($source->is($this->south))->toBeTrue()
            ->and($ownSeller->is($this->north))->toBeTrue()
            ->and($ownSource)->toBeNull();
    });

    it('counts what a distributor can buy upstream in a dealer\'s availability', function () {
        // North holds none; the Company (the product's owner) holds 50.
        expect($this->sellers->totalAvailableForDealer($this->dealer, $this->companyProduct->id))->toBe(50.0);
    });

    it('refuses one line split across two sellers', function () {
        $this->sellers->sellerFor($this->north, $this->companyProduct->id, [$this->companyPoint->id, $this->southPoint->id]);
    })->throws(UnprocessableEntityHttpException::class);
});

describe('network holders of a product', function () {
    it('lists every holder as its own group of stock points, Company first', function () {
        $holders = $this->sellers->holdersForProduct($this->companyProduct->id);

        expect(array_column($holders, 'holder_name'))->toBe(['Acme', 'South'])
            ->and($holders[1]['points'][0]['available'])->toBe('8.000')
            ->and($holders[0]['orderable'])->toBeNull();
    });

    it('flags which holders a buyer may order from', function () {
        $forNorth = collect($this->sellers->holdersForProduct($this->companyProduct->id, $this->north))->pluck('orderable', 'holder_name')->all();
        $forDealer = collect($this->sellers->holdersForProduct($this->companyProduct->id, $this->dealer))->pluck('orderable', 'holder_name')->all();

        expect($forNorth)->toBe(['Acme' => true, 'South' => true])
            ->and($forDealer)->toBe(['Acme' => true, 'North' => true, 'South' => true]);
    });
});
