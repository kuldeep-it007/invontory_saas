<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when code attempts to mutate (update) or remove (delete) an existing
 * ledger entry — a {@see App\Models\StockLedger} today, and the forthcoming
 * Transaction_Ledger.
 *
 * Ledgers are append-only: once a movement is written it is a permanent,
 * immutable record of a stock (or account) change (R15.2, R25.3). Any attempt
 * to change or delete an existing entry is a programming error and is rejected
 * at the model layer by raising this exception, leaving the stored entry
 * unchanged. The initial insert that appends a new entry is always permitted.
 *
 * This mirrors {@see ImmutableAuditException} (which guards the append-only
 * audit log, R34.2) but is distinct so callers can tell a ledger-immutability
 * violation apart from an audit-immutability violation.
 */
class ImmutableLedgerException extends RuntimeException
{
    public static function forUpdate(string $ledger = 'ledger'): self
    {
        return new self(sprintf('%s entries are append-only and cannot be updated (R15.2).', ucfirst($ledger)));
    }

    public static function forDelete(string $ledger = 'ledger'): self
    {
        return new self(sprintf('%s entries are append-only and cannot be deleted (R15.2).', ucfirst($ledger)));
    }
}
