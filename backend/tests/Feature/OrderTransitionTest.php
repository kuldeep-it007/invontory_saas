<?php

declare(strict_types=1);

use App\Exceptions\IllegalOrderTransitionException;
use App\Models\Company;
use App\Models\Distributor;
use App\Models\Order;
use App\Models\Tenant;
use App\Services\OrderStateMachine;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Task 9.3 — Order transition against a real tenant database (R21.1, R21.2, R21.4)
|--------------------------------------------------------------------------
|
| Exercises Order::transition() (which delegates to OrderStateMachine) against a
| provisioned tenant database so the status change is actually persisted and an
| illegal move is proven to leave the stored order unchanged (R21.4). The thin
| transition controller/endpoint is a direct pass-through to this same path.
|
| DB-GATED: needs MySQL + CREATE DATABASE for stancl tenancy; skips gracefully
| when MySQL is unreachable, mirroring the stock suites.
|
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Provision + migrate an isolated tenant for order-transition tests.
 *
 * @param  array<int, Tenant>  $registry
 */
function provisionOrderTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'ord'.$suffix,
        'subdomain' => 'ord-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'ord-'.$suffix.'.example.test']);
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
 * Seed a Placed order (Distributor buying from the Company) and return it.
 */
function seedPlacedOrder(): Order
{
    $company = Company::create(['name' => 'Acme']);
    $distributor = Distributor::create([
        'company_id' => $company->getKey(),
        'name' => 'Dist One',
    ]);

    return Order::create([
        'buyer_type' => $distributor->getMorphClass(),
        'buyer_id' => $distributor->getKey(),
        'seller_type' => $company->getMorphClass(),
        'seller_id' => $company->getKey(),
        'status' => OrderStateMachine::PLACED,
        'order_type' => 'standard',
        'total' => '100.00',
    ]);
}

/**
 * @param  array<int, Tenant>  $registry
 */
function teardownOrderTenants(array $registry): void
{
    foreach ($registry as $t) {
        try {
            $t->database()->getTenantDatabaseManager()->deleteDatabase($t);
        } catch (Throwable $e) {
            // best-effort
        }
        $t->domains()->delete();
        $t->delete();
    }
}

it('applies a legal transition and persists the new status (R21.1)', function () {
    $registry = [];
    $tenant = provisionOrderTenant($registry);

    try {
        $tenant->run(function () {
            $order = seedPlacedOrder();

            $newStatus = $order->transition('confirm');

            expect($newStatus)->toBe('confirmed');
            expect($order->fresh()->status)->toBe('confirmed');
        });
    } finally {
        teardownOrderTenants($registry);
    }
});

it('permits the seller approve/reject/edit while Placed (R21.2)', function () {
    $registry = [];
    $tenant = provisionOrderTenant($registry);

    try {
        $tenant->run(function () {
            // approve → confirmed
            expect(seedPlacedOrder()->transition('approve'))->toBe('confirmed');
            // reject → cancelled
            expect(seedPlacedOrder()->transition('reject'))->toBe('cancelled');
            // edit → stays placed and persists as placed
            $edited = seedPlacedOrder();
            expect($edited->transition('edit'))->toBe('placed');
            expect($edited->fresh()->status)->toBe('placed');
        });
    } finally {
        teardownOrderTenants($registry);
    }
});

it('rejects an undefined transition with a 422 and leaves the order unchanged (R21.4)', function () {
    $registry = [];
    $tenant = provisionOrderTenant($registry);

    try {
        $tenant->run(function () {
            $order = seedPlacedOrder();
            // Jump straight to deliver from Placed is undefined.
            $threw = null;
            try {
                $order->transition('deliver');
            } catch (IllegalOrderTransitionException $e) {
                $threw = $e;
            }

            expect($threw)->not->toBeNull();
            expect($threw->getStatusCode())->toBe(422);

            // Order left completely unchanged in storage (R21.4).
            expect($order->fresh()->status)->toBe('placed');
        });
    } finally {
        teardownOrderTenants($registry);
    }
});
