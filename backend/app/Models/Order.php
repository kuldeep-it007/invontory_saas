<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantAuditable;
use App\Services\OrderStateMachine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Tenant-database Order model (R20, R21).
 *
 * A purchase placed by a polymorphic Buyer (Distributor or Dealer) against a
 * polymorphic Seller (Company or Distributor) carrying one or more Order_Lines
 * (R20.1, R20.2). The `status` column holds the current lifecycle state and
 * covers the whole set Draft, Placed, Confirmed, Dispatched, Delivered, Closed,
 * Cancelled (R21.1).
 *
 * The Order never decides its own legal moves: {@see OrderStateMachine} owns
 * the declarative transition map, and {@see transition()} asks it to resolve
 * the next state (rejecting an undefined move with a 422 and leaving the Order
 * unchanged — R21.4). This keeps the lifecycle rules in one testable place and
 * the model a thin record of the current state.
 *
 * Tenant-owned and physically isolated (no `tenant_id`); {@see TenantAuditable}
 * records create/update/delete — including status changes — in the tenant
 * `audit_logs` table (R34.1).
 *
 * @property int $id
 * @property string|null $order_number
 * @property string $buyer_type
 * @property int $buyer_id
 * @property string $seller_type
 * @property int $seller_id
 * @property string $status
 * @property string $order_type
 * @property string $total
 */
class Order extends Model implements Auditable
{
    use TenantAuditable;

    /**
     * A standard order with no reservation/scheduling semantics (R20).
     */
    public const TYPE_STANDARD = 'standard';

    /**
     * A blanket order defining scheduled releases (R23.1).
     */
    public const TYPE_BLANKET = 'blanket';

    /**
     * An advance order placed against future/blocked stock with a reservation,
     * gated by the `advance_orders` feature flag (R23.2).
     */
    public const TYPE_ADVANCE = 'advance';

    /**
     * A depot order/invoice recorded at the stock-point level (R23.3).
     */
    public const TYPE_DEPOT = 'depot';

    /**
     * The supported order types (R20, R23). `order_type` is stored as a string
     * so new types need no migration; this is the documented allow-list.
     *
     * @var list<string>
     */
    public const ORDER_TYPES = [
        self::TYPE_STANDARD,
        self::TYPE_BLANKET,
        self::TYPE_ADVANCE,
        self::TYPE_DEPOT,
    ];

    /**
     * Mass-assignable attributes. The polymorphic buyer/seller may also be set
     * through the {@see buyer()} / {@see seller()} relations.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_number',
        'buyer_type',
        'buyer_id',
        'seller_type',
        'seller_id',
        'status',
        'order_type',
        'total',
    ];

    protected $casts = [
        'buyer_id' => 'integer',
        'seller_id' => 'integer',
        'total' => 'decimal:2',
    ];

    /**
     * The fixed base offset for the human-facing {@see $order_number}. Adding it
     * to the auto-increment id yields a value that is always at least 8 digits
     * (id 1 → 10000001) and inherently unique, so the business identifier never
     * collides and never shrinks below the required minimum width (R20).
     */
    private const ORDER_NUMBER_BASE = 10_000_000;

    /**
     * Assign the unique, ≥8-digit {@see $order_number} right after the order is
     * inserted (the id exists only post-insert). Derived deterministically from
     * the id so it is unique without a lookup; persisted with a direct update so
     * the model's audit trail still records a single create (R20, R34.1).
     */
    protected static function booted(): void
    {
        static::created(function (Order $order): void {
            if ($order->order_number === null || $order->order_number === '') {
                $number = (string) (self::ORDER_NUMBER_BASE + (int) $order->getKey());
                $order->order_number = $number;
                // Save only the generated column to avoid re-triggering side effects.
                static::query()->whereKey($order->getKey())->update(['order_number' => $number]);
            }
        });
    }

    /**
     * The polymorphic Buyer that placed the order: a {@see Distributor} buying
     * from the Company, or a {@see Dealer} buying from its Distributor (R20.1,
     * R20.2).
     */
    public function buyer(): MorphTo
    {
        return $this->morphTo('buyer');
    }

    /**
     * The polymorphic Seller the order is placed against: the {@see Company}
     * (for a Distributor order) or a {@see Distributor} (for a Dealer order)
     * (R20.1, R20.2).
     */
    public function seller(): MorphTo
    {
        return $this->morphTo('seller');
    }

    /**
     * The order's lines — one per cart entry at submission (R20.4). Eager-loaded
     * via the `order_id` index to render an order without an N+1 (R38.1).
     *
     * @return HasMany<OrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    /**
     * Whether `$event` is a legal transition from this order's current status
     * per the {@see OrderStateMachine} transition map (R21.4).
     */
    public function canTransition(string $event, ?OrderStateMachine $machine = null): bool
    {
        return ($machine ?? new OrderStateMachine)->canTransition($this->status, $event);
    }

    /**
     * Apply a lifecycle `$event` to this order via the {@see OrderStateMachine}
     * (R21.2, R21.4) and persist the resulting status.
     *
     * The machine asserts the move is legal BEFORE anything changes: an
     * undefined transition throws a 422
     * {@see \App\Exceptions\IllegalOrderTransitionException} and the order is
     * left untouched (R21.4). A legal move updates `status` to the resolved
     * state and saves (the change is captured by the audit trail — R34.1).
     *
     * This performs ONLY the state change. Side effects that accompany certain
     * transitions (stock movement + invoice on confirm, reversal on cancel) are
     * orchestrated by the dedicated services (OrderConfirmationService, etc.)
     * which call the same machine to assert legality first.
     *
     * @return string the new status after a legal transition.
     */
    public function transition(string $event, ?OrderStateMachine $machine = null): string
    {
        $machine ??= new OrderStateMachine;

        $this->status = $machine->assertTransition($this->status, $event);
        $this->save();

        return $this->status;
    }
}
