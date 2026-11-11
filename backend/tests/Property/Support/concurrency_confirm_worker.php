<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Parallel confirmation worker — Task 9.21 (Property 5: no oversell)
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 5: No overselling under
| concurrency
|
| This script is spawned as an INDEPENDENT OS process (one per concurrent
| confirmation) by tests/Property/NoOversellUnderConcurrencyTest.php. Each
| process:
|
|   1. Boots the real Laravel application (its own container + its own fresh
|      PDO connection to MySQL — no sharing with the parent or siblings), so N
|      of these running at once produce GENUINE OS-level parallelism all
|      contending for the same tenant's locked stock row.
|   2. Initializes tenancy for the SAME tenant id passed on the CLI, so every
|      worker is pointed at the identical per-tenant database and the identical
|      stock row.
|   3. Fires ONE real order-confirmation decrement through the production
|      App\Services\StockMovementService::apply() path — the exact code under
|      test, whose transaction locks the row with SELECT ... FOR UPDATE
|      (R16.5, R35.1) and rejects a below-zero result when the tenant has
|      backorders disabled (R17.1).
|   4. Writes its outcome as a single JSON object to the RESULT FILE handed to
|      it on the CLI. The parent reads these files once every worker has
|      exited. A file (rather than a stdout pipe) is used because Windows
|      proc_open pipe flushing combined with Laravel's output buffering makes
|      stdout capture unreliable; a result file is written+fsynced by the
|      worker and is always visible to the parent.
|
| The worker never mutates anything except via StockMovementService::apply(),
| so the oversell guarantee it is probing is the production guarantee.
|
| Usage (invoked by the test, not by hand):
|   php concurrency_confirm_worker.php <resultFile> <tenantId> <ownerType> \
|       <ownerId> <productId> <stockPointId> <demandUnits> <reference> \
|       <startEpochMicros>
*/

$root = dirname(__DIR__, 3); // backend/

$resultFile = (string) ($argv[1] ?? '');

/**
 * Atomically write this worker's JSON result to its result file, then exit so
 * the parent — which polls for every result file to appear — can read it.
 */
$writeResult = static function (string $status, string $demand, string $detail = '') use ($resultFile): void {
    if ($resultFile !== '') {
        @file_put_contents(
            $resultFile,
            json_encode(['status' => $status, 'demand' => $demand, 'detail' => $detail])
        );
    }
    exit(0);
};

// A bootstrap-time fatal (bad autoload, boot failure) still leaves a result
// file behind so the worker never looks like a silent hang to the parent.
register_shutdown_function(static function () use ($resultFile) {
    $err = error_get_last();
    if (
        $resultFile !== ''
        && ! file_exists($resultFile)
        && $err !== null
        && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)
    ) {
        @file_put_contents(
            $resultFile,
            json_encode(['status' => 'error', 'demand' => '0.000', 'detail' => 'fatal: '.$err['message']])
        );
    }
});

require $root.'/vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require $root.'/bootstrap/app.php';

$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Tenant;
use App\Services\Stock\StockMovement;
use App\Services\StockMovementService;

/**
 * Normalize an integer unit count to a DECIMAL(15,3) string, e.g. 7 => "7.000".
 */
function worker_qty(int $units): string
{
    return number_format($units, 3, '.', '');
}

$tenantId = (string) ($argv[2] ?? '');
$ownerType = (string) ($argv[3] ?? '');
$ownerId = (int) ($argv[4] ?? 0);
$productId = (int) ($argv[5] ?? 0);
$stockPointId = (int) ($argv[6] ?? 0);
$demandUnits = (int) ($argv[7] ?? 0);
$reference = (string) ($argv[8] ?? 'CONC');
$startAt = (float) ($argv[9] ?? 0.0);

try {
    /** @var Tenant|null $tenant */
    $tenant = Tenant::find($tenantId);

    if ($tenant === null) {
        $writeResult('error', worker_qty($demandUnits), 'tenant not found: '.$tenantId);
    }

    // All workers busy-wait to a shared wall-clock start instant so they fire
    // their FOR UPDATE confirmations as close to simultaneously as possible,
    // maximizing genuine contention on the single locked stock row.
    if ($startAt > 0.0) {
        $now = microtime(true);
        if ($startAt > $now) {
            usleep((int) (($startAt - $now) * 1_000_000));
        }
    }

    $tenant->run(function () use ($ownerType, $ownerId, $productId, $stockPointId, $demandUnits, $reference, $writeResult) {
        $service = new StockMovementService;

        $movement = new StockMovement(
            $ownerType,
            $ownerId,
            $productId,
            $stockPointId,
            '-'.worker_qty($demandUnits),
            'sale_out',
            null,
            $reference,
        );

        try {
            // No explicit allow_negative: the per-tenant backorders toggle
            // (OFF for this proof) governs the guard, so a confirmation that
            // would drive the row below zero is rejected (R17.1), exactly as a
            // real order confirmation behaves.
            $service->apply([$movement]);
            $writeResult('confirmed', worker_qty($demandUnits));
        } catch (\App\Exceptions\InsufficientStockException $e) {
            $writeResult('rejected', worker_qty($demandUnits), '422');
        }
    });
} catch (\Throwable $e) {
    $writeResult('error', worker_qty($demandUnits), get_class($e).': '.$e->getMessage());
}
