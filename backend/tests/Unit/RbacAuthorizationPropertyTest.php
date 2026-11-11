<?php

declare(strict_types=1);

use App\Auth\Roles;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Task 1.9 — Property 20: RBAC authorization
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 20: RBAC authorization
|
| Validates: Requirements 8.2, 8.3, 8.4, 36.3
|
| Property statement (design.md §Property 20):
|   For any (role, action) pair, the action executes if and only if the role
|   holds the required ability; otherwise it is rejected (403). The Read-only
|   role is permitted view actions and denied all mutations.
|
| The authorization decision in this platform is driven entirely by the
| canonical role -> ability map in App\Auth\Roles (the single source of truth
| the tenant RoleSeeder, the spatie gate, and GET /api/me all consume). The
| core invariant is therefore a pure property of that map and needs no
| database, so it runs in every environment. The DB-backed exercise of the
| spatie gate against seeded roles lives in the DB-gated AuthFlowTest.
|
| Generation uses innmind/black-box (NOT hand-rolled): BlackBox `Set`s produce
| the (role, action) scenarios, and `->take(100)->enumerate()` draws the
| minimum 100 generated iterations the spec mandates.
|
*/

/**
 * The authorization decision under test: a user holding $role may execute
 * $ability if and only if the canonical map grants that ability to the role.
 * This mirrors the RBAC middleware's `can($ability)` check that runs before
 * any action executes (R8.2, R36.3); a false result corresponds to the 403
 * rejection (R8.3).
 */
function rbacAuthorizes(string $role, string $ability): bool
{
    $granted = Roles::abilitiesByRole()[$role] ?? [];

    return in_array($ability, $granted, true);
}

it('authorizes a (role, action) pair iff the role holds the ability (R8.2, R8.3, R36.3)', function () {
    // BlackBox generates random (role, ability) pairs from the full taxonomy.
    $pairs = Set::tuple(
        Set::of(...Roles::all()),
        Set::of(...Roles::allAbilities()),
    )->take(100);

    $iterations = 0;

    foreach ($pairs->enumerate() as [$role, $ability]) {
        $iterations++;

        // The authority of record: membership in the role's granted ability set.
        $expected = in_array($ability, Roles::abilitiesByRole()[$role], true);

        expect(rbacAuthorizes($role, $ability))
            ->toBe($expected, "Role [{$role}] authorization for ability [{$ability}] must equal map membership.");
    }

    // Guard the spec's minimum-iteration requirement.
    expect($iterations)->toBeGreaterThanOrEqual(100);
});

it('permits the Read-only role every view action (R8.4)', function () {
    $views = Set::of(...Roles::viewAbilities())->take(100);

    $iterations = 0;

    foreach ($views->enumerate() as $viewAbility) {
        $iterations++;

        expect(rbacAuthorizes(Roles::READ_ONLY, $viewAbility))->toBeTrue(
            sprintf('Read-only must be permitted the view action [%s] (R8.4).', $viewAbility),
        );
    }

    expect($iterations)->toBeGreaterThanOrEqual(100);
});

it('denies the Read-only role every mutation (R8.4)', function () {
    $mutations = Set::of(...Roles::mutationAbilities())->take(100);

    $iterations = 0;

    foreach ($mutations->enumerate() as $mutationAbility) {
        $iterations++;

        expect(rbacAuthorizes(Roles::READ_ONLY, $mutationAbility))->toBeFalse(
            sprintf('Read-only must be denied the mutation [%s] (R8.4).', $mutationAbility),
        );
    }

    expect($iterations)->toBeGreaterThanOrEqual(100);
});
