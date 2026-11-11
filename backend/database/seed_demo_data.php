<?php

/**
 * One-off local demo-data seeder. Provisions (or reuses) a `demo` tenant
 * resolvable at `localhost` and fills its database with realistic sample data
 * so the app can be visualized and tested end-to-end:
 *
 *   • 1 Company (the tenant singleton) with stock points
 *   • Catalog hierarchy: Categories -> Collections -> Colours -> Products
 *   • Prices (collection + product level, with markup examples)
 *   • Distributors + their Dealers (with payment terms)
 *   • Stock balances per product / stock point
 *   • Sample Orders with Order_Lines across lifecycle states
 *   • Sample Payments
 *
 * Run inside the api container (recommended):
 *   docker compose exec api php artisan tinker database/seed_demo_data.php
 *
 * Or locally if the toolchain is installed:
 *   php artisan tinker database/seed_demo_data.php
 *
 * Idempotent: re-running reuses the existing tenant/company and tops up any
 * missing catalog/party rows without creating duplicates.
 *
 * Login after seeding:  admin@demo.test / password123  (host: localhost)
 */

use App\Models\Category;
use App\Models\Collection;
use App\Models\Colour;
use App\Models\Company;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderStatus;
use App\Models\Payment;
use App\Models\Price;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockPoint;
use App\Models\Tenant;
use App\Services\TenantProvisioningService;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/*
|--------------------------------------------------------------------------
| 1. Ensure a demo tenant exists (resolvable at http://localhost)
|--------------------------------------------------------------------------
*/
$svc = app(TenantProvisioningService::class);
$plan = $svc->ensureStarterPlan();

$tenant = Tenant::query()->where('subdomain', 'demo')->first();

if ($tenant === null) {
    try {
        $result = $svc->provision([
            'subdomain' => 'demo',
            'domain' => 'localhost',
            'plan_id' => $plan->id,
            'currency' => 'INR',
            'backorders_enabled' => true,
            'dealer_stock_enabled' => false,
            'company_name' => 'Demo Textiles Pvt Ltd',
            'company_gstin' => '27ABCDE1234F1Z5',
            'admin_name' => 'Demo Admin',
            'admin_email' => 'admin@demo.test',
            'admin_password' => 'password123',
        ]);
        $tenant = $result['tenant'];
        fwrite(STDOUT, "Provisioned demo tenant id={$tenant->getTenantKey()}".PHP_EOL);
    } catch (ConflictHttpException $e) {
        // Domain already taken by a previous run but subdomain lookup missed;
        // re-resolve by domain.
        $tenant = Tenant::query()->where('subdomain', 'demo')->firstOrFail();
    }
} else {
    fwrite(STDOUT, "Reusing existing demo tenant id={$tenant->getTenantKey()}".PHP_EOL);
}

/*
|--------------------------------------------------------------------------
| 2. Seed the tenant database (runs inside tenant context)
|--------------------------------------------------------------------------
*/
$tenant->run(function (): void {
    $company = Company::query()->firstOrFail();

    // ---- Stock points owned by the Company ----------------------------------
    // `owner` is a polymorphic relation (not mass-assignable), so resolve via
    // the Company's morphMany and create through the relation when missing.
    $mainWarehouse = $company->stockPoints()->where('name', 'Main Warehouse')->first()
        ?? $company->stockPoints()->create(['name' => 'Main Warehouse', 'sort_order' => 1]);
    $overflow = $company->stockPoints()->where('name', 'Overflow Depot')->first()
        ?? $company->stockPoints()->create(['name' => 'Overflow Depot', 'sort_order' => 2]);

    // ---- Catalog hierarchy ---------------------------------------------------
    // Category -> Collections -> Colours, then Products with prices + stock.
    $catalog = [
        'Fabrics' => [
            'code' => 'CAT-FAB',
            'collections' => [
                'Cotton' => ['code' => 'COL-COT', 'base' => 450.00, 'colours' => ['White', 'Beige', 'Sky Blue']],
                'Silk' => ['code' => 'COL-SLK', 'base' => 1200.00, 'markup' => ['mup', 12.0], 'colours' => ['Maroon', 'Gold']],
            ],
        ],
        'Apparel' => [
            'code' => 'CAT-APP',
            'collections' => [
                'Shirts' => ['code' => 'COL-SHT', 'base' => 799.00, 'colours' => ['Navy', 'White', 'Olive']],
                'Trousers' => ['code' => 'COL-TRS', 'base' => 1099.00, 'markup' => ['markup', 8.0], 'colours' => ['Black', 'Khaki']],
            ],
        ],
    ];

    $allProducts = [];
    $skuCounter = 1;

    foreach ($catalog as $categoryName => $categoryDef) {
        $category = Category::query()->firstOrCreate(
            ['code' => $categoryDef['code']],
            ['name' => $categoryName, 'active' => true],
        );

        foreach ($categoryDef['collections'] as $collectionName => $collectionDef) {
            $collection = Collection::query()->firstOrCreate(
                ['code' => $collectionDef['code']],
                ['category_id' => $category->id, 'name' => $collectionName, 'active' => true],
            );

            // Collection-level price (optionally with a markup/MUP example).
            Price::query()->firstOrCreate(
                ['level' => Price::LEVEL_COLLECTION, 'ref_id' => $collection->id],
                [
                    'amount' => $collectionDef['base'],
                    'markup_type' => $collectionDef['markup'][0] ?? null,
                    'markup_value' => $collectionDef['markup'][1] ?? null,
                ],
            );

            foreach ($collectionDef['colours'] as $colourName) {
                $colour = Colour::query()->firstOrCreate(
                    ['collection_id' => $collection->id, 'name' => $colourName],
                    ['code' => 'CLR-'.strtoupper(substr($collectionDef['code'], 4)).'-'.strtoupper(substr($colourName, 0, 3)), 'active' => true],
                );

                $sku = sprintf('SKU-%04d', $skuCounter++);
                $product = Product::query()->firstOrCreate(
                    ['sku' => $sku],
                    [
                        'name' => "{$collectionName} {$colourName}",
                        'collection_id' => $collection->id,
                        'colour_id' => $colour->id,
                        'tax_type' => 'GST18',
                        'uom' => 'MTR',
                    ],
                );

                // A product-level price override on roughly every third product.
                if ($skuCounter % 3 === 0) {
                    Price::query()->firstOrCreate(
                        ['level' => Price::LEVEL_PRODUCT, 'ref_id' => $product->id],
                        ['amount' => round($collectionDef['base'] * 1.05, 2)],
                    );
                }

                // Stock balances at both company stock points. `stockable` is
                // a polymorphic relation (not mass-assignable), so associate
                // the owner on the instance before saving.
                $makeStock = static function (StockPoint $point, float $qty, float $threshold) use ($company, $product): void {
                    $exists = Stock::query()
                        ->where('stockable_type', $company->getMorphClass())
                        ->where('stockable_id', $company->id)
                        ->where('product_id', $product->id)
                        ->where('stock_point_id', $point->id)
                        ->exists();
                    if ($exists) {
                        return;
                    }
                    $stock = new Stock([
                        'product_id' => $product->id,
                        'stock_point_id' => $point->id,
                        'quantity' => $qty,
                        'low_stock_threshold' => $threshold,
                    ]);
                    $stock->owner()->associate($company);
                    $stock->save();
                };

                $makeStock($mainWarehouse, 500 + ($skuCounter * 10), 50);
                // Make a couple of rows breach their low-stock threshold so the
                // low-stock report has something to show.
                $makeStock($overflow, $skuCounter % 4 === 0 ? 20 : 120, 30);

                $allProducts[] = $product;
            }
        }
    }

    // ---- Distributors + Dealers ---------------------------------------------
    $distributorDefs = [
        ['name' => 'Northern Distributors', 'city' => 'Delhi', 'credit' => 500000, 'dealers' => ['Capital Retail', 'Metro Mart']],
        ['name' => 'Western Supplies Co', 'city' => 'Mumbai', 'credit' => 750000, 'dealers' => ['Harbour Traders', 'Seaside Stores', 'City Centre Shop']],
    ];

    $distributors = [];
    foreach ($distributorDefs as $i => $def) {
        $distributor = Distributor::query()->firstOrCreate(
            ['name' => $def['name']],
            [
                'company_id' => $company->id,
                'contact_name' => 'Manager '.($i + 1),
                'contact_email' => 'sales@'.str_replace(' ', '', strtolower($def['name'])).'.test',
                'contact_phone' => '+91-98765-4'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'gstin' => sprintf('27DISTR%04dF1Z%d', $i + 1, $i),
                'address' => $def['city'].', India',
                'payment_terms' => [
                    'credit_limit' => $def['credit'],
                    'overdue_balance_limit' => (int) round($def['credit'] * 0.2),
                    'overdue_hold_days' => 30,
                    'payment_type' => 'credit',
                ],
            ],
        );

        foreach ($def['dealers'] as $j => $dealerName) {
            Dealer::query()->firstOrCreate(
                ['distributor_id' => $distributor->id, 'name' => $dealerName],
                [
                    'contact_name' => 'Owner '.($j + 1),
                    'contact_email' => 'contact@'.str_replace(' ', '', strtolower($dealerName)).'.test',
                    'contact_phone' => '+91-91234-5'.str_pad((string) $j, 4, '0', STR_PAD_LEFT),
                    'gstin' => sprintf('27DEALR%02d%02dF1Z%d', $i, $j, $j),
                    'address' => $def['city'].' area, India',
                    'payment_terms' => [
                        'credit_limit' => 100000,
                        'overdue_balance_limit' => 20000,
                        'overdue_hold_days' => 15,
                        'payment_type' => 'credit',
                    ],
                ],
            );
        }

        $distributors[] = $distributor;
    }

    // ---- Sample orders (Distributor buying from Company) --------------------
    // Only create sample orders once (keyed off whether any order exists).
    if (Order::query()->doesntExist() && $allProducts !== []) {
        $statuses = [
            OrderStatus::PLACED,
            OrderStatus::CONFIRMED,
            OrderStatus::DELIVERED,
            OrderStatus::CLOSED,
        ];

        foreach ($distributors as $di => $distributor) {
            foreach ($statuses as $si => $status) {
                $lineProducts = array_slice($allProducts, ($di + $si) % max(1, count($allProducts)), 2);
                if ($lineProducts === []) {
                    $lineProducts = [$allProducts[0]];
                }

                $order = Order::query()->create([
                    'buyer_type' => $distributor->getMorphClass(),
                    'buyer_id' => $distributor->id,
                    'seller_type' => $company->getMorphClass(),
                    'seller_id' => $company->id,
                    'status' => $status,
                    'order_type' => Order::TYPE_STANDARD,
                    'total' => 0,
                ]);

                $total = 0.0;
                foreach ($lineProducts as $k => $product) {
                    $unit = 500 + ($k * 125) + ($si * 50);
                    $qty = 10 + ($k * 5) + ($si * 2);
                    $discount = $k === 0 ? 0 : 50;
                    $lineTotal = ($unit * $qty) - $discount;
                    $total += $lineTotal;

                    OrderLine::query()->create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'quantity' => $qty,
                        'unit_price' => $unit,
                        'discount' => $discount,
                    ]);
                }

                $order->update(['total' => round($total, 2)]);
            }
        }

        // ---- Sample payments against the first distributor ------------------
        $firstDistributor = $distributors[0];
        Payment::query()->create([
            'buyer_type' => $firstDistributor->getMorphClass(),
            'buyer_id' => $firstDistributor->id,
            'amount' => 25000.00,
            'method' => 'bank_transfer',
            'reference' => 'NEFT-DEMO-0001',
            'paid_at' => now()->subDays(10)->toDateString(),
        ]);
        Payment::query()->create([
            'buyer_type' => $firstDistributor->getMorphClass(),
            'buyer_id' => $firstDistributor->id,
            'amount' => 12000.00,
            'method' => 'upi',
            'reference' => 'UPI-DEMO-0002',
            'paid_at' => now()->subDays(3)->toDateString(),
        ]);
    }

    // ---- Summary -------------------------------------------------------------
    fwrite(STDOUT, 'Seeded tenant data:'.PHP_EOL);
    fwrite(STDOUT, '  categories    = '.Category::query()->count().PHP_EOL);
    fwrite(STDOUT, '  collections   = '.Collection::query()->count().PHP_EOL);
    fwrite(STDOUT, '  colours       = '.Colour::query()->count().PHP_EOL);
    fwrite(STDOUT, '  products      = '.Product::query()->count().PHP_EOL);
    fwrite(STDOUT, '  prices        = '.Price::query()->count().PHP_EOL);
    fwrite(STDOUT, '  stock_points  = '.StockPoint::query()->count().PHP_EOL);
    fwrite(STDOUT, '  stock rows    = '.Stock::query()->count().PHP_EOL);
    fwrite(STDOUT, '  distributors  = '.Distributor::query()->count().PHP_EOL);
    fwrite(STDOUT, '  dealers       = '.Dealer::query()->count().PHP_EOL);
    fwrite(STDOUT, '  orders        = '.Order::query()->count().PHP_EOL);
    fwrite(STDOUT, '  order_lines   = '.OrderLine::query()->count().PHP_EOL);
    fwrite(STDOUT, '  payments      = '.Payment::query()->count().PHP_EOL);
});

fwrite(STDOUT, PHP_EOL.'Done. Login: admin@demo.test / password123 at host localhost'.PHP_EOL);
