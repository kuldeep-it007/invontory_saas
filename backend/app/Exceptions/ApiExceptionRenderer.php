<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Centralized API exception renderer.
 *
 * Produces the single, uniform error envelope used across every JSON endpoint:
 *
 *     {
 *         "error": {
 *             "code":    "SNAKE_CASE_CODE",
 *             "message": "Human readable message",
 *             "details": { ... }   // optional, structured context (e.g. validation errors)
 *         }
 *     }
 *
 * Wired in bootstrap/app.php via `$exceptions->render(...)`.
 */
class ApiExceptionRenderer
{
    /**
     * Render the given throwable as the standard error envelope.
     */
    public function render(Throwable $e, Request $request): JsonResponse
    {
        [$status, $code, $message, $details] = $this->map($e);

        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($details !== null) {
            $error['details'] = $details;
        }

        // Surface debugging context only when APP_DEBUG is on and the error is a 5xx.
        if (config('app.debug') && $status >= 500) {
            $error['details'] = array_merge($error['details'] ?? [], [
                'exception' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        return new JsonResponse(['error' => $error], $status);
    }

    /**
     * Map a throwable to [httpStatus, errorCode, message, details].
     *
     * @return array{0:int,1:string,2:string,3:array<string,mixed>|null}
     */
    private function map(Throwable $e): array
    {
        return match (true) {
            $e instanceof ValidationException => [
                422,
                'VALIDATION_FAILED',
                $e->getMessage(),
                ['fields' => $e->errors()],
            ],
            $e instanceof AuthenticationException => [
                401,
                'UNAUTHENTICATED',
                'Authentication is required to access this resource.',
                null,
            ],
            $e instanceof AuthorizationException => [
                403,
                'FORBIDDEN',
                $e->getMessage() ?: 'This action is unauthorized.',
                null,
            ],
            $e instanceof ModelNotFoundException => [
                404,
                'NOT_FOUND',
                'The requested resource was not found.',
                null,
            ],
            // Negative-stock rejection: a 422 that must IDENTIFY the insufficient
            // products (R17.3). The all-or-nothing guard in
            // StockMovementService collects every offending line; surface them in
            // `details.products` so the caller sees exactly which products (and at
            // which stock point) could not satisfy the requested movement.
            $e instanceof InsufficientStockException => [
                422,
                'INSUFFICIENT_STOCK',
                $e->getMessage(),
                ['products' => $e->offenders],
            ],
            $e instanceof HttpExceptionInterface => [
                $e->getStatusCode(),
                $this->codeForStatus($e->getStatusCode()),
                $e->getMessage() ?: $this->messageForStatus($e->getStatusCode()),
                null,
            ],
            default => [
                500,
                'INTERNAL_SERVER_ERROR',
                config('app.debug') ? $e->getMessage() : 'An unexpected error occurred.',
                null,
            ],
        };
    }

    /**
     * Derive a stable, machine-readable error code from an HTTP status.
     */
    private function codeForStatus(int $status): string
    {
        return match ($status) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHENTICATED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            409 => 'CONFLICT',
            422 => 'VALIDATION_FAILED',
            429 => 'TOO_MANY_REQUESTS',
            500 => 'INTERNAL_SERVER_ERROR',
            503 => 'SERVICE_UNAVAILABLE',
            default => 'HTTP_ERROR_'.$status,
        };
    }

    /**
     * Default human-readable message for an HTTP status.
     */
    private function messageForStatus(int $status): string
    {
        return match ($status) {
            400 => 'The request was malformed.',
            403 => 'This action is forbidden.',
            404 => 'The requested resource was not found.',
            405 => 'The HTTP method is not allowed for this resource.',
            409 => 'The request conflicts with the current state of the resource.',
            429 => 'Too many requests.',
            503 => 'The service is temporarily unavailable.',
            default => 'An error occurred while processing the request.',
        };
    }
}
