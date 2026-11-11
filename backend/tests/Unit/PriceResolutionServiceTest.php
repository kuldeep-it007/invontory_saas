<?php

declare(strict_types=1);

use App\Models\Price;
use App\Services\PriceResolutionService;

/*
|--------------------------------------------------------------------------
| Unit: PriceResolutionService pure computation core (R12.2, R12.4, R12.5, R39.1)
|--------------------------------------------------------------------------
|
| Exercises computePrice() — the pure, deterministic arithmetic that resolves an
| order-line price: markup/MUP applied to the base, then all applicable
| discounts applied CUMULATIVELY, clamped so the result is NEVER negative, and
| tagged with the tenant's single currency (falling back to the tenancy default
| outside an initialized tenant). No database or tenancy needed.
*/

function priceResolver(): PriceResolutionService
{
    return new PriceResolutionService();
}

it('returns the base unchanged with no markup and no discounts (R12.4)', function () {
    $resolved = priceResolver()->computePrice(100.0);

    expect($resolved->base)->toBe(100.0);
    expect($resolved->markedUp)->toBe(100.0);
    expect($resolved->final)->toBe(100.0);
});

it('applies a collection markup percentage to the base (R12.2)', function () {
    $resolved = priceResolver()->computePrice(100.0, Price::MARKUP_TYPE_MARKUP, 10.0);

    expect(round($resolved->markedUp, 2))->toBe(110.0);
    expect(round($resolved->final, 2))->toBe(110.0);
});

it('applies MUP the same way as markup (R12.2)', function () {
    $resolved = priceResolver()->computePrice(200.0, Price::MARKUP_TYPE_MUP, 25.0);

    expect($resolved->markedUp)->toBe(250.0);
});

it('applies a single discount off the marked-up amount (R12.5)', function () {
    // base 100 -> markup +10% = 110 -> 10% off = 99
    $resolved = priceResolver()->computePrice(100.0, Price::MARKUP_TYPE_MARKUP, 10.0, [10.0]);

    expect(round($resolved->final, 2))->toBe(99.0);
});

it('applies customer + online discounts cumulatively (R12.5)', function () {
    // base 100, no markup -> 10% off then 20% off = 100 * 0.9 * 0.8 = 72
    $resolved = priceResolver()->computePrice(100.0, null, null, [10.0, 20.0]);

    expect(round($resolved->final, 2))->toBe(72.0);
});

it('is order-independent for cumulative discounts (R12.5)', function () {
    $a = priceResolver()->computePrice(100.0, null, null, [10.0, 20.0])->final;
    $b = priceResolver()->computePrice(100.0, null, null, [20.0, 10.0])->final;

    expect(round($a, 6))->toBe(round($b, 6));
});

it('never returns a negative price even with a 100% discount (R12.4)', function () {
    $resolved = priceResolver()->computePrice(100.0, null, null, [100.0]);

    expect($resolved->final)->toBe(0.0);
    expect($resolved->final)->toBeGreaterThanOrEqual(0.0);
});

it('clamps a negative base to zero (R12.4)', function () {
    $resolved = priceResolver()->computePrice(-50.0);

    expect($resolved->base)->toBe(0.0);
    expect($resolved->final)->toBe(0.0);
});

it('ignores markup when the type is set but value is null (R12.2)', function () {
    $resolved = priceResolver()->computePrice(100.0, Price::MARKUP_TYPE_MARKUP, null);

    expect($resolved->markedUp)->toBe(100.0);
});

it('tags the resolved price with a non-empty currency code (R39.1)', function () {
    $resolved = priceResolver()->computePrice(100.0);

    expect($resolved->currency)->toBeString();
    expect($resolved->currency)->not->toBe('');
});
