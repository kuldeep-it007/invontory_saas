<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Canonical Order lifecycle states (R21.1).
 *
 * The Platform supports the lifecycle Draft → Placed → Confirmed → Dispatched
 * → Delivered → Closed, with Cancelled reachable from pre-terminal states
 * (R21.1). These constants are the single source of truth for the state names
 * consumed by the {@see Order} model's `status` column and by the declarative
 * `OrderStateMachine` transition map (task 9.3). Statuses are stored as strings
 * so new states or transitions can be added without a schema change.
 */
final class OrderStatus
{
    public const DRAFT = 'draft';

    public const PLACED = 'placed';

    public const CONFIRMED = 'confirmed';

    public const DISPATCHED = 'dispatched';

    public const DELIVERED = 'delivered';

    public const CLOSED = 'closed';

    public const CANCELLED = 'cancelled';

    /**
     * The complete set of lifecycle states (R21.1), in forward order with the
     * terminal Cancelled state last.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::DRAFT,
            self::PLACED,
            self::CONFIRMED,
            self::DISPATCHED,
            self::DELIVERED,
            self::CLOSED,
            self::CANCELLED,
        ];
    }

    /**
     * Whether a value is a recognised lifecycle state (R21.1).
     */
    public static function isValid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }
}
