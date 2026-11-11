<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Thrown when a reservation request would block more than the AVAILABLE
 * quantity of a stock row (R23.4).
 *
 * Available = on-hand − Σ(active reservations). A reservation may never block
 * more than what is currently available, otherwise the reserved quantity would
 * no longer be guaranteed unavailable to the orders that already hold it. The
 * whole reservation request is rejected and nothing is persisted. This is a 422
 * {@see HttpException} so the centralized {@see ApiExceptionRenderer} maps it to
 * the standard error envelope; `stock_id`, the `requested` quantity, and the
 * `available` quantity are surfaced in the response `details` so the caller
 * sees exactly why the reservation was refused.
 */
class InsufficientAvailableStockException extends HttpException
{
    /**
     * @param  int  $stockId  The stock row whose available quantity was exceeded.
     * @param  string  $requested  The reservation quantity requested (decimal string).
     * @param  string  $available  The available quantity at request time (decimal string).
     */
    public function __construct(
        public readonly int $stockId,
        public readonly string $requested,
        public readonly string $available,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            422,
            sprintf(
                'Insufficient available stock: requested [%s] exceeds available [%s] for stock row [%d].',
                $requested,
                $available,
                $stockId,
            ),
            $previous,
        );
    }
}
