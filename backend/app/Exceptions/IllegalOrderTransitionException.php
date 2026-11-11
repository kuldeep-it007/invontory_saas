<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Thrown when an Order transition event is not defined for the Order's current
 * state in the {@see \App\Services\OrderStateMachine} transition map (R21.4).
 *
 * The order lifecycle is a single declarative `{ fromState: { event: toState } }`
 * map; the state machine rejects any event that is not present for the current
 * state. An illegal transition must leave the Order completely unchanged and is
 * surfaced to the client as an HTTP 422 (R21.4). This is a 422
 * {@see HttpException} so the centralized {@see ApiExceptionRenderer} maps it
 * to the standard `{ error: { code, message, details } }` envelope; the current
 * state and the rejected event are carried in `details` so the caller can see
 * exactly which transition was refused.
 */
class IllegalOrderTransitionException extends HttpException
{
    /**
     * @param  string  $fromState  The Order's current state when the transition was attempted.
     * @param  string  $event  The transition event that is not defined for that state.
     */
    public function __construct(
        public readonly string $fromState,
        public readonly string $event,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            422,
            sprintf(
                "Illegal order transition: event '%s' is not permitted from state '%s'.",
                $event,
                $fromState,
            ),
            $previous,
        );
    }

    /**
     * Structured context surfaced in the error envelope `details` (R21.4).
     *
     * @return array{from_state: string, event: string}
     */
    public function context(): array
    {
        return [
            'from_state' => $this->fromState,
            'event' => $this->event,
        ];
    }
}
