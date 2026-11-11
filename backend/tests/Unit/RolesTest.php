<?php

declare(strict_types=1);

use App\Auth\Roles;

/*
|--------------------------------------------------------------------------
| Task 1.7 — RBAC role/ability taxonomy (no DB required)
|--------------------------------------------------------------------------
|
| Pure assertions over the canonical role → ability map that the tenant role
| seeder consumes. These guard the invariants the spec depends on:
|   • exactly the six platform roles exist (R8.1);
|   • Read-only is granted only view abilities and no mutation (R8.4);
|   • every mutating role still carries the view abilities.
|
*/

it('defines exactly the six platform roles (R8.1)', function () {
    expect(Roles::all())->toEqual([
        'Platform_Admin',
        'Company_Admin',
        'Company_Staff',
        'Distributor',
        'Dealer',
        'Read-only',
    ]);
});

it('maps an ability set for every role', function () {
    $map = Roles::abilitiesByRole();

    foreach (Roles::all() as $role) {
        expect($map)->toHaveKey($role)
            ->and($map[$role])->toBeArray();
    }
});

it('grants Read-only only view abilities and denies every mutation (R8.4)', function () {
    $readOnly = Roles::abilitiesByRole()[Roles::READ_ONLY];

    // Read-only === the full view set, nothing else.
    expect($readOnly)->toEqualCanonicalizing(Roles::viewAbilities());

    // No mutation ability leaks into Read-only.
    foreach (Roles::mutationAbilities() as $mutation) {
        expect($readOnly)->not->toContain($mutation);
    }
});

it('grants Platform_Admin and Company_Admin the full ability catalogue', function () {
    $map = Roles::abilitiesByRole();

    expect($map[Roles::PLATFORM_ADMIN])->toEqualCanonicalizing(Roles::allAbilities())
        ->and($map[Roles::COMPANY_ADMIN])->toEqualCanonicalizing(Roles::allAbilities());
});

it('gives every mutating role the baseline view abilities', function () {
    $map = Roles::abilitiesByRole();

    foreach ([Roles::COMPANY_STAFF, Roles::DISTRIBUTOR, Roles::DEALER] as $role) {
        foreach (Roles::viewAbilities() as $view) {
            expect($map[$role])->toContain($view);
        }
    }
});

it('scopes mutation abilities per role (staff can record payments; dealer cannot)', function () {
    $map = Roles::abilitiesByRole();

    expect($map[Roles::COMPANY_STAFF])->toContain('payments.record')
        ->and($map[Roles::DEALER])->not->toContain('payments.record')
        ->and($map[Roles::DISTRIBUTOR])->toContain('dealers.manage')
        ->and($map[Roles::DEALER])->not->toContain('dealers.manage')
        ->and($map[Roles::DEALER])->toContain('orders.create');
});

it('lets a Distributor drive its own order lifecycle but denies a Dealer (R21)', function () {
    $map = Roles::abilitiesByRole();

    // A distributor sells to its dealers, so it may confirm/cancel the orders
    // placed against its own stock (the seller-ownership check confines this to
    // its own orders). A dealer is a buyer only and may do neither.
    expect($map[Roles::DISTRIBUTOR])->toContain('orders.confirm')
        ->and($map[Roles::DISTRIBUTOR])->toContain('orders.cancel')
        ->and($map[Roles::DEALER])->not->toContain('orders.confirm')
        ->and($map[Roles::DEALER])->not->toContain('orders.cancel');
});
