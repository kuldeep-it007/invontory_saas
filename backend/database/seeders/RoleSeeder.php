<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Auth\Roles;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the six platform roles and their abilities into the ACTIVE tenant
 * database (R8.1).
 *
 * spatie roles/permissions are stored per-tenant, so this runs inside tenant
 * context (either during provisioning or via `tenancy()->run(...)`). It is
 * idempotent: roles/permissions are created with `firstOrCreate` and ability
 * assignments are synced, so re-running makes no duplicate rows.
 *
 * Role → ability mapping comes from the single source of truth in
 * {@see Roles}. The Read-only role receives only view abilities, which denies
 * every mutation (R8.4).
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $guard = config('auth.defaults.guard', 'web');

        // Create every ability (permission) once, and keep the resolved
        // Permission MODELS keyed by name. We sync by object, not by name:
        // syncing by name makes spatie re-resolve each permission using the
        // request's ACTIVE guard, which is `sanctum` during an authenticated
        // provisioning request and causes a GuardDoesNotMatch against the
        // `web`-guarded roles. Passing the explicit `web` Permission objects
        // sidesteps that resolution entirely (R8.1, R8.2).
        $permissions = [];
        foreach (Roles::allAbilities() as $ability) {
            $permissions[$ability] = Permission::findOrCreate($ability, $guard);
        }

        // Create each role (explicit `web` guard) and sync its abilities as
        // resolved objects.
        foreach (Roles::abilitiesByRole() as $roleName => $abilities) {
            $role = Role::findOrCreate($roleName, $guard);
            $role->syncPermissions(array_map(
                static fn (string $ability) => $permissions[$ability],
                $abilities,
            ));
        }

        // Flush the spatie permission cache so freshly seeded roles/abilities
        // are visible to authorization checks immediately.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
