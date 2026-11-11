<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\StarterPlan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Central-database seeder for baseline subscription plans (R4.7).
 *
 * Idempotently creates the Starter tier with its quota limits and feature
 * flags via the single-source-of-truth {@see StarterPlan} definition, so a
 * fresh central database always has a provisionable Starter plan. Safe to run
 * repeatedly: existing rows are synced to the defaults.
 */
class PlanSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        StarterPlan::provision();
    }
}
