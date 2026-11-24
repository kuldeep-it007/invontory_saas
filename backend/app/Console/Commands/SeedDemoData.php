<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\TenantProvisioningService;
use Database\Seeders\DemoTenantSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('demo:seed {--fresh : Delete the demo tenants (and their databases) first} {--prefix= : Prefix for the demo subdomains (default none)} {--domain= : Base domain for tenant hosts (default: localhost when it is a central domain, else the first central domain)} {--only= : Only this tenant: acme, starter or lapsed} {--password=password123 : Password for every demo login}')]
#[Description('Create demo tenants with data for every scenario: roles, catalogue, stock, discounts, orders in every state, payments, overdue, quotas, subscription states')]
class SeedDemoData extends Command
{
    /** subdomain => [profile, plan, subscription status, company, admin local part, extra tenant flags] */
    private const TENANTS = [
        'acme' => ['rich', 'Growth', Subscription::STATUS_ACTIVE, 'Acme Tiles & Sanitaryware', 'admin', ['backorders_enabled' => true, 'dealer_stock_enabled' => true]],
        'starter' => ['boundary', 'Starter', Subscription::STATUS_TRIAL, 'Starter Co (at its quota limits)', 'owner', []],
        'lapsed' => ['empty', 'Starter', Subscription::STATUS_PAST_DUE, 'Lapsed Co (subscription past due)', 'owner', []],
    ];

    public function handle(TenantProvisioningService $provisioning): int
    {
        $password = (string) $this->option('password');
        if (strlen($password) < 8) {
            $this->error('The password must be at least 8 characters.');

            return self::FAILURE;
        }
        $prefix = strtolower((string) $this->option('prefix'));
        if ($prefix !== '' && preg_match('/^[a-z0-9-]{1,20}$/', $prefix) !== 1) {
            $this->error('The prefix may contain only a-z, 0-9 and "-" (max 20 characters).');

            return self::FAILURE;
        }
        $domains = (array) config('tenancy.central_domains', ['localhost']);
        $domain = strtolower((string) ($this->option('domain') ?: (in_array('localhost', $domains, true) ? 'localhost' : $domains[0])));
        $only = $this->option('only');
        if ($only !== null && ! isset(self::TENANTS[$only])) {
            $this->error('--only must be one of: '.implode(', ', array_keys(self::TENANTS)));

            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => PlanSeeder::class, '--force' => true]);
        $growth = $this->growthPlan();
        $starter = Plan::query()->where('name', 'Starter')->firstOrFail();
        $problems = [];
        $logins = [];

        foreach (self::TENANTS as $name => [$profile, $planName, $status, $company, $adminLocal, $flags]) {
            if ($only !== null && $only !== $name) {
                continue;
            }
            $subdomain = $prefix.$name;
            $existing = Tenant::query()->where('subdomain', $subdomain)->first();
            if ($existing !== null && ! $this->option('fresh')) {
                $this->warn("Tenant {$subdomain} already exists; skipped (use --fresh to recreate it).");

                continue;
            }
            if ($existing !== null) {
                $this->line("Deleting tenant {$subdomain} ...");
                $existing->domains()->delete();
                $existing->delete();
            }

            $host = $subdomain.'.'.$domain;
            $adminEmail = $adminLocal.'@'.$subdomain.'.test';
            $this->line("Provisioning {$subdomain} ({$profile}) ...");
            try {
                $result = $provisioning->provision([
                    'subdomain' => $subdomain, 'domain' => $host, 'plan_id' => ($planName === 'Growth' ? $growth : $starter)->getKey(), 'currency' => 'INR',
                    'company_name' => $company, 'company_gstin' => '27AABCA0000A1Z5',
                    'admin_name' => 'Demo Admin', 'admin_email' => $adminEmail, 'admin_password' => $password,
                    'subscription_status' => $status,
                ] + $flags);
            } catch (Throwable $e) {
                $problems[] = "{$subdomain}: provisioning failed: ".$e->getMessage();

                continue;
            }

            $seeder = new DemoTenantSeeder($password);
            /** @var Tenant $tenant */
            $tenant = $result['tenant'];
            $tenant->run(fn () => $seeder->run($profile));
            $problems = array_merge($problems, array_map(fn (string $p) => "{$subdomain}: {$p}", $seeder->problems));

            $this->table(['What', 'Count'], collect($seeder->summary)->map(fn ($v, $k) => [$k, $v])->values()->all());
            $logins[] = [$subdomain, "http://{$host}", $adminEmail, $status];
        }

        if ($logins !== []) {
            $this->newLine();
            $this->info('Logins (password for all: '.$password.')');
            $this->table(['Tenant', 'URL', 'Company admin', 'Subscription'], $logins);
            $this->line('acme also has: staff@, readonly@, distributor.{north,south,east,west}@ and dealer.{north_shop,north_mart,south_store,west_depot}@ the same domain (@<tenant>.test).');
            $this->line('Platform admin (separate login): php artisan platform:admin');
        }
        foreach ($problems as $problem) {
            $this->warn($problem);
        }

        return $problems === [] ? self::SUCCESS : self::FAILURE;
    }

    /** A plan with every optional module on and no quotas, so the rich tenant is not limited. */
    private function growthPlan(): Plan
    {
        /** @var Plan $plan */
        $plan = Plan::query()->firstOrCreate(['name' => 'Growth'], ['price' => 99.0, 'billing_cycle' => 'monthly', 'is_active' => true]);
        foreach (PlanFeature::FEATURE_KEYS as $key) {
            $plan->features()->updateOrCreate(['key' => $key], ['enabled' => true]);
        }

        return $plan;
    }
}
