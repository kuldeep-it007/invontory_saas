<?php

declare(strict_types=1);

use App\Models\Discount;
use App\Models\Price;
use App\Services\PriceResolutionService;

/*
|--------------------------------------------------------------------------
| Unit: PriceResolutionService pure computation core (R12.2, R12.4, R39.1)
|--------------------------------------------------------------------------
|
| computePrice(): markup/MUP on the base, then AT MOST ONE discount (percent or
| fixed amount), clamped so the result is never negative, tagged with the
| tenant currency. Discounts are never combined (the resolver picks one by
| priority; see Feature\DiscountPriorityTest). No database needed.
*/

function priceResolver(): PriceResolutionService
{
    return new PriceResolutionService;
}

function percentOff(float $percent): Discount
{
    return new Discount(['value_type' => Discount::VALUE_PERCENT, 'percentage' => $percent]);
}

function amountOff(float $amount): Discount
{
    return new Discount(['value_type' => Discount::VALUE_FIXED, 'percentage' => 0, 'amount' => $amount]);
}

it('returns the base unchanged with no markup and no discount (R12.4)', function () {
    $resolved = priceResolver()->computePrice(100.0);

    expect($resolved->base)->toBe(100.0)
        ->and($resolved->markedUp)->toBe(100.0)
        ->and($resolved->final)->toBe(100.0)
        ->and($resolved->discountLevel)->toBeNull();
});

it('applies a collection markup percentage to the base (R12.2)', function () {
    $resolved = priceResolver()->computePrice(100.0, Price::MARKUP_TYPE_MARKUP, 10.0);

    expect(round($resolved->markedUp, 2))->toBe(110.0)
        ->and(round($resolved->final, 2))->toBe(110.0);
});

it('applies MUP the same way as markup (R12.2)', function () {
    expect(priceResolver()->computePrice(200.0, Price::MARKUP_TYPE_MUP, 25.0)->markedUp)->toBe(250.0);
});

it('takes a percentage discount off the marked-up amount', function () {
    // base 100 -> markup +10% = 110 -> 10% off = 99
    $resolved = priceResolver()->computePrice(100.0, Price::MARKUP_TYPE_MARKUP, 10.0, percentOff(10.0), Discount::LEVEL_GENERAL);

    expect(round($resolved->final, 2))->toBe(99.0)
        ->and(round($resolved->discountAmount, 2))->toBe(11.0)
        ->and($resolved->discountType)->toBe('percent')
        ->and($resolved->discountLevel)->toBe('general');
});

it('takes a fixed amount off the marked-up amount', function () {
    $resolved = priceResolver()->computePrice(250.0, null, null, amountOff(40.0), Discount::LEVEL_PRODUCT);

    expect($resolved->final)->toBe(210.0)
        ->and($resolved->discountAmount)->toBe(40.0)
        ->and($resolved->discountType)->toBe('fixed')
        ->and($resolved->discountValue)->toBe(40.0)
        ->and($resolved->toArray()['discount'])->toMatchArray(['level' => 'product', 'type' => 'fixed', 'amount' => 40.0]);
});

it('never returns a negative price: a fixed amount larger than the price gives 0 (R12.4)', function () {
    $resolved = priceResolver()->computePrice(30.0, null, null, amountOff(50.0));

    expect($resolved->final)->toBe(0.0)
        ->and($resolved->discountAmount)->toBe(30.0);
});

it('never returns a negative price even with a 100% discount (R12.4)', function () {
    expect(priceResolver()->computePrice(100.0, null, null, percentOff(100.0))->final)->toBe(0.0);
});

it('caps a percentage above 100 at 100 (R12.4)', function () {
    expect(priceResolver()->computePrice(100.0, null, null, percentOff(150.0))->final)->toBe(0.0);
});

it('clamps a negative base to zero (R12.4)', function () {
    $resolved = priceResolver()->computePrice(-50.0);

    expect($resolved->base)->toBe(0.0)
        ->and($resolved->final)->toBe(0.0);
});

it('ignores markup when the type is set but value is null (R12.2)', function () {
    expect(priceResolver()->computePrice(100.0, Price::MARKUP_TYPE_MARKUP, null)->markedUp)->toBe(100.0);
});

it('tags the resolved price with a non-empty currency code (R39.1)', function () {
    $resolved = priceResolver()->computePrice(100.0);

    expect($resolved->currency)->toBeString()->not->toBe('');
});
