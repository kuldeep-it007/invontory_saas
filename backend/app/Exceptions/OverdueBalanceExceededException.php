<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Thrown when a Buyer whose overdue balance exceeds its overdue balance limit
 * attempts to place a new Order (R26.4).
 *
 * A Buyer is blocked from placing NEW orders if and only if its overdue
 * balance exceeds the configured overdue balance limit (Property 25). This is
 * evaluated at order-placement time by
 * {@see App\Services\CreditEnforcementService::assertNotOverdueBlocked()}; it is
 * distinct from the credit-limit check (R26.2), which runs only at confirmation.
 *
 * This is a 403 {@see HttpException} so the centralized {@see ApiExceptionRenderer}
 * maps it to the standard `FORBIDDEN` envelope — the Buyer is not permitted to
 * place the order rather than having sent a malformed request. The message is
 * descriptive (R26.4), naming the overdue balance and the limit; both figures
 * are also carried as readonly properties for programmatic callers.
 */
class OverdueBalanceExceededException extends HttpException
{
    /**
     * @param  string  $overdueBalance  The Buyer's current overdue balance (decimal string).
     * @param  string  $overdueBalanceLimit  The configured overdue balance limit (decimal string).
     */
    public function __construct(
        public readonly string $overdueBalance,
        public readonly string $overdueBalanceLimit,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            403,
            sprintf(
                'Order blocked: the buyer\'s overdue balance of %s exceeds the overdue balance limit of %s. '
                .'Settle the overdue amount or record an authorized override to proceed.',
                $overdueBalance,
                $overdueBalanceLimit,
            ),
            $previous,
        );
    }
}
