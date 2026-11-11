<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Models\Stock;
use App\Models\StockPoint;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-stock-point stock availability of a Buyer's SELLER for a product (R16,
 * R20, R23.4).
 *
 * When building an order a Buyer may source a single line from several of the
 * seller's stock points. This service answers "for this buyer + product, which
 * of the seller's stock points carry it, and how much is available at each?":
 *
 *   • a Distributor buyer's seller is the Company (R20.1);
 *   • a Dealer buyer's seller is its serving Distributor (R20.2).
 *
 * Availability per point is on-hand minus active reservations, reusing
 * {@see StockReservationService} so "available" means the same thing everywhere
 * (R23.4).
 */
class SellerStockService
{
    public function __construct(private readonly StockReservationService $reservations) {}

    /**
     * The seller's stock points for a product, each with on-hand / reserved /
     * available quantities. Points with no stock row for the product report
     * zeros so the buyer still sees every point.
     *
     * @return array{
     *     seller_type: string,
     *     seller_id: int,
     *     points: list<array{stock_point_id:int, name:string, on_hand:string, reserved:string, available:string}>
     * }
     */
    public function pointsForBuyerProduct(Model $buyer, int $productId): array
    {
        $seller = $this->resolveSeller($buyer);

        $points = StockPoint::query()
            ->where('owner_type', $seller->getMorphClass())
            ->where('owner_id', (int) $seller->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name']);

        $rows = [];
        foreach ($points as $point) {
            $stock = Stock::query()
                ->where('stockable_type', $seller->getMorphClass())
                ->where('stockable_id', (int) $seller->getKey())
                ->where('product_id', $productId)
                ->where('stock_point_id', (int) $point->id)
                ->first();

            $onHand = $stock !== null ? (string) $stock->quantity : '0.000';
            $reserved = $stock !== null ? $this->reservations->reservedQuantity((int) $stock->getKey()) : '0.000';
            $available = $stock !== null ? $this->reservations->availableQuantity($stock) : '0.000';

            $rows[] = [
                'stock_point_id' => (int) $point->id,
                'name' => (string) $point->name,
                'on_hand' => $onHand,
                'reserved' => $reserved,
                'available' => $available,
            ];
        }

        return [
            'seller_type' => $this->shortType($seller),
            'seller_id' => (int) $seller->getKey(),
            'points' => $rows,
        ];
    }

    /**
     * The TOTAL available quantity of a product across all of the buyer's
     * seller's stock points (Σ available). Used to block ordering more than is
     * on hand (R16.4, R17.1). Returned as a float for comparison.
     */
    public function totalAvailableForBuyerProduct(Model $buyer, int $productId): float
    {
        $sourcing = $this->pointsForBuyerProduct($buyer, $productId);

        $total = 0.0;
        foreach ($sourcing['points'] as $point) {
            $total += (float) $point['available'];
        }

        return $total;
    }

    /**
     * Resolve the Seller for a Buyer (R20.1, R20.2): a Distributor buys from the
     * Company; a Dealer buys from its serving Distributor.
     */
    private function resolveSeller(Model $buyer): Model
    {
        if ($buyer instanceof Distributor) {
            return Company::query()->firstOrFail();
        }

        if ($buyer instanceof Dealer) {
            return $buyer->distributor()->firstOrFail();
        }

        return Company::query()->firstOrFail();
    }

    private function shortType(Model $seller): string
    {
        return match (true) {
            $seller instanceof Company => 'company',
            $seller instanceof Distributor => 'distributor',
            $seller instanceof Dealer => 'dealer',
            default => $seller->getMorphClass(),
        };
    }
}
