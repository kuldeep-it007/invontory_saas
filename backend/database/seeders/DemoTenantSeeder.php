<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Dealer;
use App\Models\Discount;
use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Price;
use App\Models\Product;
use App\Models\Prospect;
use App\Models\Stock;
use App\Models\StockPoint;
use App\Models\User;
use App\Services\CartService;
use App\Services\CreditEnforcementService;
use App\Services\DealerService;
use App\Services\DistributorService;
use App\Services\LowStockService;
use App\Services\OrderConfirmationService;
use App\Services\OrderReturnService;
use App\Services\PaymentService;
use App\Services\ProspectService;
use App\Services\Stock\StockMovement;
use App\Services\StockMovementService;
use App\Services\TenantUserService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Demo data for ONE tenant database (runs inside tenant context).
 *
 * Everything is created through the same services the API uses, so ledgers,
 * invoices, reservations and audit rows are consistent and the data behaves
 * like real usage. Profiles:
 *
 *  - `rich`     a full trading network: users in every role, a catalogue with every
 *               tax slab and price level, stock (healthy, low, zero), every discount
 *               scope, and orders in every lifecycle state (placed, confirmed,
 *               dispatched, delivered, closed, cancelled, rejected, chained, advance,
 *               backordered, returned), payments, overdue invoices, credit limits,
 *               prospects and an open cart.
 *  - `boundary` a Starter-sized tenant filled to its quotas (5 distributors).
 *  - `empty`    only the Company and its admin (a brand-new tenant).
 *
 * A scenario that fails is recorded in {@see self::$problems} and the rest continues.
 */
final class DemoTenantSeeder
{
    /** @var list<string> */
    public array $problems = [];

    /** @var array<string, int> */
    public array $summary = [];

    /** @var array<string, Product> */
    private array $products = [];

    /** @var array<string, Distributor> */
    private array $distributors = [];

    /** @var array<string, Dealer> */
    private array $dealers = [];

    private Company $company;

    private int $adminId;

    public function __construct(private readonly string $password = 'password123') {}

    public function run(string $profile): void
    {
        auth()->shouldUse('web');
        $this->company = Company::query()->firstOrFail();
        $this->adminId = (int) User::query()->orderBy('id')->value('id');

        if ($profile === 'empty') {
            return;
        }
        if ($profile === 'boundary') {
            $this->boundary();

            return;
        }

        $this->scenario('company users (staff, read-only)', fn () => $this->companyUsers());
        $this->scenario('catalogue', fn () => $this->catalogue());
        $this->scenario('network (distributors, dealers, users, terms)', fn () => $this->network());
        $this->scenario('stock', fn () => $this->stock());
        $this->scenario('discounts', fn () => $this->discounts());
        $this->scenario('orders and payments', fn () => $this->orders());
        $this->scenario('prospects', fn () => $this->prospects());
        $this->scenario('open cart', fn () => $this->openCart());
        $this->summarise();
    }

    private function scenario(string $name, callable $fn): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            $this->problems[] = sprintf('%s: %s (%s)', $name, $e->getMessage(), class_basename($e));
        }
    }

    /** @param  callable(): mixed  $fn */
    private function attempt(string $name, callable $fn): mixed
    {
        try {
            return $fn();
        } catch (Throwable $e) {
            $this->problems[] = sprintf('%s: %s (%s)', $name, $e->getMessage(), class_basename($e));

            return null;
        }
    }

    // ── users ────────────────────────────────────────────────────────────────
    private function companyUsers(): void
    {
        foreach ([['Sam Staff', 'staff', 'Company_Staff'], ['Rita Reader', 'readonly', 'Read-only']] as [$name, $local, $role]) {
            $email = $local.'@'.$this->domainOfAdmin();
            if (User::query()->where('email', $email)->exists()) {
                continue;
            }
            $user = User::create(['name' => $name, 'email' => $email, 'password' => $this->password]);
            $user->assignRole($role);
        }
    }

    private function domainOfAdmin(): string
    {
        $admin = (string) User::query()->orderBy('id')->value('email');

        return str_contains($admin, '@') ? substr($admin, strpos($admin, '@') + 1) : 'example.test';
    }

    // ── catalogue ────────────────────────────────────────────────────────────
    private function catalogue(): void
    {
        $tree = [
            ['Tiles', 'TIL', '6907', [
                ['Vitrified', 'VIT', 'gst_18', [['Ivory', 'IVO', 850.00], ['Grey', 'GRY', 920.00]]],
                ['Ceramic', 'CER', 'gst_18', [['Sand', 'SND', 540.00]]],
                ['Outdoor', 'OUT', 'gst_28', [['Slate', 'SLT', 1250.00]]],
            ]],
            ['Sanitaryware', 'SAN', '6910', [
                ['Basins', 'BAS', 'gst_18', [['White', 'WHT', 3400.00]]],
                ['Taps', 'TAP', 'gst_12', [['Chrome', 'CHR', 1800.00], ['Matte Black', 'MBK', 2100.00]]],
            ]],
            ['Accessories', 'ACC', '3926', [
                ['Grout', 'GRT', 'gst_5', [['Standard', 'STD', 260.00]]],
            ]],
        ];
        $now = now();
        foreach ($tree as [$catName, $catCode, $hsn, $collections]) {
            $categoryId = DB::table('categories')->insertGetId(['name' => $catName, 'code' => $catCode, 'active' => true, 'owner_key' => 0, 'owner_distributor_id' => null, 'created_at' => $now, 'updated_at' => $now]);
            foreach ($collections as [$colName, $colCode, $tax, $colours]) {
                $collectionId = DB::table('collections')->insertGetId(['category_id' => $categoryId, 'name' => $colName, 'code' => $catCode.'-'.$colCode, 'active' => true, 'owner_key' => 0, 'owner_distributor_id' => null, 'created_at' => $now, 'updated_at' => $now]);
                foreach ($colours as [$clrName, $clrCode, $price]) {
                    $colourId = DB::table('colours')->insertGetId(['collection_id' => $collectionId, 'name' => $clrName, 'code' => $catCode.'-'.$colCode.'-'.$clrCode, 'active' => true, 'owner_key' => 0, 'owner_distributor_id' => null, 'created_at' => $now, 'updated_at' => $now]);
                    $sku = $catCode.'-'.$colCode.'-'.$clrCode;
                    $this->products[$sku] = $this->product($sku, $catName.' '.$colName.' '.$clrName, $hsn, $tax, $collectionId, $colourId, $price);
                }
            }
        }
        // A discontinued line: hidden in the catalogue browse, still has a product row.
        $categoryId = (int) DB::table('categories')->where('code', 'TIL')->value('id');
        $collectionId = DB::table('collections')->insertGetId(['category_id' => $categoryId, 'name' => 'Discontinued', 'code' => 'TIL-DSC', 'active' => false, 'owner_key' => 0, 'owner_distributor_id' => null, 'created_at' => $now, 'updated_at' => $now]);
        $colourId = DB::table('colours')->insertGetId(['collection_id' => $collectionId, 'name' => 'Old Beige', 'code' => 'TIL-DSC-OBG', 'active' => false, 'owner_key' => 0, 'owner_distributor_id' => null, 'created_at' => $now, 'updated_at' => $now]);
        $this->products['TIL-DSC-OBG'] = $this->product('TIL-DSC-OBG', 'Tiles Discontinued Old Beige', '6907', 'gst_18', $collectionId, $colourId, 300.00);

        // Collection-level price with a markup (resolved when a product has no price of its own).
        $tapCollection = (int) DB::table('collections')->where('code', 'SAN-TAP')->value('id');
        Price::query()->updateOrCreate(['level' => Price::LEVEL_COLLECTION, 'ref_id' => $tapCollection], ['amount' => 1500.00, 'markup_type' => Price::MARKUP_TYPE_MARKUP, 'markup_value' => 10]);
    }

    private function product(string $sku, string $name, string $hsn, string $tax, int $collectionId, int $colourId, float $price, ?int $ownerDistributorId = null): Product
    {
        $product = new Product(['sku' => $sku, 'hsn_code' => $hsn, 'name' => $name, 'collection_id' => $collectionId, 'colour_id' => $colourId, 'tax_type' => $tax, 'uom' => 'box', 'price' => $price]);
        $product->owner_distributor_id = $ownerDistributorId;
        $product->save();
        Price::query()->updateOrCreate(['level' => Price::LEVEL_PRODUCT, 'ref_id' => $product->id], ['amount' => $price]);

        return $product;
    }

    // ── distributors, dealers, users, payment terms ─────────────────────────
    private function network(): void
    {
        $distributors = [
            'north' => ['North Traders', 'Nina North', '27AAAAA0000A1Z5', ['credit_limit' => '200000', 'overdue_balance_limit' => '50000', 'overdue_hold_days' => 30, 'payment_type' => 'credit']],
            'south' => ['South Supplies', 'Sunil South', '29BBBBB1111B1Z6', ['credit_limit' => '100000', 'overdue_balance_limit' => '10000', 'overdue_hold_days' => 15, 'payment_type' => 'credit']],
            'east' => ['East Wholesale', 'Esha East', '19CCCCC2222C1Z7', ['payment_type' => 'cash']],
            'west' => ['West Agencies', 'Wasim West', '24DDDDD3333D1Z8', ['credit_limit' => '5000', 'overdue_balance_limit' => '1000', 'overdue_hold_days' => 7, 'payment_type' => 'advance']],
        ];
        foreach ($distributors as $key => [$name, $contact, $gstin, $terms]) {
            $this->distributors[$key] = app(DistributorService::class)->create([
                'name' => $name, 'contact_name' => $contact, 'contact_email' => $key.'@parties.example.test', 'contact_phone' => '+91 90000 0000'.count($this->distributors),
                'gstin' => $gstin, 'address' => ucfirst($key).' Industrial Estate', 'payment_terms' => $terms,
            ]);
            app(TenantUserService::class)->createForDistributor($this->distributors[$key], ['name' => $contact, 'email' => 'distributor.'.$key.'@'.$this->domainOfAdmin(), 'password' => $this->password]);
        }
        $dealers = [
            'north_shop' => ['north', 'North Tile Shop', 'Dev Dealer', ['credit_limit' => '50000', 'overdue_hold_days' => 30, 'payment_type' => 'credit']],
            'north_mart' => ['north', 'North Hardware Mart', 'Maya Mart', ['credit_limit' => '9000', 'payment_type' => 'credit']],
            'south_store' => ['south', 'South Home Store', 'Sara Store', ['payment_type' => 'cash']],
            'west_depot' => ['west', 'West Depot', 'Will Depot', ['credit_limit' => '2000', 'overdue_balance_limit' => '500', 'overdue_hold_days' => 7]],
        ];
        foreach ($dealers as $key => [$parent, $name, $contact, $terms]) {
            $this->dealers[$key] = app(DealerService::class)->create($this->distributors[$parent], [
                'name' => $name, 'contact_name' => $contact, 'contact_email' => $key.'@parties.example.test', 'gstin' => null, 'address' => $name.' Road', 'payment_terms' => $terms,
            ]);
            app(TenantUserService::class)->createForDealer($this->dealers[$key], ['name' => $contact, 'email' => 'dealer.'.$key.'@'.$this->domainOfAdmin(), 'password' => $this->password]);
        }
        // Distributor-owned catalogue ("own products"): only North and its network see these.
        $north = $this->distributors['north'];
        $now = now();
        $categoryId = DB::table('categories')->insertGetId(['name' => 'North Specials', 'code' => 'NSP', 'active' => true, 'owner_key' => $north->id, 'owner_distributor_id' => $north->id, 'created_at' => $now, 'updated_at' => $now]);
        $collectionId = DB::table('collections')->insertGetId(['category_id' => $categoryId, 'name' => 'Local Stone', 'code' => 'NSP-LST', 'active' => true, 'owner_key' => $north->id, 'owner_distributor_id' => $north->id, 'created_at' => $now, 'updated_at' => $now]);
        $colourId = DB::table('colours')->insertGetId(['collection_id' => $collectionId, 'name' => 'Granite', 'code' => 'NSP-LST-GRN', 'active' => true, 'owner_key' => $north->id, 'owner_distributor_id' => $north->id, 'created_at' => $now, 'updated_at' => $now]);
        $this->products['NSP-LST-GRN'] = $this->product('NSP-LST-GRN', 'North Specials Local Stone Granite', '6802', 'gst_18', $collectionId, $colourId, 700.00, $north->id);
    }

    // ── stock ────────────────────────────────────────────────────────────────
    private function stock(): void
    {
        $main = $this->point($this->company, 'Main Warehouse');
        $overflow = $this->point($this->company, 'Overflow Yard');
        $levels = [
            'TIL-VIT-IVO' => 400, 'TIL-VIT-GRY' => 250, 'TIL-CER-SND' => 600, 'TIL-OUT-SLT' => 120,
            'SAN-BAS-WHT' => 60, 'SAN-TAP-CHR' => 8,      // 8 is below its threshold of 10: low-stock alert
            'SAN-TAP-MBK' => 3,                              // less than a typical order: backorder scenario
            'ACC-GRT-STD' => 1000, 'TIL-DSC-OBG' => 15,
        ];
        foreach ($levels as $sku => $qty) {
            if ($qty > 0) {
                $this->move($this->company, $sku, $qty >= 200 ? $overflow : $main, $qty, 'opening');
            }
        }
        // Part of the grout sits in a second point to exercise multi-point allocation.
        $this->move($this->company, 'ACC-GRT-STD', $main, 300, 'received', 'Second consignment');
        $this->threshold($this->company, 'SAN-TAP-CHR', '10');
        $this->threshold($this->company, 'SAN-BAS-WHT', '20');
        $this->threshold($this->company, 'TIL-OUT-SLT', '50');

        // Distributors hold some of the range themselves; East holds nothing.
        $north = $this->point($this->distributors['north'], 'North Depot');
        $south = $this->point($this->distributors['south'], 'South Depot');
        $this->move($this->distributors['north'], 'TIL-VIT-IVO', $north, 40, 'opening');
        $this->move($this->distributors['north'], 'TIL-CER-SND', $north, 6, 'opening');   // small: forces a chained order
        $this->move($this->distributors['north'], 'NSP-LST-GRN', $north, 90, 'opening');
        $this->move($this->distributors['south'], 'TIL-VIT-IVO', $south, 25, 'opening');
        $this->move($this->distributors['south'], 'SAN-BAS-WHT', $south, 12, 'opening');
        $this->threshold($this->distributors['north'], 'TIL-CER-SND', '10');
        // A dealer with its own stock (dealer stock tracking).
        $shop = $this->point($this->dealers['north_shop'], 'Shop Floor');
        $this->move($this->dealers['north_shop'], 'TIL-VIT-GRY', $shop, 14, 'opening');
    }

    private function point(Model $owner, string $name): StockPoint
    {
        $point = new StockPoint(['name' => $name, 'sort_order' => 0]);
        $point->owner()->associate($owner);
        $point->save();

        return $point;
    }

    private function move(Model $owner, string $sku, StockPoint $point, int $qty, string $type, ?string $reason = null): void
    {
        app(StockMovementService::class)->apply(
            [StockMovement::forOwner($owner, $this->products[$sku]->id, $point->id, (string) $qty, $type, $reason ?? 'Demo data')],
            ['actor_id' => $this->adminId],
        );
    }

    private function threshold(Model $owner, string $sku, string $threshold): void
    {
        $stock = Stock::query()->where('stockable_type', $owner->getMorphClass())->where('stockable_id', $owner->getKey())->where('product_id', $this->products[$sku]->id)->firstOrFail();
        app(LowStockService::class)->setThreshold($stock, $threshold);
    }

    // ── discounts: every scope and value type, plus inactive ones ───────────
    private function discounts(): void
    {
        $common = ['type' => Discount::TYPE_CUSTOMER, 'is_active' => true];
        $rows = [
            // general (everyone)
            $common + ['value_type' => Discount::VALUE_PERCENT, 'percentage' => 2, 'amount' => null],
            // a product
            $common + ['value_type' => Discount::VALUE_FIXED, 'percentage' => 0, 'amount' => 50, 'product_id' => $this->products['TIL-VIT-IVO']->id],
            // a category-wide percentage on one collection
            $common + ['value_type' => Discount::VALUE_PERCENT, 'percentage' => 5, 'amount' => null, 'collection_id' => (int) DB::table('collections')->where('code', 'TIL-CER')->value('id')],
            // one distributor, one dealer
            $common + ['value_type' => Discount::VALUE_PERCENT, 'percentage' => 4, 'amount' => null, 'buyer_type' => $this->distributors['south']->getMorphClass(), 'buyer_id' => $this->distributors['south']->id],
            $common + ['value_type' => Discount::VALUE_FIXED, 'percentage' => 0, 'amount' => 75, 'product_id' => $this->products['SAN-BAS-WHT']->id, 'buyer_type' => $this->dealers['north_shop']->getMorphClass(), 'buyer_id' => $this->dealers['north_shop']->id],
            // online-type and inactive
            ['type' => Discount::TYPE_ONLINE, 'is_active' => true, 'value_type' => Discount::VALUE_PERCENT, 'percentage' => 1, 'amount' => null],
            ['type' => Discount::TYPE_CUSTOMER, 'is_active' => false, 'value_type' => Discount::VALUE_PERCENT, 'percentage' => 50, 'amount' => null, 'product_id' => $this->products['ACC-GRT-STD']->id],
        ];
        foreach ($rows as $row) {
            Discount::create($row);
        }
    }

    // ── orders in every state, payments, overdue, returns ───────────────────
    private function orders(): void
    {
        $confirm = app(OrderConfirmationService::class);
        $d = $this->dealers;
        $n = $this->distributors;

        // 1. Placed, waiting for the seller.
        $placed = $this->place($d['north_shop'], ['TIL-VIT-IVO' => 10, 'ACC-GRT-STD' => 5]);
        $this->age($placed, 1);

        // 2. Confirmed (stock moved, invoice created).
        $confirmed = $this->place($d['north_shop'], ['TIL-VIT-IVO' => 8]);
        $confirmed = $this->confirmAll($confirmed);
        $this->age($confirmed, 4);

        // 3. Dispatched.
        $dispatched = $this->confirmAll($this->place($d['south_store'], ['SAN-BAS-WHT' => 3]));
        $this->advance($dispatched, ['dispatch']);
        $this->age($dispatched, 7);

        // 4. Delivered, fully paid.
        $delivered = $this->confirmAll($this->place($d['north_mart'], ['TIL-VIT-IVO' => 4]));
        $this->advance($delivered, ['dispatch', 'deliver']);
        $this->age($delivered, 12);
        $this->payInFull($delivered, 'upi');

        // 5. Closed, with a partial return processed against it.
        $closed = $this->confirmAll($this->place($d['north_shop'], ['TIL-VIT-IVO' => 6, 'ACC-GRT-STD' => 10]));
        $this->advance($closed, ['dispatch', 'deliver']);
        $this->age($closed, 25);
        $this->payInFull($closed, 'bank_transfer');
        $this->attempt('return', fn () => app(OrderReturnService::class)->process($closed[0]->fresh(), [['product_id' => $this->products['ACC-GRT-STD']->id, 'quantity' => 2]], ['actor_id' => $this->adminId]));
        $this->advance($closed, ['close']);

        // 6. Cancelled by the buyer, and 7. rejected by the seller.
        $cancelled = $this->place($d['south_store'], ['TIL-VIT-IVO' => 2]);
        $this->each($cancelled, 'cancel');
        $this->age($cancelled, 9);
        $rejected = $this->place($d['north_mart'], ['TIL-CER-SND' => 2]);
        $this->each($rejected, 'reject');
        $this->age($rejected, 6);

        // 8. Distributor buys from the Company (placed and confirmed).
        $po = $this->place($n['south'], ['TIL-OUT-SLT' => 20, 'TIL-VIT-GRY' => 30]);
        $this->age($po, 3);
        $poConfirmed = $this->confirmAll($this->place($n['east'], ['TIL-VIT-GRY' => 40, 'TIL-CER-SND' => 60]));
        $this->advance($poConfirmed, ['dispatch']);
        $this->age($poConfirmed, 20);
        $this->pay($poConfirmed, 0.5, 'cheque');                                  // partially paid

        // 9. Chain order: the dealer wants more than North holds, so the shortfall is chained upstream.
        $chain = $this->place($d['north_shop'], ['TIL-CER-SND' => 15]);
        $this->age($chain, 2);

        // 10. Backorder: the zero-stock product.
        $this->attempt('backorder', fn () => $this->age($this->place($n['east'], ['SAN-TAP-MBK' => 5]), 1));

        // 11. Advance and blanket orders (feature-gated).
        $this->attempt('advance order', fn () => $this->age($this->place($n['north'], ['TIL-OUT-SLT' => 10], Order::TYPE_ADVANCE), 2));
        $this->attempt('blanket order', fn () => $this->age($this->place($n['south'], ['ACC-GRT-STD' => 100], Order::TYPE_BLANKET), 2));

        // 12. Overdue: an old unpaid invoice against a tight overdue limit (West is blocked from ordering).
        $overdue = $this->confirmAll($this->place($n['west'], ['TIL-VIT-GRY' => 4]));
        $this->advance($overdue, ['dispatch', 'deliver']);
        $this->age($overdue, 45);
        foreach ([$n['west'], $d['west_depot']] as $buyer) {
            $this->attempt('flag overdue', fn () => app(CreditEnforcementService::class)->flagOverdueInvoices($buyer));
        }
        // And an overdue invoice that stays within its limit (a warning, not a block).
        $lateButOk = $this->confirmAll($this->place($n['north'], ['TIL-VIT-IVO' => 3]));
        $this->age($lateButOk, 40);
        $this->attempt('flag overdue north', fn () => app(CreditEnforcementService::class)->flagOverdueInvoices($n['north']));

        // 13. An advance payment with no allocation (credit on account), and a spread of older history for reports.
        $this->attempt('advance payment', fn () => app(PaymentService::class)->record($d['south_store'], ['amount' => '2500.00', 'method' => 'cash', 'reference' => 'ADV-001', 'paid_at' => now()->subDays(3), 'recorded_by' => $this->adminId]));
        for ($i = 1; $i <= 6; $i++) {
            $this->attempt('history order '.$i, function () use ($i, $d): void {
                $history = $this->confirmAll($this->place($i % 2 ? $d['north_shop'] : $d['south_store'], ['TIL-CER-SND' => 2 + $i, 'ACC-GRT-STD' => 3 * $i]));
                $this->advance($history, ['dispatch', 'deliver']);
                $this->age($history, 30 + $i * 6);
                $this->payInFull($history, $i % 2 ? 'upi' : 'card');
            });
        }
        unset($confirm);
    }

    /**
     * @param  array<string, int>  $items  sku => quantity
     * @return list<Order>
     */
    private function place(Model $buyer, array $items, string $type = 'standard'): array
    {
        $cart = app(CartService::class);
        foreach ($items as $sku => $qty) {
            $cart->addItem($buyer, $this->products[$sku]->id, $qty);
        }

        $orders = array_values($cart->submitAll($buyer, $type));
        foreach ($orders as $order) {
            foreach (Order::query()->where('parent_order_id', $order->id)->get() as $upstream) {
                $orders[] = $upstream;
            }
        }

        return $orders;
    }

    /**
     * @param  list<Order>  $orders
     * @return list<Order>
     */
    private function confirmAll(array $orders): array
    {
        $confirm = app(OrderConfirmationService::class);
        $done = [];
        // Upstream (chained purchase) orders must be confirmed before the order that depends on them.
        usort($orders, fn (Order $a, Order $b) => ($b->parent_order_id !== null) <=> ($a->parent_order_id !== null));
        foreach ($orders as $order) {
            $this->attempt('confirm '.$order->order_number, function () use ($confirm, $order, &$done): void {
                $done[] = $confirm->confirm($order->fresh());
            });
        }

        return $done ?: $orders;
    }

    /**
     * Apply several events to each order in turn. Upstream (chained purchase) orders come first
     * because the order that depends on them cannot be dispatched until they are delivered.
     *
     * @param  list<Order>  $orders
     * @param  list<string>  $events
     */
    private function advance(array $orders, array $events): void
    {
        usort($orders, fn (Order $a, Order $b) => ($b->parent_order_id !== null) <=> ($a->parent_order_id !== null));
        foreach ($orders as $order) {
            foreach ($events as $event) {
                $this->attempt($event.' '.$order->order_number, fn () => $order->fresh()->transition($event));
            }
        }
    }

    /** @param  list<Order>  $orders */
    private function each(array $orders, string $event): void
    {
        foreach ($orders as $order) {
            $this->attempt($event.' '.$order->order_number, fn () => $order->fresh()->transition($event));
        }
    }

    /** @param  list<Order>  $orders */
    private function age(array $orders, int $days): void
    {
        $at = now()->subDays($days);
        foreach ($orders as $order) {
            DB::table('orders')->where('id', $order->id)->update(['created_at' => $at, 'updated_at' => $at]);
            DB::table('invoices')->where('order_id', $order->id)->update(['created_at' => $at, 'updated_at' => $at]);
        }
    }

    /** @param  list<Order>  $orders */
    private function payInFull(array $orders, string $method): void
    {
        $this->pay($orders, 1.0, $method);
    }

    /** @param  list<Order>  $orders */
    private function pay(array $orders, float $fraction, string $method): void
    {
        foreach ($orders as $order) {
            $invoice = Invoice::query()->where('order_id', $order->id)->first();
            if ($invoice === null) {
                continue;
            }
            $amount = number_format((float) $invoice->amount * $fraction, 2, '.', '');
            $buyer = $order->buyer;
            $this->attempt('payment '.$order->order_number, fn () => app(PaymentService::class)->record($buyer, [
                'amount' => $amount, 'method' => $method, 'reference' => 'PAY-'.$order->order_number,
                'paid_at' => $order->created_at?->copy()->addDays(2) ?? now(),
                'recorded_by' => $this->adminId,
                'allocations' => [['invoice_id' => $invoice->id, 'amount' => $amount]],
            ]));
        }
    }

    // ── prospects ────────────────────────────────────────────────────────────
    private function prospects(): void
    {
        $service = app(ProspectService::class);
        $service->signUp(['name' => 'Fresh Dealer Co', 'contact_name' => 'Pat Pending', 'contact_email' => 'pending@prospects.example.test', 'contact_phone' => '+91 98000 11111', 'intended_buyer_type' => 'dealer', 'serving_distributor_id' => $this->distributors['north']->id]);
        $approved = $service->signUp(['name' => 'Approved Stores', 'contact_name' => 'Ana Approved', 'contact_email' => 'approved@prospects.example.test', 'intended_buyer_type' => 'dealer', 'serving_distributor_id' => $this->distributors['south']->id]);
        $this->attempt('approve prospect', fn () => $service->approve($approved));
        $rejected = $service->signUp(['name' => 'Rejected Retail', 'contact_name' => 'Rex Rejected', 'contact_email' => 'rejected@prospects.example.test', 'intended_buyer_type' => 'dealer', 'serving_distributor_id' => $this->distributors['north']->id]);
        $this->attempt('reject prospect', fn () => $service->reject($rejected));
        $service->signUp(['name' => 'Big New Distributor', 'contact_name' => 'Dina Distributor', 'contact_email' => 'newdist@prospects.example.test', 'intended_buyer_type' => 'distributor']);
    }

    private function openCart(): void
    {
        $cart = app(CartService::class);
        $cart->addItem($this->dealers['south_store'], $this->products['TIL-VIT-GRY']->id, 3);
        $cart->addItem($this->dealers['south_store'], $this->products['SAN-TAP-CHR']->id, 2);
    }

    // ── boundary tenant: filled to the Starter quotas ───────────────────────
    private function boundary(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $d = app(DistributorService::class)->create(['name' => "Quota Distributor {$i}", 'contact_email' => "q{$i}@parties.example.test"]);
            if ($i === 1) {
                for ($j = 1; $j <= 3; $j++) {
                    app(DealerService::class)->create($d, ['name' => "Quota Dealer {$j}"]);
                }
            }
        }
        $this->summary['distributors'] = Distributor::query()->count();
        $this->summary['dealers'] = Dealer::query()->count();
    }

    private function summarise(): void
    {
        $this->summary = [
            'users' => User::query()->count(),
            'distributors' => Distributor::query()->count(),
            'dealers' => Dealer::query()->count(),
            'products' => Product::query()->count(),
            'stock rows' => Stock::query()->count(),
            'discounts' => Discount::query()->count(),
            'orders' => Order::query()->count(),
            'invoices' => Invoice::query()->count(),
            'overdue invoices' => Invoice::query()->where('overdue', true)->count(),
            'payments' => (int) DB::table('payments')->count(),
            'prospects' => Prospect::query()->count(),
        ];
        foreach (OrderStatus::all() as $status) {
            $this->summary['orders '.$status] = Order::query()->where('status', $status)->count();
        }
    }
}
