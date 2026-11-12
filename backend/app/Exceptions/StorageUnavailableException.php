<?php

declare(strict_types=1);

namespace App\Exceptions;

use Aws\Exception\AwsException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Thrown when the uploads disk (Oracle Object Storage, or the local fallback)
 * cannot store a file. Rendered as a 503 SERVICE_UNAVAILABLE whose message
 * names the storage error — e.g. "NoSuchBucket" or "SignatureDoesNotMatch" —
 * so a misconfiguration is visible to the admin without reading server logs.
 *
 * The message never contains credentials: S3 error codes/messages and the
 * SDK's own messages do not include the access key secret.
 */
class StorageUnavailableException extends HttpException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct(503, 'Could not save the file to storage: '.self::describe($previous), $previous);
    }

    /** The most specific, human-readable cause in the exception chain. */
    public static function describe(Throwable $e): string
    {
        for ($cur = $e; $cur !== null; $cur = $cur->getPrevious()) {
            if ($cur instanceof AwsException) {
                $code = $cur->getAwsErrorCode();
                $msg = $cur->getAwsErrorMessage();
                if ($code || $msg) {
                    return trim(($code ?: 'Error').': '.($msg ?: ''), ': ');
                }
            }
        }

        // No S3 error code (network failure, missing credentials, local disk
        // not writable, ...): use the innermost message, first line, trimmed.
        $inner = $e;
        while ($inner->getPrevious() !== null) {
            $inner = $inner->getPrevious();
        }
        $line = strtok($inner->getMessage(), "\n") ?: $inner::class;

        return mb_strimwidth($line, 0, 300, '…');
    }
}
