<?php

declare(strict_types=1);

namespace App\Services\Stock;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * An immutable value object describing a single stock movement to be applied by
 * {@see App\Services\StockMovementService}.
 *
 * A movement names the affected stock row — the polymorphic owner
 * (`stockableType`, `stockableId`), the `productId`, and the `stockPointId` —
 * together with the signed `delta` to apply to the on-hand quantity, the
 * `movementType` recorded on the ledger entry, and optional `reason` and
 * `reference` metadata (R15.1). A positive delta is an increment (inward); a
 * negative delta is a decrement (outward).
 *
 * The owner is modelled as its morph type + key rather than a live model so a
 * batch of movements can be built once and locked/applied deterministically; a
 * convenience {@see forOwner()} factory derives the type/key from a persisted
 * Eloquent model.
 *
 * Quantities are kept as decimal strings (never floats) so exactness is
 * preserved end-to-end for the balance-after invariant (R15.3).
 */
final class StockMovement
{
    /**
     * @param  string  $stockableType  The owner morph type (e.g. the Company/Distributor class or morph alias).
     * @param  int  $stockableId  The owner key.
     * @param  int  $productId  The product whose quantity moves.
     * @param  int  $stockPointId  The stock point at which the movement occurs.
     * @param  string  $delta  The signed quantity to apply, as a decimal string (positive = increment, negative = decrement).
     * @param  string  $movementType  The movement type recorded on the ledger entry (e.g. `received`, `sale_out`, `transfer_in`).
     * @param  string|null  $reason  Optional reason (required for manual adjustments; validated by the caller — R14.3).
     * @param  string|null  $reference  Optional free-form reference tying the movement to its source (order/transfer/adjustment).
     */
    public function __construct(
        public readonly string $stockableType,
        public readonly int $stockableId,
        public readonly int $productId,
        public readonly int $stockPointId,
        public readonly string $delta,
        public readonly string $movementType,
        public readonly ?string $reason = null,
        public readonly ?string $reference = null,
    ) {
        if ($this->stockableType === '') {
            throw new InvalidArgumentException('A stock movement requires a non-empty owner type.');
        }

        if ($this->movementType === '') {
            throw new InvalidArgumentException('A stock movement requires a non-empty movement type.');
        }

        if (! is_numeric($this->delta)) {
            throw new InvalidArgumentException(sprintf('A stock movement delta must be numeric, got [%s].', $this->delta));
        }
    }

    /**
     * Build a movement for a persisted owner model, deriving its morph type and
     * key. The owner is the Company/Distributor/Dealer that holds the stock
     * (R13.4, R13.5, R13.7).
     */
    public static function forOwner(
        Model $owner,
        int $productId,
        int $stockPointId,
        string $delta,
        string $movementType,
        ?string $reason = null,
        ?string $reference = null,
    ): self {
        return new self(
            $owner->getMorphClass(),
            (int) $owner->getKey(),
            $productId,
            $stockPointId,
            $delta,
            $movementType,
            $reason,
            $reference,
        );
    }

    /**
     * The deterministic composite key identifying the affected stock row:
     * (owner type, owner id, product, stock point). Used to group movements and
     * resolve the locked stock row for each movement.
     */
    public function stockKey(): string
    {
        return implode('|', [
            $this->stockableType,
            $this->stockableId,
            $this->productId,
            $this->stockPointId,
        ]);
    }
}
