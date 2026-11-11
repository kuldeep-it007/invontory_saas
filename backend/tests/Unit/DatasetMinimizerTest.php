<?php

declare(strict_types=1);

use App\Services\Ai\DatasetMinimizer;

/*
|--------------------------------------------------------------------------
| Task 13.6 — DatasetMinimizer: send only the minimum aggregated data
|--------------------------------------------------------------------------
|
| Supports Requirement 32.3 ("send only the minimum aggregated data required").
| Pure-logic unit tests: the minimizer derives a compact aggregate profile
| (shape + numeric summaries + a bounded sample) from a dataset with no I/O.
*/

it('derives numeric summaries and reports the dataset shape', function () {
    $minimizer = new DatasetMinimizer;

    $rows = [
        ['product_id' => 1, 'quantity' => 10],
        ['product_id' => 2, 'quantity' => 20],
        ['product_id' => 3, 'quantity' => 30],
    ];

    $out = $minimizer->minimize($rows);

    expect($out['row_count'])->toBe(3);
    expect($out['columns'])->toBe(['product_id', 'quantity']);

    $qty = $out['numeric_summaries']['quantity'];
    expect($qty['count'])->toBe(3);
    expect($qty['sum'])->toBe(60.0);
    expect($qty['min'])->toBe(10.0);
    expect($qty['max'])->toBe(30.0);
    expect($qty['avg'])->toBe(20.0);
});

it('bounds the sample to at most MAX_SAMPLE_ROWS leading rows', function () {
    $minimizer = new DatasetMinimizer;

    $rows = [];
    for ($i = 0; $i < DatasetMinimizer::MAX_SAMPLE_ROWS + 50; $i++) {
        $rows[] = ['n' => $i];
    }

    $out = $minimizer->minimize($rows);

    expect($out['row_count'])->toBe(DatasetMinimizer::MAX_SAMPLE_ROWS + 50);
    // The full dataset is NOT shipped — only a bounded sample (R32.3).
    expect(count($out['sample']))->toBe(DatasetMinimizer::MAX_SAMPLE_ROWS);
    expect($out['sample'][0]['n'])->toBe(0);
});

it('treats a column with any non-numeric value as a dimension, not a measure', function () {
    $minimizer = new DatasetMinimizer;

    $rows = [
        ['label' => 'north', 'amount' => 5],
        ['label' => 'south', 'amount' => 7],
    ];

    $out = $minimizer->minimize($rows);

    // `amount` is summarised; `label` (a dimension) is not.
    expect($out['numeric_summaries'])->toHaveKey('amount');
    expect($out['numeric_summaries'])->not->toHaveKey('label');
});

it('handles an empty dataset without error', function () {
    $minimizer = new DatasetMinimizer;

    $out = $minimizer->minimize([]);

    expect($out['row_count'])->toBe(0);
    expect($out['columns'])->toBe([]);
    expect($out['numeric_summaries'])->toBe([]);
    expect($out['sample'])->toBe([]);
});
