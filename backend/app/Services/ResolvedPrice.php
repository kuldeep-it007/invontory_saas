<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Immutable result of an order-line price resolution (R12.4, R12.5, R39.1).
 *
 * Carries the resolved monetary values — all expressed in the Tenant's single
 * configured currency (R39.1) — produced by {@see PriceResolutionService}:
 *
 *   • {@see $base}        the applicable price (product-level overriding
 *                         collection-level — R12.4);
 *   • {@see $markedUp}    the base after the collection markup/MUP (R12.2);
 *   • {@see $final}       the price after all applicable discounts applied
 *                         cumulatively, clamped so it is never negative
 *                         (R12.4, R12.5);
 *   • {@see $currency}    the tenant's single currency code (R39.1).
 */
final class ResolvedPrice
{
    public function __construct(
        public readonly float $base,
        public readonly float $markedUp,
        public readonly float $final,
        public readonly string $currency,
    ) {}

    /**
     * @return array{base: float, marked_up: float, final: float, currency: string}
     */
    public function toArray(): array
    {
        return [
            'base' => $this->base,
            'marked_up' => $this->markedUp,
            'final' => $this->final,
            'currency' => $this->currency,
        ];
    }
}
