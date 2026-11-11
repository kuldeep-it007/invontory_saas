<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\AuditLog;
use RuntimeException;

/**
 * Thrown when code attempts to mutate (update) or remove (delete) an existing
 * {@see AuditLog} entry.
 *
 * Audit entries are append-only: once written they are a permanent, immutable
 * record of a security-relevant action or data mutation (R34.2). Any attempt to
 * change or delete an existing entry is a programming error and is rejected at
 * the model layer by raising this exception, leaving the stored entry unchanged.
 */
class ImmutableAuditException extends RuntimeException
{
    public static function forUpdate(): self
    {
        return new self('Audit log entries are append-only and cannot be updated (R34.2).');
    }

    public static function forDelete(): self
    {
        return new self('Audit log entries are append-only and cannot be deleted (R34.2).');
    }
}
