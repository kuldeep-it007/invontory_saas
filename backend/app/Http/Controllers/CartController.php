<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AddCartItemRequest;
use App\Http\Requests\CartBuyerRequest;
use App\Http\Requests\SubmitCartViaCartRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Models\Cart;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Services\CartService;
use App\Services\SellerStockService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

/**
 * Database-backed shopping cart HTTP surface (R20.3, R20.4).
 *
 * Thin controller: validates the payload and delegates all cart logic to
 * {@see CartService}. Every response returns the buyer's current open cart
 * (items with their full price breakdown + aggregate totals), so the frontend
 * always renders from a single authoritative shape. Runs on the tenant
 * pipeline, so every action is confined to the active tenant database.
 */
class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly SellerStockService $sellerStock,
    ) {}

    /**
     * GET /api/cart/sourcing?buyer_type=&buyer_id=&product_id= — the seller's
     * stock points for a product, each with available quantity, so the buyer
     * can split an order line across points (R16, R20).
     */
    public function sourcing(CartBuyerRequest $request): JsonResponse
    {
        $buyer = $this->resolveBuyer($request->validated());
        $productId = (int) $request->query('product_id');

        return response()->json([
            'data' => $this->sellerStock->pointsForBuyerProduct($buyer, $productId),
        ]);
    }

    /**
     * GET /api/cart?buyer_type=&buyer_id= — the buyer's open cart.
     */
    public function show(CartBuyerRequest $request): JsonResponse
    {
        $buyer = $this->resolveBuyer($request->validated());
        $cart = $this->cart->openCartFor($buyer);

        return response()->json($this->present($cart));
    }

    /**
     * POST /api/cart/items — add a product (or bump its quantity).
     */
    public function add(AddCartItemRequest $request): JsonResponse
    {
        $data = $request->validated();
        $buyer = $this->resolveBuyer($data);

        $cart = $this->cart->addItem(
            $buyer,
            (int) $data['product_id'],
            $data['quantity'],
            $data['allocations'] ?? null,
        );

        return response()->json($this->present($cart), 201);
    }

    /**
     * PATCH /api/cart/items/{item} — set a line's quantity (0 removes it).
     */
    public function update(UpdateCartItemRequest $request, int $item): JsonResponse
    {
        $data = $request->validated();
        $buyer = $this->resolveBuyer($data);

        $cart = $this->cart->updateItem($buyer, $item, $data['quantity'], $data['allocations'] ?? null);

        return response()->json($this->present($cart));
    }

    /**
     * DELETE /api/cart/items/{item} — remove a line.
     */
    public function remove(CartBuyerRequest $request, int $item): JsonResponse
    {
        $buyer = $this->resolveBuyer($request->validated());
        $cart = $this->cart->removeItem($buyer, $item);

        return response()->json($this->present($cart));
    }

    /**
     * DELETE /api/cart — empty the cart.
     */
    public function clear(CartBuyerRequest $request): JsonResponse
    {
        $buyer = $this->resolveBuyer($request->validated());
        $cart = $this->cart->clear($buyer);

        return response()->json($this->present($cart));
    }

    /**
     * POST /api/cart/submit — turn the cart into an Order (R20.4).
     */
    public function submit(SubmitCartViaCartRequest $request): JsonResponse
    {
        $data = $request->validated();
        $buyer = $this->resolveBuyer($data);

        $order = $this->cart->submit($buyer, $data['order_type'] ?? 'standard');

        return response()->json(['data' => $order->load('lines')], 201);
    }

    /**
     * Shape the cart response: the cart row, its items (each with the product
     * context and resolved breakdown), and the aggregate totals.
     *
     * @return array{data: array<string, mixed>}
     */
    private function present(Cart $cart): array
    {
        return [
            'data' => [
                'id' => $cart->getKey(),
                'status' => $cart->status,
                'items' => $cart->items->map(fn ($item) => [
                    'id' => $item->getKey(),
                    'product_id' => $item->product_id,
                    'product' => $item->product ? [
                        'id' => $item->product->getKey(),
                        'sku' => $item->product->sku,
                        'name' => $item->product->name,
                        'collection' => $item->product->collection?->name,
                        'category' => $item->product->collection?->category?->name,
                        'colour' => $item->product->colour?->name,
                    ] : null,
                    'quantity' => $item->quantity,
                    'allocations' => $item->allocations ?? null,
                    'rrp' => $item->rrp,
                    'discount_pct' => $item->discount_pct,
                    'unit_price' => $item->unit_price,
                    'net_amount' => $item->net_amount,
                    'tax_rate' => $item->tax_rate,
                    'tax_amount' => $item->tax_amount,
                    'total_amount' => $item->total_amount,
                ])->all(),
                'totals' => $this->cart->totals($cart),
            ],
        ];
    }

    /**
     * Resolve the Buyer model from the validated morph type + id (R20.1, R20.2).
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveBuyer(array $data): Model
    {
        return match ($data['buyer_type']) {
            'distributor' => Distributor::query()->findOrFail((int) $data['buyer_id']),
            'dealer' => Dealer::query()->findOrFail((int) $data['buyer_id']),
        };
    }
}
