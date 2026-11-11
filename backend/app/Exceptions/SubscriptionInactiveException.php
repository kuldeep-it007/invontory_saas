<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Thrown when a tenant attempts to create a new tenant-owned resource while its
 * active subscription status is `past_due` or `cancelled` (R5.7).
 *
 * This is a 403 {@see HttpException} so the centralized {@see ApiExceptionRenderer}
 * maps it to the standard `{ error: { code: "FORBIDDEN", message, ... } }`
 * envelope. The message names the blocking status so the caller sees exactly
 * why creation was denied.
 */
class SubscriptionInactiveException extends HttpException
{
    /**
     * @param  string  $status  The blocking subscription status (`past_due`/`cancelled`).
     */
    public function __construct(
        public readonly string $status,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            403,
            sprintf(
                'Subscription is [%s]: creation of new resources is not permitted until the subscription is active.',
                $status,
            ),
            $previous,
        );
    }
}
