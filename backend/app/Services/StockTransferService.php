<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\StockPointOwnerMismatchException;
use App\Models\StockLedger;
use App\Models\StockPoint;
use App\Services\Stock\StockMovement;

/**
 * Moves Stock between two Stock_Points of the SAME owner (R18.1).
 *
 * A transfer is a pair of movements applied atomically: a `transfer_out`
 * decrement at the source stock point and a `transfer_in` increment at the
 * destination, tied together by one shared `reference`. Both movements target
 * the SAME owner (the stock points' polymorphic owner), so the owner's total
 * owned quantity for the product is conserved — a transfer only relocates
 * stock, it never creates or destroys it (R18.1, design "Stock transfer").
 *
 * This focused service owns only the transfer's business rules — resolving and
 * validating the two stock points, asserting same-ownership, and composing the
 * movement pair. The atomicity, FOR UPDATE row-locking, immutable-ledger, and
 * negative-stock guarantees all live in {@see StockMovementService::apply()},
 * which this service delegates to so every stock change still routes through
 * the single inventory authority (R13.1, R15.1, R16.4, R35.3).
 */
class StockTransferService
{
    public function __construct(private readonly StockMovementService $movements) {}

    /**
     * Transfer `quantity` of a product from the source stock point to the
     * destination stock point, both owned by the same owner (R18.1).
     *
     * Resolves the two stock points, rejects the transfer with a 422 when they
     * belong to different owners (or either is missing), and otherwise applies
     * the decrement/increment pair in a single transaction via
     * {@see StockMovementService::apply()}. The source decrement is subject to
     * the negative-stock guard, so a transfer that would overdraw the source is
     * rejected with the whole batch and nothing is persisted (R17.1).
     *
     * @param  int  $productId  The product being moved.
     * @param  int  $sourceStockPointId  The stock point to decrement.
     * @param  int  $destinationStockPointId  The stock point to increment.
     * @param  string  $quantity  The positive quantity to move, as a decimal string.
     * @param  array{actor_id?: int|null, reason?: string|null, reference?: string|null}  $context
     *                                                                                               Optional context. `actor_id` is recorded on both ledger entries;
     *                                                                                               `reason` is carried onto both entries; `reference` ties the two
     *                                                                                               movements together (a shared value is generated when omitted).
     * @return list<StockLedger> The two ledger entries written (out, then in).
     *
     * @throws StockPointOwnerMismatchException when the stock points belong to different owners.
     */
    public function transfer(
        int $productId,
        int $sourceStockPointId,
        int $destinationStockPointId,
        string $quantity,
        array $context = [],
    ): array {
        $source = StockPoint::query()->findOrFail($sourceStockPointId);
        $destination = StockPoint::query()->findOrFail($destinationStockPointId);

        $this->assertSameOwner($source, $destination);

        $reference = $context['reference']
            ?? ('transfer:'.$source->getKey().'->'.$destination->getKey().':'.uniqid('', true));
        $reason = $context['reason'] ?? null;

        $movements = [
            new StockMovement(
                stockableType: $source->owner_type,
                stockableId: (int) $source->owner_id,
                productId: $productId,
                stockPointId: (int) $source->getKey(),
                delta: $this->negate($quantity),
                movementType: 'transfer_out',
                reason: $reason,
                reference: $reference,
            ),
            new StockMovement(
                stockableType: $destination->owner_type,
                stockableId: (int) $destination->owner_id,
                productId: $productId,
                stockPointId: (int) $destination->getKey(),
                delta: $quantity,
                movementType: 'transfer_in',
                reason: $reason,
                reference: $reference,
            ),
        ];

        return $this->movements->apply($movements, [
            'actor_id' => $context['actor_id'] ?? null,
        ]);
    }

    /**
     * Reject the transfer unless both stock points share the SAME polymorphic
     * owner (same owner type AND owner id) — a transfer only relocates one
     * owner's stock between its own stock points (R18.1).
     *
     * @throws StockPointOwnerMismatchException
     */
    private function assertSameOwner(StockPoint $source, StockPoint $destination): void
    {
        $sameOwner = $source->owner_type === $destination->owner_type
            && (int) $source->owner_id === (int) $destination->owner_id;

        if (! $sameOwner) {
            throw new StockPointOwnerMismatchException;
        }
    }

    /**
     * Negate a numeric decimal string so the source movement is a decrement
     * (`-quantity`) while the destination movement is the positive increment.
     * A leading minus is stripped rather than doubled so the result is always a
     * single, well-formed signed decimal string that {@see StockMovement}
     * accepts.
     */
    private function negate(string $quantity): string
    {
        $quantity = ltrim($quantity, '+');

        if (str_starts_with($quantity, '-')) {
            return substr($quantity, 1);
        }

        return '-'.$quantity;
    }
}
