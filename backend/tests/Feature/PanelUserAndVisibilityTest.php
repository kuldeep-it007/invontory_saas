<?php

declare(strict_types=1);

use App\Auth\Roles;
use App\Mail\AccountCreatedMail;
use App\Models\Company;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OrderListService;
use App\Services\TenantUserService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| Distributor/Dealer panel login users + own-data visibility
|--------------------------------------------------------------------------
|
| Feature: a Distributor or Dealer gets login users (TenantUserService) that
| carry the matching role and a party link (distributor_id/dealer_id). The
| panel then confines each user to its OWN party details and the orders it
| placed (as buyer) / received (as seller), derived from the user's link — never
| from request params.
|
| DB-GATED: provisions a real per-tenant MySQL database (stancl tenancy) and
| exercises the services directly inside $tenant->run(); skips gracefully when
| MySQL is unreachable, mirroring the sibling Feature suites.
*/

beforeEach(function () {
    skip_without_mysql('central');
    Mail::fake();
});

/**
 * Provision + migrate an isolated tenant and register it for teardown.
 *
 * @param  array<int, Tenant>  $registry
 */
function panelProvisionTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'pnl'.$suffix,
        'subdomain' => 'pnl-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'pnl-'.$suffix.'.example.test']);
    $registry[] = $tenant;

    $tenant->run(function () {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
            '--force' => true,
        ]);
    });

    return $tenant;
}

/**
 * @param  array<int, Tenant>  $registry
 */
function panelTeardownTenants(array $registry): void
{
    tenancy()->end();

    foreach ($registry as $t) {
        try {
            $t->database()->getTenantDatabaseManager()->deleteDatabase($t);
        } catch (Throwable $e) {
            // best-effort
        }

        try {
            $t->domains()->delete();
            $t->delete();
        } catch (Throwable $e) {
            // best-effort
        }
    }
}

/**
 * Seed one order with the given polymorphic buyer and seller, returning its id.
 */
function panelSeedOrder(Model $buyer, Model $seller): int
{
    $order = Order::create([
        'buyer_type' => $buyer->getMorphClass(),
        'buyer_id' => (int) $buyer->getKey(),
        'seller_type' => $seller->getMorphClass(),
        'seller_id' => (int) $seller->getKey(),
        'status' => 'Placed',
        'order_type' => Order::TYPE_STANDARD,
        'total' => '100.00',
    ]);

    return (int) $order->id;
}

it('creates a distributor login user with the Distributor role and party link, and confines its panel to its own orders', function () {
    $registry = [];

    try {
        $tenant = panelProvisionTenant($registry);

        $tenant->run(function () {
            $company = Company::create(['name' => 'Panel Co']);
            $distA = Distributor::create(['company_id' => $company->id, 'name' => 'Dist A']);
            $distB = Distributor::create(['company_id' => $company->id, 'name' => 'Dist B']);
            $dealerA = Dealer::create(['distributor_id' => $distA->id, 'name' => 'Dealer A1']);

            // --- user creation (TenantUserService) ------------------------
            $service = app(TenantUserService::class);

            $userA = $service->createForDistributor($distA, [
                'name' => 'Dist A Owner',
                'email' => 'owner@dist-a.test',
                'password' => 'Str0ng-Passw0rd!',
            ]);

            expect($userA->distributor_id)->toBe((int) $distA->id);
            expect($userA->dealer_id)->toBeNull();
            expect($userA->hasRole(Roles::DISTRIBUTOR))->toBeTrue();

            // The buyer binding the report engine + panel rely on resolves to
            // this distributor.
            expect($userA->reportBuyerBinding())
                ->toBe([(new Distributor)->getMorphClass(), (int) $distA->id]);

            // A welcome mail was queued (R27.1).
            Mail::assertQueued(AccountCreatedMail::class);

            // A dealer user is linked + roled correctly too.
            $dealerUser = $service->createForDealer($dealerA, [
                'name' => 'Dealer A1 Owner',
                'email' => 'owner@dealer-a1.test',
                'password' => 'Str0ng-Passw0rd!',
            ]);
            expect($dealerUser->dealer_id)->toBe((int) $dealerA->id);
            expect($dealerUser->hasRole(Roles::DEALER))->toBeTrue();
            expect($dealerUser->reportBuyerBinding())
                ->toBe([(new Dealer)->getMorphClass(), (int) $dealerA->id]);

            // --- panel visibility (OrderListService scoping) --------------
            // Orders: Dist A placed one against the company; Dist B placed one;
            // Dealer A1 placed one against Dist A (so Dist A "received" it).
            $orderADistPlaced = panelSeedOrder($distA, $company);
            $orderBDistPlaced = panelSeedOrder($distB, $company);
            $orderDealerPlaced = panelSeedOrder($dealerA, $distA);

            $orders = app(OrderListService::class);

            // Dist A placed == only its own buyer order (NOT Dist B's).
            $placedByA = collect($orders->placedBy('distributor', (int) $distA->id)->items())
                ->map(fn (Order $o) => (int) $o->id)->all();
            expect($placedByA)->toBe([$orderADistPlaced]);

            // Dist A received == its dealers' orders against it (the dealer's).
            $receivedByA = collect($orders->soldBy('distributor', (int) $distA->id)->items())
                ->map(fn (Order $o) => (int) $o->id)->all();
            expect($receivedByA)->toBe([$orderDealerPlaced]);

            // Dealer A1 placed == only its own order.
            $placedByDealer = collect($orders->placedBy('dealer', (int) $dealerA->id)->items())
                ->map(fn (Order $o) => (int) $o->id)->all();
            expect($placedByDealer)->toBe([$orderDealerPlaced]);

            // A company-side user carries NO party binding (no panel scope).
            $companyUser = User::create([
                'name' => 'Company Admin',
                'email' => 'admin@panel-co.test',
                'password' => 'Str0ng-Passw0rd!',
            ]);
            expect($companyUser->party())->toBeNull();
            expect($companyUser->reportBuyerBinding())->toBeNull();

            // Unused var guard.
            expect($orderBDistPlaced)->toBeGreaterThan(0);
        });
    } finally {
        panelTeardownTenants($registry);
    }
});
