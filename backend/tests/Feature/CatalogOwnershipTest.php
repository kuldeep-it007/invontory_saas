<?php

declare(strict_types=1);

use App\Auth\Roles;
use App\Http\Requests\StoreDiscountRequest;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Colour;
use App\Models\Discount;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogService;
use App\Services\CatalogVisibilityService;
use App\Services\PriceResolutionService;
use App\Services\PriceService;
use App\Support\CatalogOwner;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/*
|--------------------------------------------------------------------------
| Catalogue ownership and isolation (phase 2)
|--------------------------------------------------------------------------
|
| The Company and each Distributor own and manage separate catalogues. The
| Company cannot see distributor-owned rows at all (query-level scope); a
| Distributor sees the network but can only manage its own rows. Runs the real
| tenant migrations on the in-memory SQLite test connection, which also backs
| the `tenant` connection the validation rules use.
*/

beforeEach(function () {
    config(['database.connections.tenant' => config('database.connections.'.config('database.default'))]);
    DB::purge('tenant');
    DB::connection('tenant')->setPdo(DB::connection()->getPdo());

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
        '0001_01_01_000015_create_active_product_attributes_table.php',
        '2026_10_20_000001_add_code_active_to_catalog_tables.php',
        '2026_10_20_000002_add_scope_to_discounts_table.php',
        '2026_11_01_000001_add_price_to_products_table.php',
        '2026_11_29_000001_add_hsn_code_and_flexible_discounts.php',
        '2026_11_30_000001_add_catalog_ownership.php',
    ] as $file) {
        Artisan::call('migrate', ['--realpath' => true, '--force' => true, '--path' => [database_path('migrations/tenant/'.$file)]]);
    }

    $companyId = DB::table('companies')->insertGetId(['name' => 'Acme', 'created_at' => now(), 'updated_at' => now()]);
    $this->north = DB::table('distributors')->insertGetId(['company_id' => $companyId, 'name' => 'North', 'created_at' => now(), 'updated_at' => now()]);
    $this->south = DB::table('distributors')->insertGetId(['company_id' => $companyId, 'name' => 'South', 'created_at' => now(), 'updated_at' => now()]);
    $this->northDealer = DB::table('dealers')->insertGetId(['distributor_id' => $this->north, 'name' => 'North Shop', 'created_at' => now(), 'updated_at' => now()]);
    $this->southDealer = DB::table('dealers')->insertGetId(['distributor_id' => $this->south, 'name' => 'South Shop', 'created_at' => now(), 'updated_at' => now()]);

    $this->companyUser = (new User)->forceFill(['id' => 1, 'email' => 'admin@acme.test']);
    $this->northUser = (new User)->forceFill(['id' => 2, 'email' => 'north@acme.test', 'distributor_id' => $this->north]);
    $this->southUser = (new User)->forceFill(['id' => 3, 'email' => 'south@acme.test', 'distributor_id' => $this->south]);
    $this->dealerUser = (new User)->forceFill(['id' => 4, 'email' => 'shop@acme.test', 'distributor_id' => $this->north, 'dealer_id' => $this->northDealer]);

    // One catalogue for the Company and one for North, with the same names.
    $this->companyProduct = makeOwnedProduct(null, 'SKU-1');
    $this->northProduct = makeOwnedProduct($this->north, 'SKU-1');
});

/** Build category → collection → colour → product for one owner (null = Company). */
function makeOwnedProduct(?int $ownerDistributorId, string $sku, float $price = 1000): Product
{
    $catalog = app(CatalogService::class);
    $category = $catalog->createCategory(['name' => 'Tiles'], $ownerDistributorId);
    $collection = $catalog->createCollection(['name' => 'Marble', 'category_id' => $category->id], $ownerDistributorId);
    $colour = $catalog->createColour(['name' => 'White', 'collection_id' => $collection->id], $ownerDistributorId);

    $product = new Product([
        'sku' => $sku, 'hsn_code' => '6907', 'name' => 'Marble White', 'price' => $price,
        'collection_id' => $collection->id, 'colour_id' => $colour->id, 'tax_type' => 'gst18', 'uom' => 'box',
    ]);
    $product->owner_distributor_id = $ownerDistributorId;
    $product->save();

    return $product;
}

function ownedDiscount(?int $ownerDistributorId, array $attributes): Discount
{
    return app(PriceService::class)->createDiscount(
        array_merge(['type' => Discount::TYPE_CUSTOMER, 'value_type' => 'percent'], $attributes),
        $ownerDistributorId,
    );
}

describe('query-level isolation', function () {
    it('hides distributor-owned catalogue rows from Company users', function () {
        $this->actingAs($this->companyUser);

        expect(Product::query()->pluck('id')->all())->toBe([$this->companyProduct->id])
            ->and(Product::query()->find($this->northProduct->id))->toBeNull()
            ->and(Category::query()->count())->toBe(1)
            ->and(Collection::query()->count())->toBe(1)
            ->and(Colour::query()->count())->toBe(1);
    });

    it('shows the network catalogue to distributor and dealer users', function () {
        $this->actingAs($this->southUser);
        expect(Product::query()->count())->toBe(2);

        $this->actingAs($this->dealerUser);
        expect(Product::query()->count())->toBe(2);
    });

    it('does not scope queries without an authenticated user (jobs, console)', function () {
        expect(Product::query()->count())->toBe(2);
    });

    it('shows a distributor the network catalogue without its own products', function () {
        $southProduct = makeOwnedProduct($this->south, 'SKU-S');
        $catalog = fn () => collect(app(CatalogVisibilityService::class)->paginateForAll()->items())->pluck('id')->sort()->values()->all();

        $this->actingAs($this->northUser);
        expect($catalog())->toBe([$this->companyProduct->id, $southProduct->id]);

        $this->actingAs($this->dealerUser);
        expect($catalog())->toBe([$this->companyProduct->id, $this->northProduct->id, $southProduct->id]);

        $this->actingAs($this->companyUser);
        expect($catalog())->toBe([$this->companyProduct->id]);
    });
});

describe('management rights', function () {
    it('lets each owner manage only its own rows and answers 404 otherwise', function () {
        CatalogOwner::assertManages($this->companyUser, $this->companyProduct);
        CatalogOwner::assertManages($this->northUser, $this->northProduct);

        expect(fn () => CatalogOwner::assertManages($this->northUser, $this->companyProduct))->toThrow(NotFoundHttpException::class)
            ->and(fn () => CatalogOwner::assertManages($this->southUser, $this->northProduct))->toThrow(NotFoundHttpException::class)
            ->and(fn () => CatalogOwner::assertManages($this->companyUser, $this->northProduct))->toThrow(NotFoundHttpException::class);
    });

    it('refuses dealers a catalogue of their own', function () {
        expect(fn () => CatalogOwner::keyFor($this->dealerUser))->toThrow(AccessDeniedHttpException::class);
    });

    it('grants distributors catalog.manage', function () {
        expect(Roles::abilitiesByRole()[Roles::DISTRIBUTOR])->toContain('catalog.manage')
            ->and(Roles::abilitiesByRole()[Roles::DEALER])->not->toContain('catalog.manage');
    });

    it('lists the caller\'s own rows for owner=mine', function () {
        $this->actingAs($this->northUser);
        $mine = Product::query()->tap(CatalogOwner::listFilter($this->northUser, 'mine'))->pluck('id')->all();

        $this->actingAs($this->companyUser);
        $companyMine = Product::query()->tap(CatalogOwner::listFilter($this->companyUser, 'mine'))->pluck('id')->all();

        expect($mine)->toBe([$this->northProduct->id])
            ->and($companyMine)->toBe([$this->companyProduct->id]);
    });
});

describe('per-owner uniqueness', function () {
    it('allows the same SKU and code for different owners', function () {
        expect($this->northProduct->sku)->toBe($this->companyProduct->sku)
            ->and(Category::query()->withoutGlobalScopes()->pluck('code')->unique()->all())->toBe(['TILES']);
    });

    it('rejects a duplicate SKU within one owner at the database', function () {
        expect(fn () => makeOwnedProduct($this->north, 'SKU-1'))->toThrow(QueryException::class);
    });

    it('validates SKU uniqueness per owner', function () {
        $rule = fn (User $user) => ['sku' => [CatalogOwner::ownedUniqueRule($user, 'products', 'sku')]];

        expect(Validator::make(['sku' => 'SKU-1'], $rule($this->northUser))->fails())->toBeTrue()
            ->and(Validator::make(['sku' => 'SKU-1'], $rule($this->southUser))->fails())->toBeFalse();
    });

    it('only lets an owner build on its own hierarchy', function () {
        $rule = fn (User $user) => ['collection_id' => [CatalogOwner::ownedExistsRule($user, 'collections')]];

        expect(Validator::make(['collection_id' => $this->companyProduct->collection_id], $rule($this->northUser))->fails())->toBeTrue()
            ->and(Validator::make(['collection_id' => $this->northProduct->collection_id], $rule($this->northUser))->fails())->toBeFalse()
            ->and(Validator::make(['collection_id' => $this->northProduct->collection_id], $rule($this->companyUser))->fails())->toBeTrue();
    });
});

describe('distributor discounts', function () {
    it('lets a distributor target only its own dealers', function () {
        $rules = fn (array $data) => Validator::make($data, StoreDiscountRequest::sharedRules($this->northUser, $data['buyer_type']));

        expect($rules(['buyer_type' => 'dealer', 'buyer_id' => $this->northDealer])->fails())->toBeFalse()
            ->and($rules(['buyer_type' => 'dealer', 'buyer_id' => $this->southDealer])->fails())->toBeTrue()
            ->and($rules(['buyer_type' => 'distributor', 'buyer_id' => $this->north])->fails())->toBeTrue()
            ->and($rules(['buyer_type' => 'distributor', 'buyer_id' => $this->south])->fails())->toBeFalse();
    });

    it('rejects a discount scoped to another owner\'s product', function () {
        $validator = Validator::make(
            ['product_id' => $this->companyProduct->id, 'buyer_type' => null],
            StoreDiscountRequest::sharedRules($this->northUser, null),
        );

        expect($validator->fails())->toBeTrue();
    });

    it('lists discounts per owner', function () {
        ownedDiscount(null, ['percentage' => 5]);
        ownedDiscount($this->north, ['percentage' => 7]);

        $service = app(PriceService::class);

        expect(collect($service->paginateDiscounts(CatalogOwner::COMPANY)->items())->pluck('percentage')->map(fn ($p) => (float) $p)->all())->toBe([5.0])
            ->and(collect($service->paginateDiscounts($this->north)->items())->pluck('percentage')->map(fn ($p) => (float) $p)->all())->toBe([7.0]);
    });
});

describe('pricing by owner', function () {
    it('applies Company discounts only to Company products and distributor discounts only to its own', function () {
        ownedDiscount(null, ['percentage' => 10]);
        ownedDiscount($this->north, ['percentage' => 20]);

        $prices = app(PriceResolutionService::class);

        expect($prices->resolveForProduct($this->companyProduct->fresh())->final)->toBe(900.0)
            ->and($prices->resolveForProduct($this->northProduct->fresh())->final)->toBe(800.0);
    });

    it('applies a distributor\'s discount for its own dealer on a Company product', function () {
        ownedDiscount(null, ['percentage' => 10]);
        ownedDiscount($this->north, ['percentage' => 25, 'buyer_type' => 'dealer', 'buyer_id' => $this->northDealer]);

        $prices = app(PriceResolutionService::class);

        expect($prices->resolveForProduct($this->companyProduct->fresh(), 'dealer', $this->northDealer)->final)->toBe(750.0)
            ->and($prices->resolveForProduct($this->companyProduct->fresh(), 'dealer', $this->southDealer)->final)->toBe(900.0);
    });

    it('ignores another distributor\'s discounts', function () {
        ownedDiscount($this->south, ['percentage' => 50]);

        expect(app(PriceResolutionService::class)->resolveForProduct($this->northProduct->fresh())->final)->toBe(1000.0);
    });
});
