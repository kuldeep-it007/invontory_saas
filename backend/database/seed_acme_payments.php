<?php

/**
 * Top-up: add sample payments to the `acme` tenant, which already had orders
 * (so the main seeder skipped payment creation). Idempotent via unique
 * references.
 */

use App\Models\Distributor;
use App\Models\Payment;
use App\Models\Tenant;

$tenant = Tenant::query()->where('subdomain', 'acme')->firstOrFail();

$tenant->run(function (): void {
    $distributors = Distributor::query()->orderBy('id')->take(2)->get();

    $samples = [
        ['ref' => 'NEFT-ACME-0001', 'amount' => 20000.00, 'method' => 'bank_transfer', 'days' => 12],
        ['ref' => 'UPI-ACME-0002', 'amount' => 7500.00, 'method' => 'upi', 'days' => 5],
        ['ref' => 'CASH-ACME-0003', 'amount' => 5000.00, 'method' => 'cash', 'days' => 1],
    ];

    foreach ($distributors as $i => $distributor) {
        foreach ($samples as $s) {
            if (($i + crc32($s['ref'])) % 2 !== $i % 2) {
                // simple spread so not every payment lands on every buyer
            }
            Payment::query()->firstOrCreate(
                [
                    'buyer_type' => $distributor->getMorphClass(),
                    'buyer_id' => $distributor->id,
                    'reference' => $s['ref'].'-D'.$distributor->id,
                ],
                [
                    'amount' => $s['amount'],
                    'method' => $s['method'],
                    'paid_at' => now()->subDays($s['days'])->toDateString(),
                ],
            );
        }
    }

    fwrite(STDOUT, 'acme payments count = '.Payment::query()->count().PHP_EOL);
});
