<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Root seeder for a freshly provisioned tenant database.
 *
 * Runs inside tenant context (invoked by the `TenantCreated` seed job and by
 * `tenancy()->run(...)` in tests). Seeds the baseline RBAC roles/abilities
 * every tenant needs (R8.1). Later milestones extend this with the single
 * Company and the initial Company_Admin user (provisioning).
 */
class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);
    }
}
