<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Thrown when a return request is malformed relative to the order it is
 * processed against (R18.3, R22.2).
 *
 * A return must name at least one line, each referencing a product that is part
 * of the order and carrying a positive quantity. {@see \App\Services\OrderReturnService}
 * validates the requested lines against the order's lines BEFORE any stock or
 * account side effect and rejects a mismatch with this exception. It is a 422
 * {@see HttpException} so the centralized {@see ApiExceptionRenderer} maps it
 * to the standard `{ error: { code, message, details } }` envelope.
 */
class InvalidReturnException extends HttpException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct(422, $message, $previous);
    }
}
