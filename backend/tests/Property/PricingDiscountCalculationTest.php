<?php

declare(strict_types=1);

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
|   - R12.5 Where multiple discount types are simultaneously applicable, ALL
|           applicable discounts are applied CUMULATIVELY (compounding).
|   - R39.1 The Platform operates each Tenant in a single configured currency.
|
| The invariant under test is a pure, deterministic property of the arithmetic
| core PriceResolutionService::computePrice():
|
|       final == base * (1 + markup/100)? * Π(1 - dᵢ/100)       (clamped ≥ 0)
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
 * The independent reference computation of Property 23's formula. Deliberately
 * written separately from the production arithmetic so the test does not merely
 * restate the implementation: markup raised onto the base, then every discount
 * compounded cumulatively, finally clamped so the price is never negative.
 *
 * @param  array<int, float>  $discountPercents
 */
function expectedCumulativePrice(
    float $base,
    ?float $markupValue,
    array $discountPercents,
): float {
    $amount = max(0.0, $base);

    if ($markupValue !== null) {
        $amount *= 1.0 + ($markupValue / 100.0);
    }

    foreach ($discountPercents as $percent) {
        $clamped = max(0.0, min(100.0, $percent));
        $amount *= 1.0 - ($clamped / 100.0);
    }

    return max(0.0, $amount);
}

it('computes a price equal to the base with every discount applied cumulatively, never negative (R12.4, R12.5)', function () {
    // BlackBox generates:
    //   base            : a non-negative applicable price (R12.4 base).
    //   markup          : null (no markup) or a non-negative markup percentage (R12.2).
    //   a set of 0..5   : applicable `customer`/`online` discount percentages in
    //                     [0, 100], applied cumulatively (R12.5).
    static::forAll(
        Set::realNumbers()->between(0, 100_000),
        Set::either(
            Set::of(null),
            Set::realNumbers()->between(0, 500),
        ),
        Set::sequence(Set::realNumbers()->between(0, 100))->between(0, 5),
    )->then(function (float $base, ?float $markup, array $discounts) {
        $service = new PriceResolutionService();

        $markupType = $markup === null ? null : Price::MARKUP_TYPE_MARKUP;

        $resolved = $service->computePrice($base, $markupType, $markup, $discounts);

        expect($resolved)->toBeInstanceOf(ResolvedPrice::class);

        // (1) NEVER NEGATIVE (R12.4): neither the base, the marked-up amount,
        //     nor the final price may ever drop below zero.
        expect($resolved->base)->toBeGreaterThanOrEqual(0.0);
        expect($resolved->markedUp)->toBeGreaterThanOrEqual(0.0);
        expect($resolved->final)->toBeGreaterThanOrEqual(0.0);

        // (2) CUMULATIVE DISCOUNTS (R12.5): the final price equals the base
        //     (plus markup) with every applicable discount compounded. Compared
        //     against an independent reference implementation of the formula.
        $expected = expectedCumulativePrice($base, $markup, $discounts);
        expect($resolved->final)->toEqualWithDelta($expected, 1e-6);

        // The final price can only be <= the marked-up amount: discounts only
        // reduce (or leave unchanged when the set is empty / all 0%).
        expect($resolved->final)->toBeLessThanOrEqual($resolved->markedUp + 1e-6);
    });
});

it('applies cumulative discounts order-independently so the result is commutative (R12.5)', function () {
    // The compounding product Π(1 - dᵢ/100) is commutative, so reversing the
    // discount order must not change the computed price.
    static::forAll(
        Set::realNumbers()->between(0, 100_000),
        Set::sequence(Set::realNumbers()->between(0, 100))->between(2, 5),
    )->then(function (float $base, array $discounts) {
        $service = new PriceResolutionService();

        $forward = $service->computePrice($base, null, null, $discounts)->final;
        $reversed = $service->computePrice($base, null, null, array_reverse($discounts))->final;

        expect($forward)->toEqualWithDelta($reversed, 1e-6);
    });
});

it('clamps the final price to zero whenever a full (100%) discount applies, never going negative (R12.4)', function () {
    // Any set containing a 100% discount must drive the final price to exactly
    // zero regardless of base, markup, or the other discounts — and never below.
    static::forAll(
        Set::realNumbers()->between(0, 100_000),
        Set::either(Set::of(null), Set::realNumbers()->between(0, 500)),
        Set::sequence(Set::realNumbers()->between(0, 100))->between(0, 4),
    )->then(function (float $base, ?float $markup, array $others) {
        $service = new PriceResolutionService();

        $markupType = $markup === null ? null : Price::MARKUP_TYPE_MARKUP;
        $discounts = [...$others, 100.0];

        $resolved = $service->computePrice($base, $markupType, $markup, $discounts);

        expect($resolved->final)->toBe(0.0);
        expect($resolved->final)->toBeGreaterThanOrEqual(0.0);
    });
});

it('always expresses the resolved price in a single non-empty tenant currency (R12.4, R39.1)', function () {
    // Every resolution — across any base/markup/discount combination — must be
    // tagged with exactly one non-empty currency code (the tenant's single
    // configured currency, R39.1). Outside a booted tenant the service falls
    // back to its single DEFAULT_CURRENCY, so the invariant "exactly one
    // currency, never empty" still holds universally.
    static::forAll(
        Set::realNumbers()->between(0, 100_000),
        Set::sequence(Set::realNumbers()->between(0, 100))->between(0, 5),
    )->then(function (float $base, array $discounts) {
        $resolved = (new PriceResolutionService())->computePrice($base, null, null, $discounts);

        expect($resolved->currency)->toBeString();
        expect($resolved->currency)->not->toBe('');
        expect($resolved->currency)->toBe(PriceResolutionService::DEFAULT_CURRENCY);
    });
});

