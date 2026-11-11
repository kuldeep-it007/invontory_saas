<?php

declare(strict_types=1);

use App\Services\Stock\StockMovement;

/*
|--------------------------------------------------------------------------
| Task 7.12 — StockMovement value object (no DB)
|--------------------------------------------------------------------------
|
| Pure-logic checks for the movement DTO consumed by StockMovementService:
| it validates its inputs and produces a deterministic composite stock key so
| movements can be grouped and locked in a stable order (R16.5).
|
*/

it('builds a deterministic composite stock key', function () {
    $movement = new StockMovement(
        stockableType: 'App\\Models\\Company',
        stockableId: 7,
        productId: 3,
        stockPointId: 11,
        delta: '5.000',
        movementType: 'received',
    );

    expect($movement->stockKey())->toBe('App\\Models\\Company|7|3|11');
});

it('treats two movements for the same owner/product/point as the same key', function () {
    $a = new StockMovement('App\\Models\\Company', 1, 2, 3, '5.000', 'received');
    $b = new StockMovement('App\\Models\\Company', 1, 2, 3, '-2.000', 'sale_out');

    expect($a->stockKey())->toBe($b->stockKey());
});

it('rejects a non-numeric delta', function () {
    expect(fn () => new StockMovement('App\\Models\\Company', 1, 2, 3, 'abc', 'received'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects an empty owner type or movement type', function () {
    expect(fn () => new StockMovement('', 1, 2, 3, '5.000', 'received'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => new StockMovement('App\\Models\\Company', 1, 2, 3, '5.000', ''))
        ->toThrow(InvalidArgumentException::class);
});

it('accepts negative and fractional deltas', function () {
    $movement = new StockMovement('App\\Models\\Company', 1, 2, 3, '-12.345', 'adjustment_out', 'damaged', 'ADJ-9');

    expect($movement->delta)->toBe('-12.345');
    expect($movement->reason)->toBe('damaged');
    expect($movement->reference)->toBe('ADJ-9');
});
