<?php

declare(strict_types=1);

use App\Exceptions\IllegalOrderTransitionException;
use App\Services\OrderStateMachine;

/*
|--------------------------------------------------------------------------
| Task 9.3 — OrderStateMachine declarative transition map (no DB)
|--------------------------------------------------------------------------
|
| Pure-logic checks for the single `{ fromState: { event: toState } }` map that
| governs the Order lifecycle (R21.1, R21.2, R21.4). These verify the lifecycle
| path Draft → Placed → Confirmed → Dispatched → Delivered → Closed, Cancelled
| reachable from every pre-terminal state, the seller approve/reject/edit moves
| while Placed, and that undefined transitions are rejected with a 422 leaving
| the resolution unmapped. The exhaustive "accepted iff defined" property is the
| separate PBT task 9.4 (Property 10).
|
*/

beforeEach(function () {
    $this->machine = new OrderStateMachine;
});

it('declares all seven lifecycle states as the canonical OrderStatus values (R21.1)', function () {
    // The machine speaks the SAME lowercase vocabulary the Order model persists
    // (OrderStatus), so a persisted order's status resolves in the map.
    expect(OrderStateMachine::states())->toEqual([
        'draft', 'placed', 'confirmed', 'dispatched', 'delivered', 'closed', 'cancelled',
    ]);
});

it('walks the happy-path lifecycle draft → placed → confirmed → dispatched → delivered → closed (R21.1)', function () {
    $m = $this->machine;

    expect($m->resolve('draft', 'submit'))->toBe('placed');
    expect($m->resolve('placed', 'confirm'))->toBe('confirmed');
    expect($m->resolve('confirmed', 'dispatch'))->toBe('dispatched');
    expect($m->resolve('dispatched', 'deliver'))->toBe('delivered');
    expect($m->resolve('delivered', 'close'))->toBe('closed');
});

it('permits the seller to approve, reject, or edit while placed (R21.2)', function () {
    $m = $this->machine;

    // approve → confirmed, reject → cancelled, edit → stays placed.
    expect($m->resolve('placed', 'approve'))->toBe('confirmed');
    expect($m->resolve('placed', 'reject'))->toBe('cancelled');
    expect($m->resolve('placed', 'edit'))->toBe('placed');

    expect($m->canTransition('placed', 'approve'))->toBeTrue();
    expect($m->canTransition('placed', 'reject'))->toBeTrue();
    expect($m->canTransition('placed', 'edit'))->toBeTrue();
});

it('reaches cancelled from every pre-terminal state (R21.1)', function () {
    $m = $this->machine;

    expect($m->resolve('placed', 'cancel'))->toBe('cancelled');
    expect($m->resolve('confirmed', 'cancel'))->toBe('cancelled');
    expect($m->resolve('dispatched', 'cancel'))->toBe('cancelled');
});

it('treats closed and cancelled as terminal with no outgoing transitions (R21.4)', function () {
    $m = $this->machine;

    expect($m->availableEvents('closed'))->toBe([]);
    expect($m->availableEvents('cancelled'))->toBe([]);

    expect($m->canTransition('closed', 'cancel'))->toBeFalse();
    expect($m->canTransition('cancelled', 'submit'))->toBeFalse();
});

it('resolves undefined transitions to null and reports them as not transitionable (R21.4)', function () {
    $m = $this->machine;

    // Skipping states / wrong event for the state is undefined.
    expect($m->resolve('draft', 'confirm'))->toBeNull();
    expect($m->resolve('confirmed', 'deliver'))->toBeNull();
    expect($m->resolve('delivered', 'cancel'))->toBeNull();
    expect($m->resolve('placed', 'bogus'))->toBeNull();

    expect($m->canTransition('draft', 'confirm'))->toBeFalse();
});

it('asserts a legal transition and returns the resulting state (R21.4)', function () {
    expect($this->machine->assertTransition('placed', 'confirm'))->toBe('confirmed');
});

it('rejects an undefined transition with a 422 IllegalOrderTransitionException (R21.4)', function () {
    $threw = null;

    try {
        $this->machine->assertTransition('draft', 'confirm');
    } catch (IllegalOrderTransitionException $e) {
        $threw = $e;
    }

    expect($threw)->not->toBeNull();
    expect($threw->getStatusCode())->toBe(422);
    expect($threw->fromState)->toBe('draft');
    expect($threw->event)->toBe('confirm');
});

it('lists the events available from each state (R21.2)', function () {
    $m = $this->machine;

    expect($m->availableEvents('draft'))->toBe(['submit']);
    expect($m->availableEvents('placed'))->toBe(['approve', 'confirm', 'reject', 'edit', 'cancel']);
    expect($m->availableEvents('confirmed'))->toBe(['dispatch', 'cancel']);
    expect($m->availableEvents('dispatched'))->toBe(['deliver', 'cancel']);
    expect($m->availableEvents('delivered'))->toBe(['close']);
});

it('exposes the transition map read-only without any terminal-state key (R21.4)', function () {
    $map = OrderStateMachine::transitions();

    expect($map)->toHaveKeys(['draft', 'placed', 'confirmed', 'dispatched', 'delivered']);
    expect($map)->not->toHaveKey('closed');
    expect($map)->not->toHaveKey('cancelled');
});

it('aligns its constants with the canonical OrderStatus vocabulary (R21.1)', function () {
    // The reconciliation guarantee: the machine's state constants ARE the
    // OrderStatus values persisted in the Order model's status column, so
    // assertTransition(order->status, …) resolves for a real persisted order.
    expect(OrderStateMachine::DRAFT)->toBe(\App\Models\OrderStatus::DRAFT);
    expect(OrderStateMachine::PLACED)->toBe(\App\Models\OrderStatus::PLACED);
    expect(OrderStateMachine::CONFIRMED)->toBe(\App\Models\OrderStatus::CONFIRMED);
    expect(OrderStateMachine::CANCELLED)->toBe(\App\Models\OrderStatus::CANCELLED);
    expect(OrderStateMachine::states())->toEqual(\App\Models\OrderStatus::all());
});
