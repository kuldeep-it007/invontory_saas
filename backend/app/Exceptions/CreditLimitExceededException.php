<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Thrown when confirming an Order would drive a Buyer's projected
 * Outstanding_Balance above its configured credit limit (R26.2).
 *
 * Projected outstanding is the Buyer's current Outstanding_Balance O plus the
 * order total T; confirmation is blocked if and only if O + T exceeds the
 * credit limit L (Property 24). The check runs ONLY at confirmation time — a
 * Draft/Placed order is never pre-emptively blocked on credit — so this is
 * raised exclusively from {@see App\Services\CreditEnforcementService::assertCreditWithinLimit()}
 * inside the confirmation flow.
 *
 * This is a 422 {@see HttpException} so the centralized {@see ApiExceptionRenderer}
 * maps it to the standard error envelope. The message is descriptive (R26.2),
 * naming the current outstanding, the order total, the projected outstanding,
 * and the limit so the caller can see exactly why confirmation was refused. The
 * same figures are carried as readonly properties for programmatic callers.
 */
class CreditLimitExceededException extends HttpException
{
    /**
     * @param  string  $outstanding  The Buyer's current Outstanding_Balance O (decimal string).
     * @param  string  $orderTotal  The order total T being confirmed (decimal string).
     * @param  string  $projected  The projected outstanding O + T (decimal string).
     * @param  string  $creditLimit  The configured credit limit L (decimal string).
     */
    public function __construct(
        public readonly string $outstanding,
        public readonly string $orderTotal,
        public readonly string $projected,
        public readonly string $creditLimit,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            422,
            sprintf(
                'Credit limit exceeded: confirming this order would raise the projected outstanding '
                .'balance to %s (current %s + order %s), above the credit limit of %s.',
                $projected,
                $outstanding,
                $orderTotal,
                $creditLimit,
            ),
            $previous,
        );
    }
}
