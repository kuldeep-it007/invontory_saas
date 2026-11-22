<?php

declare(strict_types=1);

use App\Http\Requests\StoreProductRequest;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Colour;
use App\Models\Discount;
use App\Models\Price;
use App\Models\Product;
use App\Services\PriceResolutionService;
use App\Services\ResolvedPrice;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/*
|--------------------------------------------------------------------------
| Discount priority, fixed/percent discounts, base-price fallback, HSN code
|--------------------------------------------------------------------------
|
| Only ONE discount applies: the highest-priority applicable one in the order
| Dealer > Distributor > Product > General. Runs the real tenant migrations on
| the in-memory SQLite test connection (no MySQL needed).
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
        '0001_01_01_000011_create_prices_table.php',
        '0001_01_01_000012_create_discounts_table.php',
        '2026_10_20_000001_add_code_active_to_catalog_tables.php',
        '2026_10_20_000002_add_scope_to_discounts_table.php',
        '2026_11_01_000001_add_price_to_products_table.php',
        '2026_11_29_000001_add_hsn_code_and_flexible_discounts.php',
    ] as $file) {
        Artisan::call('migrate', ['--realpath' => true, '--force' => true, '--path' => [database_path('migrations/tenant/'.$file)]]);
    }

    $companyId = DB::table('companies')->insertGetId(['name' => 'Acme', 'created_at' => now(), 'updated_at' => now()]);
    $this->distributorId = DB::table('distributors')->insertGetId(['company_id' => $companyId, 'name' => 'North', 'created_at' => now(), 'updated_at' => now()]);
    $this->otherDistributorId = DB::table('distributors')->insertGetId(['company_id' => $companyId, 'name' => 'South', 'created_at' => now(), 'updated_at' => now()]);
    $this->dealerId = DB::table('dealers')->insertGetId(['distributor_id' => $this->distributorId, 'name' => 'Shop A', 'created_at' => now(), 'updated_at' => now()]);
    $this->otherDealerId = DB::table('dealers')->insertGetId(['distributor_id' => $this->distributorId, 'name' => 'Shop B', 'created_at' => now(), 'updated_at' => now()]);

    $category = Category::query()->create(['name' => 'Tiles', 'code' => 'TIL']);
    $collection = Collection::query()->create(['name' => 'Marble', 'code' => 'MAR', 'category_id' => $category->id]);
    $colour = Colour::query()->create(['name' => 'White', 'code' => 'WHT', 'collection_id' => $collection->id]);
    $this->scope = ['category_id' => $category->id, 'collection_id' => $collection->id, 'colour_id' => $colour->id];
    $this->product = Product::query()->create([
        'sku' => 'MAR-WHT-01', 'hsn_code' => '6907', 'name' => 'Marble White', 'price' => 1000,
        'collection_id' => $collection->id, 'colour_id' => $colour->id, 'tax_type' => 'gst18', 'uom' => 'box',
    ]);
});

function discount(array $attributes): Discount
{
    return Discount::query()->create(array_merge(['type' => Discount::TYPE_CUSTOMER, 'value_type' => 'percent', 'percentage' => 0], $attributes));
}

function priceFor(Product $product, ?string $buyerType = null, ?int $buyerId = null): ResolvedPrice
{
    return app(PriceResolutionService::class)->resolveForProduct($product->fresh(), $buyerType, $buyerId);
}

describe('base price', function () {
    it('uses the product price when no price row exists', function () {
        expect(priceFor($this->product)->final)->toBe(1000.0);
    });

    it('prefers a product-level price row over the product price, and the product price over the collection row', function () {
        Price::query()->create(['level' => Price::LEVEL_COLLECTION, 'ref_id' => $this->product->collection_id, 'amount' => 700]);
        expect(priceFor($this->product)->final)->toBe(1000.0);

        Price::query()->create(['level' => Price::LEVEL_PRODUCT, 'ref_id' => $this->product->id, 'amount' => 900]);
        expect(priceFor($this->product)->final)->toBe(900.0);
    });

    it('falls back to the collection price when the product has no price at all', function () {
        $this->product->update(['price' => null]);
        Price::query()->create(['level' => Price::LEVEL_COLLECTION, 'ref_id' => $this->product->collection_id, 'amount' => 700]);

        expect(priceFor($this->product)->final)->toBe(700.0);
    });

    it('fails clearly when no base price exists anywhere', function () {
        $this->product->update(['price' => null]);

        expect(fn () => priceFor($this->product))->toThrow(InvalidArgumentException::class);
    });
});

describe('priority: Dealer > Distributor > Product > General, never combined', function () {
    it('applies a general discount when nothing more specific exists', function () {
        discount(['percentage' => 5]);

        $price = priceFor($this->product, 'dealer', $this->dealerId);

        expect($price->final)->toBe(950.0)->and($price->discountLevel)->toBe('general');
    });

    it('lets a product discount override a larger general discount', function () {
        discount(['percentage' => 30]);
        discount(['percentage' => 10, 'product_id' => $this->product->id]);

        $price = priceFor($this->product, 'dealer', $this->dealerId);

        expect($price->final)->toBe(900.0)->and($price->discountLevel)->toBe('product');
    });

    it('lets the dealer\'s distributor discount override product and general discounts', function () {
        discount(['percentage' => 30]);
        discount(['percentage' => 25, 'product_id' => $this->product->id]);
        discount(['percentage' => 12, 'buyer_type' => 'distributor', 'buyer_id' => $this->distributorId]);

        $price = priceFor($this->product, 'dealer', $this->dealerId);

        expect($price->final)->toBe(880.0)->and($price->discountLevel)->toBe('distributor');
    });

    it('lets a dealer-specific discount override every other level', function () {
        discount(['percentage' => 30]);
        discount(['percentage' => 25, 'product_id' => $this->product->id]);
        discount(['percentage' => 20, 'buyer_type' => 'distributor', 'buyer_id' => $this->distributorId]);
        discount(['value_type' => 'fixed', 'amount' => 50, 'buyer_type' => 'dealer', 'buyer_id' => $this->dealerId]);

        $price = priceFor($this->product, 'dealer', $this->dealerId);

        expect($price->final)->toBe(950.0)
            ->and($price->discountLevel)->toBe('dealer')
            ->and($price->discountType)->toBe('fixed')
            ->and($price->discountAmount)->toBe(50.0);
    });

    it('never stacks two applicable discounts', function () {
        discount(['percentage' => 10]);
        discount(['type' => Discount::TYPE_ONLINE, 'percentage' => 10, 'product_id' => $this->product->id]);

        // 10% only (product level), not 100 * 0.9 * 0.9.
        expect(priceFor($this->product)->final)->toBe(900.0);
    });

    it('ignores discounts for another dealer, another distributor, another product or inactive ones', function () {
        discount(['percentage' => 40, 'buyer_type' => 'dealer', 'buyer_id' => $this->otherDealerId]);
        discount(['percentage' => 40, 'buyer_type' => 'distributor', 'buyer_id' => $this->otherDistributorId]);
        $other = Product::query()->create(array_merge(['sku' => 'OTHER', 'hsn_code' => '6907', 'name' => 'Other', 'price' => 10, 'tax_type' => 'gst18', 'uom' => 'box'], array_intersect_key($this->scope, array_flip(['collection_id', 'colour_id']))));
        discount(['percentage' => 40, 'product_id' => $other->id]);
        discount(['percentage' => 40, 'is_active' => false]);

        expect(priceFor($this->product, 'dealer', $this->dealerId)->final)->toBe(1000.0);
    });

    it('applies a distributor-specific discount when the distributor itself buys', function () {
        discount(['percentage' => 15, 'buyer_type' => 'distributor', 'buyer_id' => $this->distributorId]);
        discount(['percentage' => 5]);

        $price = priceFor($this->product, 'distributor', $this->distributorId);

        expect($price->final)->toBe(850.0)->and($price->discountLevel)->toBe('distributor');
    });

    it('breaks a tie at the same level by the more specific catalog scope, then by the larger saving', function () {
        discount(['percentage' => 20, 'category_id' => $this->scope['category_id']]);
        discount(['percentage' => 8, 'colour_id' => $this->scope['colour_id']]);
        expect(priceFor($this->product)->final)->toBe(920.0); // colour beats category

        discount(['value_type' => 'fixed', 'amount' => 100, 'colour_id' => $this->scope['colour_id']]);
        expect(priceFor($this->product)->final)->toBe(900.0); // same scope: larger saving wins
    });

    it('caps a fixed discount larger than the price at the price', function () {
        discount(['value_type' => 'fixed', 'amount' => 5000]);

        expect(priceFor($this->product)->final)->toBe(0.0);
    });

    it('resolves a whole listing with the same results as single lookups', function () {
        discount(['percentage' => 10, 'product_id' => $this->product->id]);

        $many = app(PriceResolutionService::class)->resolveMany(Product::query()->get());

        expect($many[$this->product->id]->final)->toBe(priceFor($this->product)->final);
    });
});

describe('HSN code', function () {
    it('accepts 4, 6 and 8 digit codes and rejects anything else', function (string $code, bool $valid) {
        $rules = ['hsn_code' => (new StoreProductRequest)->rules()['hsn_code']];

        expect(Validator::make(['hsn_code' => $code], $rules)->passes())->toBe($valid);
    })->with([
        ['6907', true], ['690721', true], ['69072100', true],
        ['690', false], ['69072', false], ['6907210', false], ['690721001', false], ['69A7', false], ['', false],
    ]);

    it('is mandatory when creating a product', function () {
        $rules = ['hsn_code' => (new StoreProductRequest)->rules()['hsn_code']];

        expect(Validator::make([], $rules)->fails())->toBeTrue();
    });
});
