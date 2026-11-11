<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Thrown when creating a tenant-owned resource would breach the active plan's
 * quota for that resource (R5.2, R5.3, R10.4).
 *
 * This is a 403 {@see HttpException} so the centralized {@see ApiExceptionRenderer}
 * maps it to the standard `{ error: { code: "FORBIDDEN", message, ... } }`
 * envelope. The message always NAMES the exceeded quota key (R5.2) so callers
 * can see exactly which limit was hit (e.g. `max_dealers_per_distributor`,
 * `max_orders_per_month`).
 */
class QuotaExceededException extends HttpException
{
    /**
     * @param  string  $quotaKey  The exceeded quota key (e.g. `max_distributors`).
     * @param  int  $count  The current count that met/exceeded the limit.
     * @param  int  $limit  The active plan limit that was reached.
     */
    public function __construct(
        public readonly string $quotaKey,
        public readonly int $count,
        public readonly int $limit,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            403,
            sprintf(
                'Quota [%s] exceeded: current usage %d has reached the plan limit of %d.',
                $quotaKey,
                $count,
                $limit,
            ),
            $previous,
        );
    }
}
