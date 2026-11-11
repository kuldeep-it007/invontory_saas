<?php

declare(strict_types=1);

use App\Exceptions\IllegalOrderTransitionException;
use App\Services\OrderStateMachine;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Task 9.4 — Property 10: Order state-machine transition legality
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 10: Order state-machine
| transition legality
|
| Validates: Requirements 21.2, 21.4
|
| Property statement (design.md / tasks.md §Property 10):
|   For any (state, event) pair drawn from the full state × event space, the
|   transition is accepted IFF it is defined for that state in the declarative
|   `{ fromState: { event: toState } }` map. A defined transition resolves to
|   exactly the state the map declares; an undefined transition is rejected with
|   a 422 (IllegalOrderTransitionException) and resolves to null — leaving the
|   order unchanged.
|
|   - R21.2 While an Order is Placed the seller may approve/reject/edit before
|           confirmation.
|   - R21.4 Transitions NOT defined in the map for the current state are
|           rejected with a 422; the single declarative map is the source of
|           truth for transition legality.
|
| This is a PURE property of the OrderStateMachine's arithmetic — no database,
| no booted tenant — so it runs in every environment and exercises the entire
| state × event grid, which the example-based Unit\OrderStateMachineTest cannot
| do exhaustively. Generation uses innmind/black-box (NOT hand-rolled) via the
| shared static::forAll(), which draws the mandated minimum of 100 generated
| scenarii per proof and shrinks any counterexample to a minimal failing input.
|
| The reference oracle for "defined-ness" is the map itself, read through the
| read-only OrderStateMachine::transitions() accessor — so the proof compares
| the three public gates (canTransition / resolve / assertTransition) against
| ONE source of truth and against EACH OTHER, catching any divergence between
| them.
*/

/**
 * The full universe of events the generator draws from: every event that is
 * declared anywhere in the transition map, PLUS deliberately-undefined events
 * (unknown verbs and events that are only legal from OTHER states). This makes
 * the generated (state, event) grid cover both legal and illegal moves densely
 * rather than almost always landing on an undefined pair.
 *
 * File-unique name to avoid colliding with any other top-level test helper.
 *
 * @return list<string>
 */
function orderStateMachineEventUniverse(): array
{
    $declared = [];

    foreach (OrderStateMachine::transitions() as $events) {
        foreach (array_keys($events) as $event) {
            $declared[$event] = true;
        }
    }

    // Undefined events: pure noise plus a couple of real-looking-but-wrong ones.
    $undefined = ['bogus', 'teleport', '', 'SUBMIT', 'reopen', 'ship'];

    return array_values(array_unique([...array_keys($declared), ...$undefined]));
}

it('accepts a transition iff it is defined for the current state in the map (R21.4)', function () {
    // Draw a state across the FULL seven-state space (including the terminal
    // closed/cancelled) and an event across the full event universe above, so
    // the generated grid lands on both defined and undefined pairs.
    static::forAll(
        Set::of(...OrderStateMachine::states()),
        Set::of(...orderStateMachineEventUniverse()),
    )->then(function (string $fromState, string $event) {
        $machine = new OrderStateMachine;

        // The single source of truth: is this (state, event) present in the map?
        $map = OrderStateMachine::transitions();
        $isDefined = isset($map[$fromState][$event]);
        $declaredTarget = $map[$fromState][$event] ?? null;

        // (1) canTransition agrees EXACTLY with the declared map (R21.4).
        expect($machine->canTransition($fromState, $event))->toBe($isDefined);

        // (2) resolve returns the mapped target for a defined transition and
        //     null for an undefined one (R21.4).
        expect($machine->resolve($fromState, $event))->toBe($declaredTarget);

        if ($isDefined) {
            // (3a) DEFINED → assertTransition returns the mapped target state
            //      and never throws (R21.2/R21.4). canTransition/resolve/assert
            //      must all agree.
            $result = $machine->assertTransition($fromState, $event);
            expect($result)->toBe($declaredTarget);
            expect($result)->toBeIn(OrderStateMachine::states());

            return;
        }

        // (3b) UNDEFINED → assertTransition rejects with a 422 and leaves no
        //      resolved state (R21.4). The exception carries the exact
        //      (fromState, event) that was refused, proving the order would be
        //      left unchanged (nothing resolved, nothing to apply).
        $threw = null;

        try {
            $machine->assertTransition($fromState, $event);
        } catch (IllegalOrderTransitionException $e) {
            $threw = $e;
        }

        expect($threw)->not->toBeNull();
        expect($threw->getStatusCode())->toBe(422);
        expect($threw->fromState)->toBe($fromState);
        expect($threw->event)->toBe($event);

        // And the undefined move resolves to null — there is no target state to
        // move the order to, so the order is unchanged.
        expect($machine->resolve($fromState, $event))->toBeNull();
    });
});

it('never permits any outgoing transition from a terminal state (R21.4)', function () {
    // Closed and Cancelled are terminal: for EVERY event in the universe the
    // machine must refuse the move with a 422 and resolve to null, so an order
    // in a terminal state can never change state again.
    static::forAll(
        Set::of(OrderStateMachine::CLOSED, OrderStateMachine::CANCELLED),
        Set::of(...orderStateMachineEventUniverse()),
    )->then(function (string $terminalState, string $event) {
        $machine = new OrderStateMachine;

        expect($machine->canTransition($terminalState, $event))->toBeFalse();
        expect($machine->resolve($terminalState, $event))->toBeNull();
        expect($machine->availableEvents($terminalState))->toBe([]);

        $threw = null;

        try {
            $machine->assertTransition($terminalState, $event);
        } catch (IllegalOrderTransitionException $e) {
            $threw = $e;
        }

        expect($threw)->not->toBeNull();
        expect($threw->getStatusCode())->toBe(422);
    });
});

it('lets the seller approve, reject, or edit from Placed and refuses those same events elsewhere (R21.2)', function () {
    // The three seller moves are legal ONLY while Placed (R21.2). From any
    // other state the identical event must be rejected with a 422 — proving the
    // approve/reject/edit window is confined to Placed by the map, not by the
    // caller.
    static::forAll(
        Set::of(...OrderStateMachine::states()),
        Set::of('approve', 'reject', 'edit'),
    )->then(function (string $fromState, string $sellerEvent) {
        $machine = new OrderStateMachine;

        $expectedTargets = [
            'approve' => OrderStateMachine::CONFIRMED,
            'reject' => OrderStateMachine::CANCELLED,
            'edit' => OrderStateMachine::PLACED,
        ];

        if ($fromState === OrderStateMachine::PLACED) {
            expect($machine->canTransition($fromState, $sellerEvent))->toBeTrue();
            expect($machine->assertTransition($fromState, $sellerEvent))
                ->toBe($expectedTargets[$sellerEvent]);

            return;
        }

        expect($machine->canTransition($fromState, $sellerEvent))->toBeFalse();
        expect($machine->resolve($fromState, $sellerEvent))->toBeNull();

        $threw = null;

        try {
            $machine->assertTransition($fromState, $sellerEvent);
        } catch (IllegalOrderTransitionException $e) {
            $threw = $e;
        }

        expect($threw)->not->toBeNull();
        expect($threw->getStatusCode())->toBe(422);
    });
});
