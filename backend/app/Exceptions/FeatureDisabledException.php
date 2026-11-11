<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Thrown when a request targets an optional module whose Feature_Flag is
 * disabled on the tenant's active plan (R5.4).
 *
 * This is a 403 {@see HttpException} so the centralized {@see ApiExceptionRenderer}
 * maps it to the standard `{ error: { code: "FORBIDDEN", message, ... } }`
 * envelope. The message names the disabled feature flag so the caller sees
 * exactly which module is unavailable on the current plan.
 */
class FeatureDisabledException extends HttpException
{
    /**
     * @param  string  $feature  The disabled feature-flag key (e.g. `exports`).
     */
    public function __construct(
        public readonly string $feature,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            403,
            sprintf(
                'Feature [%s] is not enabled on the current plan.',
                $feature,
            ),
            $previous,
        );
    }
}
