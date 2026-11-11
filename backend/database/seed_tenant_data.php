<?php

/**
 * Reusable local demo-data seeder for an EXISTING tenant.
 *
 * Fills a tenant's database with realistic sample data so the app can be
 * visualized/tested end-to-end:
 *
 *   • Catalog hierarchy: Categories -> Collections -> Colours -> Products
 *   • Prices (collection + product level, with markup examples)
 *   • Stock balances per product / stock point (some below threshold)
 *   • Distributors + their Dealers (with payment terms)
 *   • Sample Orders with Order_Lines across lifecycle states
 *   • Sample Payments
 *
 * Target the tenant by subdomain via the TENANT_SUBDOMAIN env var (defaults to
 * `acme`). The tenant must already exist (be provisioned) — this script does
 * NOT create tenants; it only seeds data into the tenant's own database.
 *
 * Run inside the api container:
 *   docker compose exec -e TENANT_SUBDOMAIN=acme api php artisan tinker database/seed_tenant_data.php
 *
 * Idempotent: re-running tops up missing catalog/party rows without creating
 * duplicates; orders/payments are only created when the tenant has none yet.
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
use App\Auth\Roles;
use App\Models\Stock;
use App\Models\StockPoint;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

$subdomain = getenv('TENANT_SUBDOMAIN') ?: 'acme';

$tenant = Tenant::query()->where('subdomain', $subdomain)->first();

if ($tenant === null) {
    fwrite(STDERR, "No tenant found with subdomain [{$subdomain}]. Provision it first.".PHP_EOL);

    return;
}

fwrite(STDOUT, "Seeding tenant subdomain={$subdomain} id={$tenant->getTenantKey()}".PHP_EOL);

$tenant->run(function (): void {
    $company = Company::query()->first();

    if ($company === null) {
        fwrite(STDERR, 'Tenant has no Company row; cannot seed tenant-owned data.'.PHP_EOL);

        return;
    }

    // ---- Stock points owned by the Company ----------------------------------
    $mainWarehouse = $company->stockPoints()->where('name', 'Main Warehouse')->first()
        ?? $company->stockPoints()->create(['name' => 'Main Warehouse', 'sort_order' => 1]);
    $overflow = $company->stockPoints()->where('name', 'Overflow Depot')->first()
        ?? $company->stockPoints()->create(['name' => 'Overflow Depot', 'sort_order' => 2]);

    // ---- Catalog hierarchy ---------------------------------------------------
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

                if ($skuCounter % 3 === 0) {
                    Price::query()->firstOrCreate(
                        ['level' => Price::LEVEL_PRODUCT, 'ref_id' => $product->id],
                        ['amount' => round($collectionDef['base'] * 1.05, 2)],
                    );
                }

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

    // ---- Demo portal login users for distributors / dealers (R8.6) ----------
    // Give the first distributor and one of its dealers a login user so the
    // multi-user portal (own-party auto-scoped order history) is demonstrable
    // out of the box. Each user is linked to its party and carries the matching
    // Distributor/Dealer role; password is "password123" like the admin demo.
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $seedPortalUser = function (string $name, string $email, string $role, array $link): void {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            array_merge(['name' => $name, 'password' => 'password123'], $link),
        );

        // Ensure the link is set even if the row pre-existed without it.
        $user->forceFill($link)->save();

        if (! $user->hasRole($role)) {
            $user->assignRole(Role::findByName($role, 'web'));
        }
    };

    $demoDistributor = $distributors[0] ?? null;
    if ($demoDistributor !== null) {
        $seedPortalUser(
            'Northern Portal User',
            'distributor@acme.test',
            Roles::DISTRIBUTOR,
            ['distributor_id' => $demoDistributor->id],
        );

        $demoDealer = Dealer::query()->where('distributor_id', $demoDistributor->id)->first();
        if ($demoDealer !== null) {
            $seedPortalUser(
                'Capital Portal User',
                'dealer@acme.test',
                Roles::DEALER,
                ['dealer_id' => $demoDealer->id],
            );
        }
    }

    // ---- Distributor stock points + stock -----------------------------------
    // Give each distributor its own warehouse seeded with stock for the first
    // few products, so the Dealer→Distributor ordering path has stock to draw
    // from and the two-sided transfer is demonstrable out of the box.
    foreach ($distributors as $distributor) {
        $distWarehouse = StockPoint::query()
            ->where('owner_type', $distributor->getMorphClass())
            ->where('owner_id', $distributor->id)
            ->where('name', 'Distributor Warehouse')
            ->first();

        if ($distWarehouse === null) {
            $distWarehouse = new StockPoint(['name' => 'Distributor Warehouse', 'sort_order' => 1]);
            $distWarehouse->owner()->associate($distributor);
            $distWarehouse->save();
        }

        foreach (array_slice($allProducts, 0, 6) as $idx => $product) {
            $exists = Stock::query()
                ->where('stockable_type', $distributor->getMorphClass())
                ->where('stockable_id', $distributor->id)
                ->where('product_id', $product->id)
                ->where('stock_point_id', $distWarehouse->id)
                ->exists();
            if ($exists) {
                continue;
            }
            $stock = new Stock([
                'product_id' => $product->id,
                'stock_point_id' => $distWarehouse->id,
                'quantity' => 150 + ($idx * 10),
                'low_stock_threshold' => 20,
            ]);
            $stock->owner()->associate($distributor);
            $stock->save();
        }
    }

    // ---- Sample orders (Distributor buying from Company) --------------------
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

        $firstDistributor = $distributors[0];
        Payment::query()->create([
            'buyer_type' => $firstDistributor->getMorphClass(),
            'buyer_id' => $firstDistributor->id,
            'amount' => 25000.00,
            'method' => 'bank_transfer',
            'reference' => 'NEFT-SEED-0001',
            'paid_at' => now()->subDays(10)->toDateString(),
        ]);
        Payment::query()->create([
            'buyer_type' => $firstDistributor->getMorphClass(),
            'buyer_id' => $firstDistributor->id,
            'amount' => 12000.00,
            'method' => 'upi',
            'reference' => 'UPI-SEED-0002',
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
    fwrite(STDOUT, '  portal users  = '.User::query()->whereNotNull('distributor_id')->orWhereNotNull('dealer_id')->count().PHP_EOL);
    fwrite(STDOUT, '  orders        = '.Order::query()->count().PHP_EOL);
    fwrite(STDOUT, '  order_lines   = '.OrderLine::query()->count().PHP_EOL);
    fwrite(STDOUT, '  payments      = '.Payment::query()->count().PHP_EOL);
});

fwrite(STDOUT, PHP_EOL.'Done.'.PHP_EOL);
