<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockPoint;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Who a buyer buys a product from, and the stock-point availability behind it
 * (R16, R20, R23.4), across the whole distribution network.
 *
 * The seller of a cart line is the holder of the stock points the buyer picks:
 *
 *   • a Distributor may buy from the Company or from ANY OTHER distributor
 *     (distributor-to-distributor ordering), never from itself;
 *   • a Dealer buys from its serving Distributor only (ordering from other
 *     holders through its distributor arrives with chain orders).
 *
 * Without a chosen point the default seller is used: for a Distributor buyer
 * the product's owner (the Company for Company products); for a Dealer its
 * serving Distributor.
 *
 * Availability per point is on-hand minus active reservations, reusing
 * {@see StockReservationService} so "available" means the same thing everywhere
 * (R23.4).
 */
class SellerStockService
{
    public function __construct(private readonly StockReservationService $reservations) {}

    /**
     * The default seller's stock points for a product, each with on-hand /
     * reserved / available quantities, plus every network holder of the
     * product (`holders`), each flagged with whether this buyer may order
     * from it.
     *
     * @return array{
     *     seller_type: string,
     *     seller_id: int,
     *     points: list<array{stock_point_id:int, name:string, on_hand:string, reserved:string, available:string}>,
     *     holders: list<array<string, mixed>>
     * }
     */
    public function pointsForBuyerProduct(Model $buyer, int $productId): array
    {
        $seller = $this->defaultSeller($buyer, $productId);

        return [
            'seller_type' => $this->shortType($seller),
            'seller_id' => (int) $seller->getKey(),
            'points' => $this->pointsFor($seller, $productId),
            'holders' => $this->holdersForProduct($productId, $buyer),
        ];
    }

    /**
     * Total available quantity of a product at one seller (Σ available over
     * its points). Used to block ordering more than is on hand (R16.4, R17.1).
     */
    public function totalAvailableAt(Model $seller, int $productId): float
    {
        $total = 0.0;
        foreach ($this->pointsFor($seller, $productId) as $point) {
            $total += (float) $point['available'];
        }

        return $total;
    }

    /**
     * Total available at the buyer's default seller for the product.
     */
    public function totalAvailableForBuyerProduct(Model $buyer, int $productId): float
    {
        return $this->totalAvailableAt($this->defaultSeller($buyer, $productId), $productId);
    }

    /**
     * The seller of a cart line: the single holder of the chosen stock points,
     * or the default seller when none are chosen.
     *
     * @param  list<int>  $stockPointIds
     *
     * @throws UnprocessableEntityHttpException when the points span holders or the buyer may not buy from the holder.
     */
    public function sellerFor(Model $buyer, int $productId, array $stockPointIds = []): Model
    {
        if ($stockPointIds === []) {
            return $this->defaultSeller($buyer, $productId);
        }

        $owners = StockPoint::query()->whereKey($stockPointIds)->get(['id', 'owner_type', 'owner_id'])
            ->map(fn (StockPoint $point): string => $point->owner_type.'#'.$point->owner_id)
            ->unique();
        if ($owners->count() !== 1) {
            throw new UnprocessableEntityHttpException('All stock points of one cart line must belong to the same seller.');
        }

        [$type, $id] = explode('#', (string) $owners->first());
        $holder = in_array($type, [Company::class, Distributor::class, Dealer::class], true)
            ? $type::query()->find((int) $id)
            : null;
        if (! $holder instanceof Model || ! $this->canBuyFrom($buyer, $holder)) {
            throw new UnprocessableEntityHttpException($buyer instanceof Dealer
                ? 'Dealers order from their own distributor\'s stock.'
                : 'You cannot order from your own stock.');
        }

        return $holder;
    }

    /**
     * Default seller: a Distributor buys a product from its owner (the Company
     * when the owner is the buyer itself or unknown); a Dealer from its
     * serving Distributor.
     */
    public function defaultSeller(Model $buyer, int $productId): Model
    {
        if ($buyer instanceof Dealer) {
            return $buyer->distributor()->firstOrFail();
        }

        if ($buyer instanceof Distributor) {
            $ownerId = Product::query()->whereKey($productId)->value('owner_distributor_id');
            if ($ownerId !== null && (int) $ownerId !== (int) $buyer->getKey()) {
                return Distributor::query()->findOrFail((int) $ownerId);
            }
        }

        return Company::query()->firstOrFail();
    }

    /** Whether a buyer may buy from a stock holder. */
    public function canBuyFrom(Model $buyer, Model $holder): bool
    {
        if ($buyer instanceof Dealer) {
            return $holder instanceof Distributor && (int) $holder->getKey() === (int) $buyer->distributor_id;
        }

        if ($buyer instanceof Distributor) {
            return $holder instanceof Company
                || ($holder instanceof Distributor && (int) $holder->getKey() !== (int) $buyer->getKey());
        }

        return false;
    }

    /**
     * Every holder of a product across the network: the Company and each
     * distributor that has a stock row for it, each with its stock points.
     * When a buyer is given, each holder carries `orderable` and the buyer's
     * default seller is always listed (even with no stock).
     *
     * @return list<array{holder_type: string, holder_id: int, holder_name: string, orderable: bool|null, points: list<array<string, mixed>>}>
     */
    public function holdersForProduct(int $productId, ?Model $buyer = null): array
    {
        $holders = Stock::query()
            ->where('product_id', $productId)
            ->get(['stockable_type', 'stockable_id'])
            ->map(fn (Stock $row): string => $row->stockable_type.'#'.$row->stockable_id)
            ->unique()
            ->values();

        if ($buyer !== null) {
            $default = $this->defaultSeller($buyer, $productId);
            $holders = $holders->prepend($default->getMorphClass().'#'.$default->getKey())->unique()->values();
        }

        $groups = [];
        foreach ($holders as $key) {
            [$type, $id] = explode('#', (string) $key);
            if (! in_array($type, [Company::class, Distributor::class], true)) {
                continue;
            }
            $holder = $type::query()->find((int) $id);
            if (! $holder instanceof Model) {
                continue;
            }

            $groups[] = [
                'holder_type' => $this->shortType($holder),
                'holder_id' => (int) $holder->getKey(),
                'holder_name' => (string) ($holder->getAttribute('name') ?? ''),
                'orderable' => $buyer === null ? null : $this->canBuyFrom($buyer, $holder),
                'points' => $this->pointsFor($holder, $productId),
            ];
        }

        // Company first, then distributors by name.
        usort($groups, fn (array $a, array $b): int => [$a['holder_type'] !== 'company', $a['holder_name']] <=> [$b['holder_type'] !== 'company', $b['holder_name']]);

        return $groups;
    }

    /**
     * One holder's stock points for a product with on-hand / reserved /
     * available. Points with no stock row for the product report zeros.
     *
     * @return list<array{stock_point_id:int, name:string, on_hand:string, reserved:string, available:string}>
     */
    private function pointsFor(Model $holder, int $productId): array
    {
        $points = StockPoint::query()
            ->where('owner_type', $holder->getMorphClass())
            ->where('owner_id', (int) $holder->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name']);

        $stocks = Stock::query()
            ->where('stockable_type', $holder->getMorphClass())
            ->where('stockable_id', (int) $holder->getKey())
            ->where('product_id', $productId)
            ->get()
            ->keyBy('stock_point_id');

        $rows = [];
        foreach ($points as $point) {
            $stock = $stocks->get($point->id);

            $rows[] = [
                'stock_point_id' => (int) $point->id,
                'name' => (string) $point->name,
                'on_hand' => $stock !== null ? (string) $stock->quantity : '0.000',
                'reserved' => $stock !== null ? $this->reservations->reservedQuantity((int) $stock->getKey()) : '0.000',
                'available' => $stock !== null ? $this->reservations->availableQuantity($stock) : '0.000',
            ];
        }

        return $rows;
    }

    public function shortType(Model $party): string
    {
        return match (true) {
            $party instanceof Company => 'company',
            $party instanceof Distributor => 'distributor',
            $party instanceof Dealer => 'dealer',
            default => $party->getMorphClass(),
        };
    }
}
