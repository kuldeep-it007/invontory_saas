<?php

declare(strict_types=1);

use App\Models\Discount;
use App\Models\Price;
use App\Services\PriceResolutionService;
use App\Services\ResolvedPrice;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Task 7.9 — Property 23: Pricing and discount calculation
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 23: Pricing and discount
| calculation
|
| Validates: Requirements 12.4, 12.5, 39.1
|
| Property statement (design.md §Property 23):
|   For any order line with an applicable price and set of applicable discounts
|   (`customer` and/or `online`), the computed price equals the base price with
|   all applicable discounts applied cumulatively, is never negative, and is
|   expressed in the tenant's single configured currency.
|
|   - R12.4 When an Order_Line price is calculated, the applicable Price and
|           discounts for the Buyer are applied and ALL monetary values resolve
|           in the Tenant's SINGLE configured currency.
|   - Discounts are NEVER combined: at most one discount (percentage or fixed
|     amount) applies, chosen by priority (Dealer > Distributor > Product >
|     General; see Feature\DiscountPriorityTest).
|   - R39.1 The Platform operates each Tenant in a single configured currency.
|
| The invariant under test is a pure, deterministic property of the arithmetic
| core PriceResolutionService::computePrice():
|
|       final == max(0, base * (1 + markup/100)? - saving(discount))
|
| so it needs no database or booted tenant and runs in every environment —
| mirroring the pure RBAC property proof. The DB-backed resolution path
| (product- vs collection-level, buyer-scoped discounts) is exercised by the
| example-based Feature\PriceResolutionTest; here we assert the UNIVERSAL
| calculation invariant across a wide generated input space.
|
| Generation uses innmind/black-box (NOT hand-rolled) via the shared
| static::forAll(), which draws the mandated minimum of 100 generated
| scenarii per proof and shrinks any counterexample to a minimal failing input.
*/

/**
 * Independent reference: markup onto the base, then ONE discount (percent of
 * the marked-up amount, or a fixed amount), clamped so it is never negative.
 */
function expectedSingleDiscountPrice(float $base, ?float $markupValue, ?string $valueType, float $value): float
{
    $amount = max(0.0, $base);
    if ($markupValue !== null) {
        $amount *= 1.0 + ($markupValue / 100.0);
    }
    $saving = match ($valueType) {
        'percent' => $amount * (max(0.0, min(100.0, $value)) / 100.0),
        'fixed' => max(0.0, $value),
        default => 0.0,
    };

    return max(0.0, $amount - min($amount, $saving));
}

function discountOf(?string $valueType, float $value): ?Discount
{
    return match ($valueType) {
        'percent' => new Discount(['value_type' => 'percent', 'percentage' => $value]),
        'fixed' => new Discount(['value_type' => 'fixed', 'percentage' => 0, 'amount' => $value]),
        default => null,
    };
}

it('computes base + markup minus exactly one discount, never negative (R12.4)', function () {
    static::forAll(
        Set::realNumbers()->between(0, 100_000),
        Set::either(Set::of(null), Set::realNumbers()->between(0, 500)),
        Set::of(null, 'percent', 'fixed'),
        Set::realNumbers()->between(0, 200_000),
    )->then(function (float $base, ?float $markup, ?string $valueType, float $value) {
        $value = $valueType === 'percent' ? fmod($value, 100.0) : $value;
        $resolved = (new PriceResolutionService)->computePrice($base, $markup === null ? null : Price::MARKUP_TYPE_MARKUP, $markup, discountOf($valueType, $value));

        expect($resolved)->toBeInstanceOf(ResolvedPrice::class);
        expect($resolved->base)->toBeGreaterThanOrEqual(0.0);
        expect($resolved->markedUp)->toBeGreaterThanOrEqual(0.0);
        expect($resolved->final)->toBeGreaterThanOrEqual(0.0);
        expect($resolved->final)->toEqualWithDelta(expectedSingleDiscountPrice($base, $markup, $valueType, $value), 1e-6);
        expect($resolved->final)->toBeLessThanOrEqual($resolved->markedUp + 1e-6);
        expect($resolved->discountAmount)->toEqualWithDelta($resolved->markedUp - $resolved->final, 1e-6);
    });
});

it('drives the price to exactly zero with a 100% discount or a fixed amount at or above the price (R12.4)', function () {
    static::forAll(
        Set::realNumbers()->between(0, 100_000),
        Set::either(Set::of(null), Set::realNumbers()->between(0, 500)),
        Set::of('percent', 'fixed'),
    )->then(function (float $base, ?float $markup, string $valueType) {
        $service = new PriceResolutionService;
        $markupType = $markup === null ? null : Price::MARKUP_TYPE_MARKUP;
        $markedUp = $service->computePrice($base, $markupType, $markup)->markedUp;
        $discount = $valueType === 'percent' ? discountOf('percent', 100.0) : discountOf('fixed', $markedUp + 1.0);

        expect($service->computePrice($base, $markupType, $markup, $discount)->final)->toBe(0.0);
    });
});

it('always expresses the resolved price in a single non-empty tenant currency (R12.4, R39.1)', function () {
    static::forAll(
        Set::realNumbers()->between(0, 100_000),
        Set::of(null, 'percent', 'fixed'),
    )->then(function (float $base, ?string $valueType) {
        $resolved = (new PriceResolutionService)->computePrice($base, null, null, discountOf($valueType, 10.0));

        expect($resolved->currency)->toBeString()->not->toBe('')
            ->and($resolved->currency)->toBe(PriceResolutionService::DEFAULT_CURRENCY);
    });
});
