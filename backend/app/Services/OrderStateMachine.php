<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\IllegalOrderTransitionException;
use App\Models\OrderStatus;

/**
 * The single source of truth for the Order lifecycle (R21.1, R21.4).
 *
 * The whole lifecycle is declared in ONE map, `{ fromState: { event: toState } }`
 * (design "Ordering Domain & Order State Machine"). An event is legal from a
 * state if and only if it appears under that state in the map; the service
 * rejects every event not present for the current state with a 422
 * (R21.4) and otherwise returns the resulting state.
 *
 * Lifecycle (design state diagram):
 *
 *   Draft → Placed → Confirmed → Dispatched → Delivered → Closed
 *
 * with Cancelled reachable from every pre-terminal state (Placed, Confirmed,
 * Dispatched), and a Placed → Placed self-transition for the seller `edit`
 * event. While an Order is Placed the seller may `approve` (→ Confirmed),
 * `reject` (→ Cancelled), or `edit` (→ Placed) before confirmation (R21.2).
 * Closed and Cancelled are terminal — no event is defined from them, so any
 * transition out of a terminal state is rejected (R21.4).
 *
 * The `confirm` event is the alias used by the confirmation pipeline (task 9.7)
 * and `approve` is the equivalent seller approval while Placed (R21.2); both
 * move Placed → Confirmed. Keeping the transition table here — pure, DB-free,
 * and side-effect-free — means the arithmetic of "what state follows what" is
 * verifiable in isolation (Property 10) while the services that perform the
 * stock/ledger side effects simply ask this machine whether a move is legal.
 */
class OrderStateMachine
{
    /*
     * The lifecycle state names are the CANONICAL {@see OrderStatus} values —
     * the exact same strings persisted in the Order model's `status` column by
     * {@see \App\Services\CartSubmissionService} and asserted by the cart tests
     * (lowercase: 'draft', 'placed', …). Keying the transition map on these
     * values is what makes `assertTransition($order->status, …)` resolve for a
     * real persisted order: the machine and the stored state speak ONE
     * vocabulary (R21.1). These aliases are retained so existing callers that
     * reference `OrderStateMachine::PLACED` keep compiling, but they now point
     * at the shared {@see OrderStatus} constants rather than a divergent set.
     */
    public const DRAFT = OrderStatus::DRAFT;

    public const PLACED = OrderStatus::PLACED;

    public const CONFIRMED = OrderStatus::CONFIRMED;

    public const DISPATCHED = OrderStatus::DISPATCHED;

    public const DELIVERED = OrderStatus::DELIVERED;

    public const CLOSED = OrderStatus::CLOSED;

    public const CANCELLED = OrderStatus::CANCELLED;

    /**
     * The declarative transition map: `{ fromState: { event: toState } }`.
     *
     * A state absent from a nested map (or an event absent under a present
     * state) means the transition is undefined and therefore illegal (R21.4).
     * Closed and Cancelled are intentionally absent — they are terminal.
     *
     * @var array<string, array<string, string>>
     */
    private const TRANSITIONS = [
        self::DRAFT => [
            // Submitting the cart places the order (R20.4, R21.1).
            'submit' => self::PLACED,
        ],
        self::PLACED => [
            // Seller approve/confirm while Placed → Confirmed (R21.2, R21.3).
            'approve' => self::CONFIRMED,
            'confirm' => self::CONFIRMED,
            // Seller reject while Placed → Cancelled (R21.2).
            'reject' => self::CANCELLED,
            // Seller edit while Placed keeps it Placed (R21.2).
            'edit' => self::PLACED,
            // Buyer/seller may cancel a not-yet-confirmed order (R21.1).
            'cancel' => self::CANCELLED,
        ],
        self::CONFIRMED => [
            'dispatch' => self::DISPATCHED,
            // Cancel after confirmation reverses stock + account (R18.2, R22.1).
            'cancel' => self::CANCELLED,
        ],
        self::DISPATCHED => [
            'deliver' => self::DELIVERED,
            // Cancel while in transit reverses stock + account (R18.2, R22.1).
            'cancel' => self::CANCELLED,
        ],
        self::DELIVERED => [
            'close' => self::CLOSED,
        ],
        // Closed and Cancelled are terminal: no outgoing transitions (R21.4).
    ];

    /**
     * All seven Order lifecycle states (R21.1).
     *
     * @return list<string>
     */
    public static function states(): array
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
     * The full transition map `{ fromState: { event: toState } }` (R21.4).
     *
     * Exposed read-only so callers/tests can inspect the lifecycle without
     * being able to mutate it.
     *
     * @return array<string, array<string, string>>
     */
    public static function transitions(): array
    {
        return self::TRANSITIONS;
    }

    /**
     * Whether `$event` is a legal transition from `$fromState` — i.e. the event
     * is defined for that state in the transition map (R21.4).
     */
    public function canTransition(string $fromState, string $event): bool
    {
        return isset(self::TRANSITIONS[$fromState][$event]);
    }

    /**
     * Resolve the state that results from applying `$event` to `$fromState`
     * (R21.4). Returns `null` when the transition is undefined; callers that
     * need the state to exist should use {@see assertTransition()} instead.
     */
    public function resolve(string $fromState, string $event): ?string
    {
        return self::TRANSITIONS[$fromState][$event] ?? null;
    }

    /**
     * Return the resulting state for a legal transition, or reject an undefined
     * transition with a 422 (R21.4).
     *
     * This is the single gate every order-state change passes through: the
     * confirmation pipeline (task 9.7) and the transition endpoint (task 9.3)
     * both call this to assert a move is legal BEFORE performing any side
     * effect, guaranteeing an illegal transition leaves the Order unchanged.
     *
     * @throws IllegalOrderTransitionException when the event is not defined for the current state.
     */
    public function assertTransition(string $fromState, string $event): string
    {
        $toState = $this->resolve($fromState, $event);

        if ($toState === null) {
            throw new IllegalOrderTransitionException($fromState, $event);
        }

        return $toState;
    }

    /**
     * The events legally available from `$fromState` (empty for terminal
     * states). Useful for surfacing allowed actions to the client/UI.
     *
     * @return list<string>
     */
    public function availableEvents(string $fromState): array
    {
        return array_keys(self::TRANSITIONS[$fromState] ?? []);
    }
}
