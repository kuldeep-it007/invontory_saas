<?php

declare(strict_types=1);

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| demo:seed creates tenants whose data covers every business scenario.
| DB-GATED: needs MySQL + CREATE DATABASE for stancl tenancy.
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    skip_without_mysql('central');
    $this->prefix = 'demo'.strtolower(bin2hex(random_bytes(3))).'-';
});

afterEach(function () {
    foreach (Tenant::query()->where('subdomain', 'like', $this->prefix.'%')->get() as $tenant) {
        try {
            $tenant->database()->getTenantDatabaseManager()->deleteDatabase($tenant);
        } catch (Throwable) {
            // best-effort
        }
        $tenant->domains()->delete();
        $tenant->delete();
    }
});

it('seeds a rich tenant with every order state, chained orders, payments and overdue invoices', function () {
    $exit = Artisan::call('demo:seed', ['--prefix' => $this->prefix, '--only' => 'acme', '--domain' => 'example.test']);

    expect($exit)->toBe(0, Artisan::output());

    $tenant = Tenant::query()->where('subdomain', $this->prefix.'acme')->firstOrFail();
    $tenant->run(function () {
        foreach ([OrderStatus::PLACED, OrderStatus::CONFIRMED, OrderStatus::DISPATCHED, OrderStatus::DELIVERED, OrderStatus::CLOSED, OrderStatus::CANCELLED] as $status) {
            expect(Order::query()->where('status', $status)->count())->toBeGreaterThan(0, "no {$status} order was seeded");
        }
        expect(Order::query()->whereNotNull('parent_order_id')->count())->toBeGreaterThan(0)
            ->and(Order::query()->distinct()->pluck('order_type')->all())->toContain('standard', 'advance', 'blanket')
            ->and(Invoice::query()->where('overdue', true)->count())->toBeGreaterThan(0)
            ->and(DB::table('payment_allocations')->count())->toBeGreaterThan(0)
            ->and(DB::table('stock_ledger')->where('movement_type', 'like', 'return%')->count())->toBeGreaterThan(0)
            ->and(DB::table('stock')->whereNotNull('low_stock_threshold')->whereColumn('quantity', '<=', 'low_stock_threshold')->count())->toBeGreaterThan(0)
            ->and(DB::table('prospects')->distinct()->pluck('status')->all())->toContain('pending', 'approved', 'rejected');

        $roles = User::query()->with('roles')->get()->flatMap(fn (User $u) => $u->roles->pluck('name'))->unique()->all();
        expect($roles)->toContain('Company_Admin', 'Company_Staff', 'Read-only', 'Distributor', 'Dealer');
    });
});

it('seeds a Starter tenant filled to its quota and a lapsed tenant with an unpaid subscription', function () {
    expect(Artisan::call('demo:seed', ['--prefix' => $this->prefix, '--domain' => 'example.test', '--only' => 'starter']))->toBe(0, Artisan::output());
    expect(Artisan::call('demo:seed', ['--prefix' => $this->prefix, '--domain' => 'example.test', '--only' => 'lapsed']))->toBe(0, Artisan::output());

    Tenant::query()->where('subdomain', $this->prefix.'starter')->firstOrFail()->run(function () {
        expect(DB::table('distributors')->count())->toBe(5);
    });
    $lapsed = Tenant::query()->where('subdomain', $this->prefix.'lapsed')->firstOrFail();
    expect($lapsed->subscription->status ?? DB::connection('central')->table('subscriptions')->where('tenant_id', $lapsed->getTenantKey())->value('status'))->toBe('past_due');
});

it('leaves an existing demo tenant alone unless asked to recreate it, and validates its options', function () {
    $args = ['--prefix' => $this->prefix, '--only' => 'lapsed', '--domain' => 'example.test'];
    expect(Artisan::call('demo:seed', $args))->toBe(0);
    $before = Tenant::query()->where('subdomain', $this->prefix.'lapsed')->value('id');

    expect(Artisan::call('demo:seed', $args))->toBe(0)
        ->and(Tenant::query()->where('subdomain', $this->prefix.'lapsed')->value('id'))->toBe($before)
        ->and(Artisan::call('demo:seed', ['--only' => 'nope']))->toBe(1)
        ->and(Artisan::call('demo:seed', ['--password' => 'short']))->toBe(1)
        ->and(Artisan::call('demo:seed', ['--prefix' => 'Bad Prefix!']))->toBe(1);
});
