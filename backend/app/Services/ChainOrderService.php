<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Stock;
use App\Models\StockPoint;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Chain orders: a dealer buying stock its distributor does not hold.
 *
 *   Order 1 (SO-…): dealer → its distributor, every line of the cart;
 *   Order 2 (PO-…): distributor → the stock holder, only the chained quantity.
 *
 * Both share a `chain_ref`; Order 2 points at Order 1 via `parent_order_id`.
 * A line is chained when the dealer picked another holder's stock (the whole
 * line), or when the distributor's own stock falls short (only the missing
 * quantity, bought from the product's default seller). Order 2's stock is
 * reserved at the holder when it is placed.
 *
 * Lifecycle rules:
 *   - Order 1 can be confirmed only once every Order 2 is confirmed (the
 *     chained stock has reached the distributor), and dispatched only once
 *     every Order 2 is delivered.
 *   - Cancelling Order 1 cancels each Order 2 that is not dispatched yet.
 *   - Confirming Order 2 releases its reservation (the stock has moved).
 */
class ChainOrderService
{
    /** Order 2 states that still hold Order 1 back from confirmation. */
    private const AWAITING_CONFIRMATION = [OrderStatus::DRAFT, OrderStatus::PLACED];

    /** Order 2 states that still hold Order 1 back from dispatch. */
    private const AWAITING_DELIVERY = [OrderStatus::DRAFT, OrderStatus::PLACED, OrderStatus::CONFIRMED, OrderStatus::DISPATCHED];

    public function __construct(
        private readonly SellerStockService $stock,
        private readonly StockReservationService $reservations,
    ) {}

    /**
     * Split a dealer's cart entries into Order 1 entries and upstream legs.
     *
     * @param  list<array{product_id: int, quantity: string, allocations: array<int, array<string, mixed>>|null, source: Model|null}>  $entries
     * @return array{order: list<array<string, mixed>>, legs: array<string, array{source: Model, entries: list<array<string, mixed>>}>}
     */
    public function plan(Distributor $distributor, array $entries): array
    {
        $orderEntries = [];
        $legs = [];

        foreach ($entries as $entry) {
            $productId = (int) $entry['product_id'];
            $quantity = (float) $entry['quantity'];
            $source = $entry['source'] ?? null;
            $allocations = $entry['allocations'] ?? null;

            if ($source !== null) {
                // The dealer picked another holder's stock: chain the whole line.
                $this->addLeg($legs, $source, $productId, $quantity, $allocations);
                $orderEntries[] = $this->orderEntry($entry, null);

                continue;
            }

            if ($allocations !== null && $allocations !== []) {
                // Explicitly sourced from the distributor's own points.
                $orderEntries[] = $this->orderEntry($entry, $allocations);

                continue;
            }

            [$own, $shortfall] = $this->takeFrom($distributor, $productId, $quantity);
            $upstream = $this->stock->defaultSeller($distributor, $productId);

            if ($shortfall <= 0.0005 || $upstream->is($distributor)) {
                $orderEntries[] = $this->orderEntry($entry, null);

                continue;
            }

            // Chain only the missing quantity. It arrives at the distributor's
            // primary point, so Order 1 takes it from there.
            $primary = $this->primaryPointId($distributor);
            if ($primary !== null) {
                $own[$primary] = ($own[$primary] ?? 0.0) + $shortfall;
            }
            $this->addLeg($legs, $upstream, $productId, $shortfall, null);
            $orderEntries[] = $this->orderEntry($entry, $primary === null ? null : $this->asAllocations($own));
        }

        return ['order' => $orderEntries, 'legs' => $legs];
    }

    /**
     * Create the upstream orders (Order 2) for a placed Order 1, link the
     * chain and reserve each leg's stock at its holder.
     *
     * @param  array<string, array{source: Model, entries: list<array<string, mixed>>}>  $legs
     * @return list<Order>
     */
    public function placeUpstream(Order $order, Distributor $distributor, array $legs, string $orderType, CartSubmissionService $submission): array
    {
        if ($legs === []) {
            return [];
        }

        $chainRef = 'CH-'.preg_replace('/\D/', '', (string) $order->order_number);
        $order->forceFill(['chain_ref' => $chainRef])->save();

        $upstream = [];
        foreach ($legs as $leg) {
            $child = $submission->submit($distributor, $leg['entries'], $orderType, $leg['source']);
            $child->forceFill(['chain_ref' => $chainRef, 'parent_order_id' => (int) $order->getKey()])->save();

            foreach ($leg['entries'] as $entry) {
                foreach ($entry['allocations'] ?? [] as $allocation) {
                    $row = Stock::query()
                        ->where('stockable_type', $leg['source']->getMorphClass())
                        ->where('stockable_id', (int) $leg['source']->getKey())
                        ->where('product_id', (int) $entry['product_id'])
                        ->where('stock_point_id', (int) $allocation['stock_point_id'])
                        ->first();
                    if ($row !== null) {
                        $this->reservations->reserve($row, (string) $allocation['quantity'], (int) $child->getKey());
                    }
                }
            }

            $upstream[] = $child;
        }

        return $upstream;
    }

    /**
     * Reject moving Order 1 ahead of its upstream orders.
     *
     * @throws UnprocessableEntityHttpException
     */
    public function assertCanTransition(Order $order, string $event): void
    {
        $waitingOn = match ($event) {
            'confirm', 'approve' => self::AWAITING_CONFIRMATION,
            'dispatch' => self::AWAITING_DELIVERY,
            default => null,
        };
        if ($waitingOn === null || ! $order->exists) {
            return;
        }

        $blocking = Order::query()
            ->where('parent_order_id', (int) $order->getKey())
            ->whereIn('status', $waitingOn)
            ->pluck('order_number');

        if ($blocking->isNotEmpty()) {
            throw new UnprocessableEntityHttpException(sprintf(
                $event === 'dispatch'
                    ? 'This order can be dispatched once upstream order %s is delivered.'
                    : 'This order can be confirmed once upstream order %s is confirmed.',
                $blocking->implode(', '),
            ));
        }
    }

    /** After an upstream order is confirmed its stock has moved: release the reservation. */
    public function afterConfirmed(Order $order): void
    {
        if ($order->parent_order_id !== null) {
            $this->reservations->releaseForOrder($order);
        }
    }

    /** Cancel each upstream order of a cancelled Order 1 that is not dispatched yet. */
    public function cascadeCancel(Order $order): void
    {
        $children = Order::query()
            ->where('parent_order_id', (int) $order->getKey())
            ->whereIn('status', [OrderStatus::PLACED, OrderStatus::CONFIRMED])
            ->get();

        foreach ($children as $child) {
            if ($child->status === OrderStatus::CONFIRMED) {
                // Reverses the stock move and the account charge.
                app(OrderCancellationService::class)->cancel($child);

                continue;
            }

            $this->reservations->releaseForOrder($child);
            $child->transition('cancel');
        }
    }

    /**
     * The other orders of this order's chain that a viewer is a party to.
     *
     * @param  array{0: string, 1: int}|null  $viewer  [morph class, id] of the viewing party; null = the Company.
     * @return list<array{id: int, order_number: string|null, status: string, buyer: string, seller: string, role: string}>
     */
    public function relatedFor(Order $order, ?array $viewer): array
    {
        if ($order->chain_ref === null) {
            return [];
        }
        if ($viewer === null) {
            $company = Company::query()->first();
            $viewer = $company === null ? null : [$company->getMorphClass(), (int) $company->getKey()];
        }

        return Order::query()
            ->with(['buyer', 'seller'])
            ->where('chain_ref', $order->chain_ref)
            ->whereKeyNot($order->getKey())
            ->orderBy('id')
            ->get()
            ->filter(fn (Order $o): bool => $viewer !== null && (
                ($o->buyer_type === $viewer[0] && (int) $o->buyer_id === $viewer[1])
                || ($o->seller_type === $viewer[0] && (int) $o->seller_id === $viewer[1])
            ))
            ->map(fn (Order $o): array => [
                'id' => (int) $o->getKey(),
                'order_number' => $o->order_number,
                'status' => (string) $o->status,
                'buyer' => (string) ($o->buyer?->getAttribute('name') ?? ''),
                'seller' => (string) ($o->seller?->getAttribute('name') ?? ''),
                'role' => $o->parent_order_id === null ? 'sales' : 'purchase',
            ])
            ->values()
            ->all();
    }

    /**
     * Take as much as possible from the distributor's own points (primary
     * first). Returns [point id => quantity, shortfall].
     *
     * @return array{0: array<int, float>, 1: float}
     */
    private function takeFrom(Distributor $distributor, int $productId, float $quantity): array
    {
        $taken = [];
        $left = $quantity;
        foreach ($this->stock->pointsFor($distributor, $productId) as $point) {
            if ($left <= 0.0005) {
                break;
            }
            $take = min($left, max(0.0, (float) $point['available']));
            if ($take > 0) {
                $taken[$point['stock_point_id']] = $take;
                $left -= $take;
            }
        }

        return [$taken, max(0.0, $left)];
    }

    /**
     * Add a chained quantity to the leg of its source. Without explicit
     * points the source's points are taken in order (primary first), so the
     * reservation and Order 2's sourcing are concrete.
     *
     * @param  array<string, array{source: Model, entries: list<array<string, mixed>>}>  $legs
     * @param  array<int, array<string, mixed>>|null  $allocations
     */
    private function addLeg(array &$legs, Model $source, int $productId, float $quantity, ?array $allocations): void
    {
        if ($allocations === null || $allocations === []) {
            $taken = [];
            $left = $quantity;
            foreach ($this->stock->pointsFor($source, $productId) as $point) {
                if ($left <= 0.0005) {
                    break;
                }
                $take = min($left, max(0.0, (float) $point['available']));
                if ($take > 0) {
                    $taken[$point['stock_point_id']] = $take;
                    $left -= $take;
                }
            }
            if ($left > 0.0005) {
                throw new UnprocessableEntityHttpException(sprintf(
                    'Not enough stock at %s to supply this order.',
                    (string) ($source->getAttribute('name') ?? 'the supplier'),
                ));
            }
            $allocations = $this->asAllocations($taken);
        }

        $key = $source->getMorphClass().'#'.$source->getKey();
        $legs[$key] ??= ['source' => $source, 'entries' => []];
        $legs[$key]['entries'][] = [
            'product_id' => $productId,
            'quantity' => number_format($quantity, 3, '.', ''),
            'allocations' => $allocations,
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<int, array<string, mixed>>|null  $allocations
     * @return array<string, mixed>
     */
    private function orderEntry(array $entry, ?array $allocations): array
    {
        return [
            'product_id' => (int) $entry['product_id'],
            'quantity' => (string) $entry['quantity'],
            'allocations' => $allocations,
        ];
    }

    /**
     * @param  array<int, float>  $byPoint
     * @return list<array{stock_point_id: int, quantity: string}>
     */
    private function asAllocations(array $byPoint): array
    {
        $out = [];
        foreach ($byPoint as $pointId => $quantity) {
            if ($quantity > 0) {
                $out[] = ['stock_point_id' => (int) $pointId, 'quantity' => number_format($quantity, 3, '.', '')];
            }
        }

        return $out;
    }

    private function primaryPointId(Model $owner): ?int
    {
        $id = StockPoint::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', (int) $owner->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    /** Whether a buyer is a dealer (chain orders start only from dealers). */
    public function startsChain(Model $buyer): bool
    {
        return $buyer instanceof Dealer;
    }
}
