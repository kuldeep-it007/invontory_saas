<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Company;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Database-backed shopping cart (R20.3, R12.4, R12.5).
 *
 * Owns the lifecycle of a Buyer's durable cart: finding/creating the single
 * open cart, adding/updating/removing line items, recomputing each line's full
 * price breakdown (RRP → discount% → discounted unit price → net → tax → total)
 * via {@see PriceResolutionService} + {@see TaxResolutionService}, and
 * submitting the cart into exactly one Order through
 * {@see CartSubmissionService} (R20.4).
 *
 * Every per-line figure mirrors the reference design:
 *
 *   rrp          = resolved marked-up price per unit (pre-discount)
 *   unit_price   = resolved final price per unit (post-discount)
 *   discount_pct = (rrp − unit_price) / rrp × 100   (0 when rrp is 0)
 *   net_amount   = unit_price × quantity
 *   tax_amount   = net_amount × tax_rate / 100
 *   total_amount = net_amount + tax_amount
 *
 * All writes run in a tenant-database transaction so a cart is never left in a
 * partially-updated state.
 */
class CartService
{
    public function __construct(
        private readonly PriceResolutionService $prices,
        private readonly TaxResolutionService $tax,
        private readonly CartSubmissionService $submission,
        private readonly SellerStockService $sellerStock,
        private readonly ChainOrderService $chains,
    ) {}

    /**
     * The Buyer's single open cart, created on first use. Eager-loads items +
     * their products so the caller can render the cart without an N+1.
     */
    public function openCartFor(Model $buyer): Cart
    {
        $this->assertBuyer($buyer);

        $cart = Cart::query()
            ->where('buyer_type', $buyer->getMorphClass())
            ->where('buyer_id', $buyer->getKey())
            ->where('status', Cart::STATUS_OPEN)
            ->latest('id')
            ->first();

        if ($cart === null) {
            $cart = Cart::create([
                'buyer_type' => $buyer->getMorphClass(),
                'buyer_id' => (int) $buyer->getKey(),
                'status' => Cart::STATUS_OPEN,
            ]);
        }

        return $cart->load(['items.product.collection.category', 'items.product.colour', 'items.seller', 'items.source']);
    }

    /**
     * Add a product to the Buyer's open cart, or increment its quantity when it
     * is already present (one line per product). The line's price breakdown is
     * (re)computed for the resulting quantity.
     */
    public function addItem(Model $buyer, int $productId, string|int|float $quantity, ?array $allocations = null): Cart
    {
        $cart = $this->openCartFor($buyer);
        $product = $this->resolveProduct($productId);
        $qty = $this->normalizeQuantity($quantity);

        DB::connection('tenant')->transaction(function () use ($cart, $buyer, $product, $qty, $allocations): void {
            $existing = CartItem::query()
                ->where('cart_id', $cart->getKey())
                ->where('product_id', $product->getKey())
                ->first();

            $newQty = $existing !== null
                ? $this->addQuantities((string) $existing->quantity, $qty)
                : $qty;

            // The seller is the holder of the chosen stock points; a plain add
            // keeps the existing line's seller (or the default seller).
            [$seller, $source] = $this->chooseLine($buyer, $product, $allocations, $existing);

            // Block ordering more than the seller (or the chain source) has
            // available (R16.4, R17.1), unless the tenant permits backorders.
            $this->assertAvailable($buyer, $seller, $source, $product, $newQty);

            // Bumping an existing line: fold the new per-point allocation into
            // the line's current split so the total still equals the line qty.
            // If the existing line has no split, the new one cannot cover the
            // whole quantity and is dropped by normalizeAllocations().
            if ($existing !== null && $allocations !== null && $allocations !== []) {
                $current = is_array($existing->allocations) ? $existing->allocations : [];
                $allocations = $current === [] ? $allocations : array_merge($current, $allocations);
            }

            $this->writeLine($cart, $buyer, $seller, $product, $newQty, $existing, $allocations, $source);
        });

        return $this->openCartFor($buyer);
    }

    /**
     * Set the exact quantity of an existing line, recomputing its breakdown. A
     * non-positive quantity removes the line.
     */
    public function updateItem(Model $buyer, int $itemId, string|int|float $quantity, ?array $allocations = null): Cart
    {
        $cart = $this->openCartFor($buyer);

        DB::connection('tenant')->transaction(function () use ($cart, $buyer, $itemId, $quantity, $allocations): void {
            $item = CartItem::query()
                ->where('cart_id', $cart->getKey())
                ->whereKey($itemId)
                ->first();

            if ($item === null) {
                return;
            }

            if ((float) $quantity <= 0) {
                $item->delete();

                return;
            }

            $product = $this->resolveProduct((int) $item->product_id);
            $qty = $this->normalizeQuantity($quantity);

            // A new split may move the line to another seller; otherwise the
            // line keeps its seller.
            [$seller, $source] = $allocations !== null && $allocations !== []
                ? $this->sellerStock->resolveLine($buyer, (int) $product->getKey(), $this->pointIds($allocations))
                : [$this->lineSeller($buyer, $item), $this->lineSource($item)];

            // Block ordering more than the seller (or the chain source) has
            // available (R16.4, R17.1), unless the tenant permits backorders.
            $this->assertAvailable($buyer, $seller, $source, $product, $qty);

            $this->writeLine($cart, $buyer, $seller, $product, $qty, $item, $allocations, $source);
        });

        return $this->openCartFor($buyer);
    }

    /**
     * Remove a line from the Buyer's open cart.
     */
    public function removeItem(Model $buyer, int $itemId): Cart
    {
        $cart = $this->openCartFor($buyer);

        CartItem::query()
            ->where('cart_id', $cart->getKey())
            ->whereKey($itemId)
            ->delete();

        return $this->openCartFor($buyer);
    }

    /**
     * Empty the Buyer's open cart.
     */
    public function clear(Model $buyer): Cart
    {
        $cart = $this->openCartFor($buyer);

        CartItem::query()->where('cart_id', $cart->getKey())->delete();

        return $this->openCartFor($buyer);
    }

    /**
     * Submit the Buyer's open cart (R20.4): one Order per seller, so a cart
     * mixing Company stock and other distributors' stock becomes one order to
     * each. The cart is then marked `submitted` and linked to the first order.
     * The heavy lifting (quota, order-line pricing, notifications) is reused
     * from {@see CartSubmissionService} so the cart and the direct submit path
     * price identically. All orders are created in one transaction.
     *
     * @return list<Order>
     *
     * @throws InvalidArgumentException When the cart is empty.
     */
    public function submitAll(Model $buyer, string $orderType = 'standard'): array
    {
        $cart = $this->openCartFor($buyer);

        if ($cart->items->isEmpty()) {
            throw new InvalidArgumentException('Cannot submit an empty cart; add at least one item.');
        }

        /** @var array<string, array{seller: Model, entries: list<array<string, mixed>>}> $groups */
        $groups = [];
        foreach ($cart->items as $item) {
            $seller = $this->lineSeller($buyer, $item);
            $key = $seller->getMorphClass().'#'.$seller->getKey();
            $groups[$key] ??= ['seller' => $seller, 'entries' => []];
            $groups[$key]['entries'][] = [
                'product_id' => (int) $item->product_id,
                'quantity' => (string) $item->quantity,
                'allocations' => $item->allocations ?? null,
                'source' => $this->lineSource($item),
            ];
        }

        $orders = DB::connection('tenant')->transaction(function () use ($buyer, $groups, $orderType, $cart): array {
            $orders = [];
            foreach ($groups as $group) {
                if ($buyer instanceof Dealer && $group['seller'] instanceof Distributor) {
                    // Chain orders: what the distributor lacks (or the dealer
                    // took from another holder) is bought upstream.
                    $plan = $this->chains->plan($group['seller'], $group['entries']);
                    $order = $this->submission->submit($buyer, $plan['order'], $orderType, $group['seller']);
                    $this->chains->placeUpstream($order, $group['seller'], $plan['legs'], $orderType, $this->submission);
                    $orders[] = $order->refresh()->load('lines');

                    continue;
                }

                $orders[] = $this->submission->submit($buyer, array_map(
                    fn (array $entry): array => array_diff_key($entry, ['source' => true]),
                    $group['entries'],
                ), $orderType, $group['seller']);
            }

            $cart->status = Cart::STATUS_SUBMITTED;
            $cart->order_id = (int) $orders[0]->getKey();
            $cart->save();

            return $orders;
        });

        return $orders;
    }

    /**
     * Submit the cart and return the first order (single-seller carts make
     * exactly one). See {@see submitAll()}.
     */
    public function submit(Model $buyer, string $orderType = 'standard'): Order
    {
        return $this->submitAll($buyer, $orderType)[0];
    }

    /**
     * Compute the cart totals summary from its lines (R20.3). Returns the
     * aggregate net, tax, and total plus the item count, so the UI can render
     * the cart footer figures from the reference design.
     *
     * @return array{items: int, net_amount: string, tax_amount: string, total_amount: string, currency: string}
     */
    public function totals(Cart $cart): array
    {
        $net = 0.0;
        $tax = 0.0;
        $total = 0.0;

        foreach ($cart->items as $item) {
            $net += (float) $item->net_amount;
            $tax += (float) $item->tax_amount;
            $total += (float) $item->total_amount;
        }

        return [
            'items' => $cart->items->count(),
            'net_amount' => number_format($net, 2, '.', ''),
            'tax_amount' => number_format($tax, 2, '.', ''),
            'total_amount' => number_format($total, 2, '.', ''),
            'currency' => $this->prices->currency(),
        ];
    }

    /* ----------------------------------------------------------------------
     | Internals
     * -------------------------------------------------------------------- */

    /**
     * Resolve the full price breakdown for a product + quantity and persist it
     * onto the cart line (creating or updating). The breakdown reuses the
     * shared {@see PriceResolutionService} so cart and order price identically.
     */
    private function writeLine(Cart $cart, Model $buyer, Model $seller, Product $product, string $quantity, ?CartItem $existing, ?array $allocations = null, ?Model $source = null): CartItem
    {
        $pricingBuyerType = $buyer instanceof Distributor ? 'distributor' : 'dealer';

        $resolved = $this->prices->resolveForProduct(
            $product,
            $pricingBuyerType,
            (int) $buyer->getKey(),
            $seller instanceof Distributor ? (int) $seller->getKey() : null,
        );

        $rrp = round($resolved->markedUp, 2);
        $unitPrice = round($resolved->final, 2);
        $discountPct = $rrp > 0 ? round((($rrp - $unitPrice) / $rrp) * 100, 4) : 0.0;

        $qtyFloat = (float) $quantity;
        $netAmount = round($unitPrice * $qtyFloat, 2);

        $taxRate = $this->tax->rateForProduct($product);
        $taxAmount = round($netAmount * ($taxRate / 100.0), 2);
        $totalAmount = round($netAmount + $taxAmount, 2);

        $attributes = [
            'seller_type' => $seller->getMorphClass(),
            'seller_id' => (int) $seller->getKey(),
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source === null ? null : (int) $source->getKey(),
            'quantity' => $quantity,
            'rrp' => $rrp,
            'discount_pct' => $discountPct,
            'unit_price' => $unitPrice,
            'net_amount' => $netAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
        ];

        // Persist a per-stock-point source split when provided and consistent
        // with the line quantity; otherwise clear it (source from primary).
        $attributes['allocations'] = $this->normalizeAllocations($allocations, $quantity);

        if ($existing !== null) {
            $existing->fill($attributes);
            $existing->save();

            return $existing;
        }

        return CartItem::create(array_merge($attributes, [
            'cart_id' => (int) $cart->getKey(),
            'product_id' => (int) $product->getKey(),
        ]));
    }

    private function assertBuyer(Model $buyer): void
    {
        if (! $buyer instanceof Distributor && ! $buyer instanceof Dealer) {
            throw new InvalidArgumentException(sprintf(
                'A cart buyer must be a Distributor or Dealer, got [%s].',
                $buyer::class,
            ));
        }
    }

    private function resolveProduct(int $productId): Product
    {
        $product = Product::query()->with('collection')->find($productId);

        if ($product === null) {
            throw new InvalidArgumentException(sprintf('Unknown product id [%d].', $productId));
        }

        return $product;
    }

    /**
     * Reject a cart line whose quantity exceeds what the line's SELLER has
     * available for the product across all its stock points (R16.4, R17.1),
     * unless the tenant permits backorders. Throws a 422
     * {@see InsufficientAvailableStockException} naming the requested vs
     * available quantities.
     */
    private function assertAvailable(Model $buyer, Model $seller, ?Model $source, Product $product, string $requestedQty): void
    {
        if ($this->backordersEnabled()) {
            return;
        }

        $productId = (int) $product->getKey();
        $available = match (true) {
            // Picked another holder's stock: it must be there.
            $source !== null => $this->sellerStock->totalAvailableAt($source, $productId),
            // A dealer gets the distributor's stock plus what it can buy upstream.
            $buyer instanceof Dealer => $this->sellerStock->totalAvailableForDealer($buyer, $productId),
            default => $this->sellerStock->totalAvailableAt($seller, $productId),
        };
        $requested = (float) $requestedQty;

        if ($requested > $available + 0.0005) {
            throw new UnprocessableEntityHttpException(sprintf(
                'Only %s of "%s" is available — you requested %s.',
                rtrim(rtrim(number_format($available, 3, '.', ''), '0'), '.'),
                $product->name,
                rtrim(rtrim($requestedQty, '0'), '.'),
            ));
        }
    }

    /**
     * [seller, chain source] for an add: from the chosen points, else the
     * existing line's, else the default seller with no source. A line has one
     * seller and one source, so choosing another holder's points for a
     * product already in the cart is rejected.
     *
     * @param  array<int, array{stock_point_id?: mixed, quantity?: mixed}>|null  $allocations
     * @return array{0: Model, 1: Model|null}
     */
    private function chooseLine(Model $buyer, Product $product, ?array $allocations, ?CartItem $existing): array
    {
        $current = $existing !== null ? [$this->lineSeller($buyer, $existing), $this->lineSource($existing)] : null;
        if ($allocations === null || $allocations === []) {
            return $current ?? [$this->sellerStock->defaultSeller($buyer, (int) $product->getKey()), null];
        }

        $line = $this->sellerStock->resolveLine($buyer, (int) $product->getKey(), $this->pointIds($allocations));
        $sameSource = ($current[1] ?? null) === null ? $line[1] === null : ($line[1] !== null && $current[1]->is($line[1]));
        if ($current !== null && (! $current[0]->is($line[0]) || ! $sameSource)) {
            throw new UnprocessableEntityHttpException(sprintf(
                '"%s" is already in the cart from another seller. Remove that line first.',
                $product->name,
            ));
        }

        return $line;
    }

    /** The upstream holder a dealer picked for a line (chain source), if any. */
    private function lineSource(CartItem $item): ?Model
    {
        $type = $item->source_type;
        if ($type === null || $item->source_id === null || ! in_array($type, [Company::class, Distributor::class], true)) {
            return null;
        }

        $source = $type::query()->find((int) $item->source_id);

        return $source instanceof Model ? $source : null;
    }

    /** A cart line's seller; lines saved before sellers were recorded use the default. */
    private function lineSeller(Model $buyer, CartItem $item): Model
    {
        $type = $item->seller_type;
        if ($type !== null && $item->seller_id !== null && in_array($type, [Company::class, Distributor::class], true)) {
            $seller = $type::query()->find((int) $item->seller_id);
            if ($seller instanceof Model) {
                return $seller;
            }
        }

        return $this->sellerStock->defaultSeller($buyer, (int) $item->product_id);
    }

    /**
     * @param  array<int, array{stock_point_id?: mixed, quantity?: mixed}>  $allocations
     * @return list<int>
     */
    private function pointIds(array $allocations): array
    {
        return array_values(array_unique(array_filter(
            array_map(fn (array $entry): int => (int) ($entry['stock_point_id'] ?? 0), $allocations),
            fn (int $id): bool => $id > 0,
        )));
    }

    /**
     * Whether the active tenant permits backorders (ordering beyond available).
     * Defaults to false (strict availability) outside a booted tenant context.
     */
    private function backordersEnabled(): bool
    {
        if (! function_exists('tenant')) {
            return false;
        }

        $tenant = tenant();

        return $tenant instanceof Tenant ? (bool) $tenant->backorders_enabled : false;
    }

    /**
     * Normalize a quantity to a positive DECIMAL(15,3) string.
     */
    private function normalizeQuantity(string|int|float $quantity): string
    {
        $value = (float) $quantity;

        if ($value <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        return number_format($value, 3, '.', '');
    }

    /**
     * Add two DECIMAL(15,3) quantity strings exactly.
     */
    private function addQuantities(string $a, string $b): string
    {
        if (function_exists('bcadd')) {
            return bcadd($a, $b, 3);
        }

        return number_format((float) $a + (float) $b, 3, '.', '');
    }

    /**
     * Normalize an optional per-stock-point allocation list to a clean array of
     * `{stock_point_id, quantity}` entries, dropping zero/invalid rows. Returns
     * null when there is no usable split OR when the split does not sum to the
     * line quantity (in which case the line sources from the primary point),
     * keeping the stored data trustworthy without failing the cart update.
     *
     * @param  array<int, array{stock_point_id?: mixed, quantity?: mixed}>|null  $allocations
     * @return list<array{stock_point_id: int, quantity: string}>|null
     */
    private function normalizeAllocations(?array $allocations, string $lineQuantity): ?array
    {
        if ($allocations === null || $allocations === []) {
            return null;
        }

        $clean = [];
        $sum = 0.0;

        foreach ($allocations as $entry) {
            $pointId = (int) ($entry['stock_point_id'] ?? 0);
            $qty = (float) ($entry['quantity'] ?? 0);

            if ($pointId <= 0 || $qty <= 0) {
                continue;
            }

            // Merge repeated stock points into a single entry.
            $merged = ((float) ($clean[$pointId]['quantity'] ?? 0)) + $qty;
            $clean[$pointId] = [
                'stock_point_id' => $pointId,
                'quantity' => number_format($merged, 3, '.', ''),
            ];
            $sum += $qty;
        }

        if ($clean === []) {
            return null;
        }

        $clean = array_values($clean);

        // Only honour a split that exactly accounts for the whole line quantity;
        // otherwise fall back to primary-point sourcing so stock maths stay sound.
        if (abs($sum - (float) $lineQuantity) > 0.0005) {
            return null;
        }

        return $clean;
    }
}
