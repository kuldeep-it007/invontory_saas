<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Thrown when applying a set of stock movements would drive one or more Stock
 * quantities below zero while backorders are disabled (R17.1, R17.3).
 *
 * Negative-stock prevention is all-or-nothing: if ANY movement in the batch
 * would make the resulting quantity negative, the ENTIRE operation is rejected
 * and no stock changes are applied (R17.1). This is a 422 {@see HttpException}
 * so the centralized {@see ApiExceptionRenderer} maps it to the standard error
 * envelope; the offending products are carried in `offenders` and surfaced in
 * the response `details` so the caller can see exactly which products were
 * insufficient (R17.3).
 *
 * {@see App\Services\StockMovementService} throws this only when the active
 * tenant's `backorders_enabled` toggle is OFF; when backorders are enabled the
 * batch is permitted to go negative and the backordered quantity is recorded
 * instead (R17.2).
 */
class InsufficientStockException extends HttpException
{
    /**
     * @param  list<array{product_id:int, stock_point_id:int, requested:string, available:string}>  $offenders
     *                                                                                                          The movements that would have driven stock negative, each naming the
     *                                                                                                          product/stock_point, the requested (signed) delta, and the available
     *                                                                                                          quantity before the movement.
     */
    public function __construct(
        public readonly array $offenders,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            422,
            'Insufficient stock: one or more products do not have enough quantity to satisfy the requested movement.',
            $previous,
        );
    }
}
