<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
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

        return $cart->load(['items.product.collection.category', 'items.product.colour']);
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

            // Block ordering more than the seller has available (R16.4, R17.1),
            // unless the tenant permits backorders.
            $this->assertAvailable($buyer, $product, $newQty);

            // Bumping an existing line: fold the new per-point allocation into
            // the line's current split so the total still equals the line qty.
            // If the existing line has no split, the new one cannot cover the
            // whole quantity and is dropped by normalizeAllocations().
            if ($existing !== null && $allocations !== null && $allocations !== []) {
                $current = is_array($existing->allocations) ? $existing->allocations : [];
                $allocations = $current === [] ? $allocations : array_merge($current, $allocations);
            }

            $this->writeLine($cart, $buyer, $product, $newQty, $existing, $allocations);
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

            // Block ordering more than the seller has available (R16.4, R17.1),
            // unless the tenant permits backorders.
            $this->assertAvailable($buyer, $product, $qty);

            $this->writeLine($cart, $buyer, $product, $qty, $item, $allocations);
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
     * Submit the Buyer's open cart into exactly one Order (R20.4), then mark the
     * cart `submitted` and link it to the created order. The heavy lifting
     * (quota, seller resolution, order-line pricing, notifications) is reused
     * from {@see CartSubmissionService} so the cart and the direct submit path
     * price identically.
     *
     * @throws InvalidArgumentException When the cart is empty.
     */
    public function submit(Model $buyer, string $orderType = 'standard'): Order
    {
        $cart = $this->openCartFor($buyer);

        if ($cart->items->isEmpty()) {
            throw new InvalidArgumentException('Cannot submit an empty cart; add at least one item.');
        }

        $entries = $cart->items
            ->map(fn (CartItem $item): array => [
                'product_id' => (int) $item->product_id,
                'quantity' => (string) $item->quantity,
                'allocations' => $item->allocations ?? null,
            ])
            ->all();

        $order = $this->submission->submit($buyer, $entries, $orderType);

        $cart->status = Cart::STATUS_SUBMITTED;
        $cart->order_id = (int) $order->getKey();
        $cart->save();

        return $order;
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
    private function writeLine(Cart $cart, Model $buyer, Product $product, string $quantity, ?CartItem $existing, ?array $allocations = null): CartItem
    {
        $pricingBuyerType = $buyer instanceof Distributor ? 'distributor' : 'dealer';

        $resolved = $this->prices->resolveForProduct($product, $pricingBuyerType, (int) $buyer->getKey());

        $rrp = round($resolved->markedUp, 2);
        $unitPrice = round($resolved->final, 2);
        $discountPct = $rrp > 0 ? round((($rrp - $unitPrice) / $rrp) * 100, 4) : 0.0;

        $qtyFloat = (float) $quantity;
        $netAmount = round($unitPrice * $qtyFloat, 2);

        $taxRate = $this->tax->rateForProduct($product);
        $taxAmount = round($netAmount * ($taxRate / 100.0), 2);
        $totalAmount = round($netAmount + $taxAmount, 2);

        $attributes = [
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
     * Reject a cart line whose quantity exceeds what the buyer's SELLER has
     * available for the product across all its stock points (R16.4, R17.1),
     * unless the tenant permits backorders. Throws a 422
     * {@see InsufficientAvailableStockException} naming the requested vs
     * available quantities.
     */
    private function assertAvailable(Model $buyer, Product $product, string $requestedQty): void
    {
        if ($this->backordersEnabled()) {
            return;
        }

        $available = $this->sellerStock->totalAvailableForBuyerProduct($buyer, (int) $product->getKey());
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
