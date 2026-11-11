<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Thrown when a stock transfer names a source and destination stock point that
 * do NOT belong to the same owner (R18.1).
 *
 * A transfer only moves inventory between two stock points of ONE owner — it
 * decrements the owner's source and increments the owner's destination without
 * changing the total owned quantity. Moving stock between two DIFFERENT owners
 * is a different operation (an order/sale), so a cross-owner transfer is
 * rejected with a 422 {@see HttpException}; the centralized
 * {@see ApiExceptionRenderer} maps it to the standard error envelope.
 */
class StockPointOwnerMismatchException extends HttpException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(
            422,
            'Stock can only be transferred between two stock points of the same owner.',
            $previous,
        );
    }
}
