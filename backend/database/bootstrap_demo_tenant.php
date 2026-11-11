<?php

/**
 * One-off local bootstrap: provision a demo tenant resolvable at `localhost`
 * with a Company_Admin you can log in with. Run inside the api container:
 *
 *   docker compose exec api php artisan tinker database/bootstrap_demo_tenant.php
 *
 * Idempotent-ish: if the `localhost` domain already exists, provisioning
 * throws a 409 ConflictHttpException, which is caught and reported.
 */

use App\Services\TenantProvisioningService;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

$svc = app(TenantProvisioningService::class);
$plan = $svc->ensureStarterPlan();

try {
    $result = $svc->provision([
        'subdomain' => 'demo',
        'domain' => 'localhost',        // so http://localhost:8000 resolves this tenant
        'plan_id' => $plan->id,
        'company_name' => 'Demo Company',
        'admin_name' => 'Demo Admin',
        'admin_email' => 'admin@demo.test',
        'admin_password' => 'password123',
    ]);

    fwrite(STDOUT, 'OK tenant_id=' . $result['tenant']->getTenantKey()
        . ' admin_id=' . $result['admin_id'] . PHP_EOL);
    fwrite(STDOUT, 'Login: admin@demo.test / password123 at host localhost' . PHP_EOL);
} catch (ConflictHttpException $e) {
    fwrite(STDOUT, 'ALREADY EXISTS: ' . $e->getMessage() . PHP_EOL);
}
