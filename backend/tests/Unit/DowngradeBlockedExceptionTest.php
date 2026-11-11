<?php

declare(strict_types=1);

use App\Exceptions\DowngradeBlockedException;
use App\Models\PlanLimit;

/*
|--------------------------------------------------------------------------
| Unit: DowngradeBlockedException message + payload (R6.2)
|--------------------------------------------------------------------------
|
| Pure (no DB): the exception is a 422 that names every violating resource and
| exposes the structured violations for programmatic consumption.
*/

it('is a 422 that names every violating resource (R6.2)', function () {
    $e = new DowngradeBlockedException([
        PlanLimit::MAX_USERS => ['count' => 12, 'target_limit' => 10],
        PlanLimit::MAX_PRODUCTS => ['count' => 500, 'target_limit' => 200],
    ]);

    expect($e->getStatusCode())->toBe(422);

    // Message names each violating resource with usage vs target (R6.2).
    expect($e->getMessage())
        ->toContain(PlanLimit::MAX_USERS)
        ->toContain('12')
        ->toContain('10')
        ->toContain(PlanLimit::MAX_PRODUCTS)
        ->toContain('500')
        ->toContain('200');
});

it('exposes the structured violations map', function () {
    $violations = [PlanLimit::MAX_STOCK_POINTS => ['count' => 4, 'target_limit' => 3]];
    $e = new DowngradeBlockedException($violations);

    expect($e->violations)->toBe($violations);
});
