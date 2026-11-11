<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\PlanLimit;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Thrown when a requested plan downgrade would set a {@see PlanLimit}
 * below the tenant's current usage of that resource and the change was NOT
 * explicitly forced (R6.2).
 *
 * This is a 422 {@see HttpException} so the centralized
 * {@see ApiExceptionRenderer} maps it to the standard error envelope. The
 * message always IDENTIFIES the violating resource (R6.2) so the Platform_Admin
 * can see exactly which limit the current usage exceeds (e.g. `max_users`:
 * current usage 12 exceeds target limit 10). The full set of violations is
 * exposed on {@see $violations} for structured consumption.
 */
class DowngradeBlockedException extends HttpException
{
    /**
     * @param  array<string, array{count: int, target_limit: int}>  $violations
     *                                                                           Map of quota key => the current count and the lower target limit that
     *                                                                           would be violated by the downgrade.
     */
    public function __construct(
        public readonly array $violations,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            422,
            self::buildMessage($violations),
            $previous,
        );
    }

    /**
     * Build a descriptive message naming every violating resource (R6.2).
     *
     * @param  array<string, array{count: int, target_limit: int}>  $violations
     */
    private static function buildMessage(array $violations): string
    {
        $parts = [];

        foreach ($violations as $quotaKey => $violation) {
            $parts[] = sprintf(
                '%s (current usage %d exceeds target limit %d)',
                $quotaKey,
                $violation['count'],
                $violation['target_limit'],
            );
        }

        return 'Downgrade blocked: current usage exceeds the target plan limit for '
            .implode(', ', $parts)
            .'. Reduce usage or force the downgrade with an audited reason.';
    }
}
