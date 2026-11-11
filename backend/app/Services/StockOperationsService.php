<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Distributor;
use App\Models\Stock;
use App\Models\StockPoint;
use App\Models\StockLedger;
use App\Services\Stock\StockMovement;
use App\Support\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Stock inward and manual-adjustment domain service (R14).
 *
 * The focused, single-responsibility service the thin
 * {@see \App\Http\Controllers\StockController} delegates to for the two
 * user-driven, quantity-increasing/-correcting operations:
 *
 *   • {@see inward()} — record stock inward of type `opening`, `received`, or
 *     `adjustment_in`, increasing the on-hand Stock and appending a Stock_Ledger
 *     entry in ONE atomic transaction (R14.1).
 *   • {@see adjust()} — record a manual stock adjustment (positive or negative),
 *     which REQUIRES a non-empty reason (R14.3), writes a Stock_Ledger entry
 *     (R14.5), and records the adjustment and its reason in the Audit_Log
 *     (R14.3, R34.3).
 *
 * **Everything routes through {@see StockMovementService::apply()}.** This
 * service never touches the `stock` or `stock_ledger` tables directly: it builds
 * a {@see StockMovement} value object and hands it to the single stock authority
 * so the atomicity, FOR UPDATE row-locking, immutable-ledger, and balance-after
 * invariants hold in exactly one place (R14.1, R14.2, R14.5). If the stock
 * increase/decrease fails, the ledger write rolls back with it — the whole batch
 * is all-or-nothing (R14.2).
 *
 * **Owner.** Inward and manual adjustments are a Company operation (R14 is a
 * Company_Admin story), so the active tenant's singleton {@see Company} is the
 * polymorphic Stock owner.
 *
 * **Actor.** The acting user id is threaded into the movement context so each
 * ledger entry records who performed the movement (R15.1), and the manual
 * adjustment audit records the same actor (R34.3).
 */
class StockOperationsService
{
    /**
     * The movement types a stock-inward operation may use (R14.1). All increase
     * on-hand stock; the caller supplies a positive quantity.
     *
     * @var list<string>
     */
    public const INWARD_TYPES = ['opening', 'received', 'adjustment_in'];

    public function __construct(
        private readonly StockMovementService $movements,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * Record stock inward, increasing on-hand Stock and appending one immutable
     * Stock_Ledger entry in a single atomic transaction (R14.1, R14.2).
     *
     * The movement is routed through {@see StockMovementService::apply()}, which
     * owns the transaction, row locking, and ledger write — so if the stock
     * increase fails the ledger entry is rolled back with it and no partial
     * change persists (R14.2).
     *
     * @param  array{product_id: int, stock_point_id: int, quantity: string, type?: string|null, reason?: string|null, reference?: string|null}  $data
     * @param  int|null  $actorId  The acting user id recorded on the ledger entry (R15.1).
     * @return StockLedger The ledger entry written for the inward movement.
     */
    public function inward(array $data, ?int $actorId): StockLedger
    {
        $type = $data['type'] ?? 'received';

        if (! in_array($type, self::INWARD_TYPES, true)) {
            // Defensive: the FormRequest already restricts the type, but the
            // service guards its own contract so it is safe to call directly.
            throw new InvalidArgumentException(sprintf(
                'Stock inward type must be one of [%s], got [%s].',
                implode(', ', self::INWARD_TYPES),
                $type,
            ));
        }

        $movement = StockMovement::forOwner(
            $this->owner(),
            (int) $data['product_id'],
            (int) $data['stock_point_id'],
            $this->positive((string) $data['quantity']),
            $type,
            $data['reason'] ?? null,
            $data['reference'] ?? null,
        );

        $ledger = $this->movements->apply([$movement], ['actor_id' => $actorId]);

        return $ledger[0];
    }

    /**
     * Record a manual stock adjustment, writing a Stock_Ledger entry (R14.5) and
     * recording the adjustment and its reason in the Audit_Log (R14.3, R34.3).
     *
     * A non-empty, non-whitespace reason is REQUIRED (R14.3); the FormRequest
     * returns a 422 when it is absent (R14.4) and this method guards the same
     * contract so direct callers cannot bypass it. The signed `quantity`
     * (positive to increase, negative to decrease) is applied through
     * {@see StockMovementService::apply()} as an `adjustment_in` / `adjustment_out`
     * movement, so the stock change and ledger write are one atomic unit (R14.2).
     *
     * The audit is recorded only AFTER the movement is durably applied, so a
     * rolled-back adjustment is never audited as if it happened.
     *
     * @param  array{product_id: int, stock_point_id: int, quantity: string, reason: string, reference?: string|null}  $data
     * @param  int|null  $actorId  The acting user id recorded on the ledger entry and the audit entry (R15.1, R34.3).
     * @return StockLedger The ledger entry written for the adjustment.
     */
    public function adjust(array $data, ?int $actorId): StockLedger
    {
        $reason = $this->requireReason($data['reason'] ?? null);
        $quantity = $this->scaleSigned((string) $data['quantity']);

        // A non-negative quantity increases stock (adjustment_in); a negative
        // quantity decreases it (adjustment_out). The sign is preserved on the
        // ledger `quantity`; the movement type names the direction.
        $type = str_starts_with($quantity, '-') ? 'adjustment_out' : 'adjustment_in';

        $movement = StockMovement::forOwner(
            $this->owner(),
            (int) $data['product_id'],
            (int) $data['stock_point_id'],
            $quantity,
            $type,
            $reason,
            $data['reference'] ?? null,
        );

        $ledger = $this->movements->apply([$movement], ['actor_id' => $actorId])[0];

        // Record the adjustment and its reason in the Audit_Log (R14.3, R34.3),
        // only after the movement is durably applied.
        $this->audit->record(
            'stock_adjusted',
            auth()->user(),
            [
                'product_id' => (int) $data['product_id'],
                'stock_point_id' => (int) $data['stock_point_id'],
                'movement_type' => $type,
                'quantity' => $quantity,
                'balance_after' => (string) $ledger->balance_after,
                'stock_ledger_id' => $ledger->getKey(),
                'reference' => $data['reference'] ?? null,
            ],
            reason: $reason,
        );

        return $ledger;
    }

    /**
     * Add stock held by a DISTRIBUTOR (distributor-wise stock management).
     *
     * The stock row and ledger entry belong to the distributor — never the
     * Company — so Company and Distributor inventories stay separate. The
     * target stock point must be one of that distributor's own points; when
     * none is given the distributor's primary point is used, provisioning a
     * "Default" point if it owns none (the same convention order confirmation
     * uses when stock lands with a distributor).
     *
     * @param  array{distributor_id: int, product_id: int, quantity: string, stock_point_id?: int|null, reason?: string|null}  $data
     */
    public function inwardForDistributor(array $data, ?int $actorId): StockLedger
    {
        $distributor = Distributor::query()->findOrFail((int) $data['distributor_id']);

        $pointId = isset($data['stock_point_id']) ? (int) $data['stock_point_id'] : null;
        if ($pointId !== null) {
            $owned = StockPoint::query()
                ->whereKey($pointId)
                ->where('owner_type', $distributor->getMorphClass())
                ->where('owner_id', (int) $distributor->getKey())
                ->exists();

            if (! $owned) {
                throw ValidationException::withMessages([
                    'stock_point_id' => 'That stock point does not belong to the selected distributor.',
                ]);
            }
        } else {
            $point = StockPoint::query()
                ->where('owner_type', $distributor->getMorphClass())
                ->where('owner_id', (int) $distributor->getKey())
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();

            if ($point === null) {
                $point = new StockPoint(['name' => 'Default', 'sort_order' => 0]);
                $point->owner()->associate($distributor);
                $point->save();
            }

            $pointId = (int) $point->getKey();
        }

        $movement = StockMovement::forOwner(
            $distributor,
            (int) $data['product_id'],
            $pointId,
            $this->positive((string) $data['quantity']),
            'received',
            $data['reason'] ?? null,
            'distributor-stock',
        );

        return $this->movements->apply([$movement], ['actor_id' => $actorId])[0];
    }

    /**
     * Edit a Company stock row's on-hand quantity to an ABSOLUTE value.
     *
     * The row is locked FOR UPDATE, the delta (new − current) is computed from
     * the locked value, and it is applied through
     * {@see StockMovementService::apply()} as an `adjustment_in`/`adjustment_out`
     * — so the immutable ledger stays append-only, the change is audited with
     * its reason, and a concurrent order cannot be overwritten by a stale read.
     *
     * The new quantity may not drop below what is already reserved by active
     * orders, otherwise confirmed-but-unfulfilled reservations would be
     * un-backed.
     *
     * @throws AccessDeniedHttpException when the row is not Company/Distributor stock.
     * @throws ValidationException       when unchanged or below the reserved quantity.
     */
    public function setQuantity(Stock $stock, string $newQuantity, string $reason, ?int $actorId): StockLedger
    {
        $reason = $this->requireReason($reason);
        // Only Company- or Distributor-owned rows are editable here; the movement
        // is applied against the row's OWN owner so company and distributor
        // stock (and their ledgers) stay strictly separate.
        $company = $this->owner();
        $isCompany = $stock->stockable_type === $company->getMorphClass()
            && (int) $stock->stockable_id === (int) $company->getKey();
        $isDistributor = $stock->stockable_type === (new Distributor)->getMorphClass();

        if (! $isCompany && ! $isDistributor) {
            throw new AccessDeniedHttpException('Only Company or Distributor stock can be edited here.');
        }

        $owner = $isCompany ? $company : Distributor::query()->findOrFail((int) $stock->stockable_id);

        $ledger = DB::connection('tenant')->transaction(function () use ($stock, $owner, $newQuantity, $reason, $actorId): StockLedger {
            $locked = Stock::query()->lockForUpdate()->findOrFail($stock->getKey());

            $target = number_format((float) $newQuantity, 3, '.', '');
            $current = number_format((float) $locked->quantity, 3, '.', '');
            $reserved = (new StockReservationService)->reservedQuantity((int) $locked->getKey());

            if ((float) $target < (float) $reserved) {
                throw ValidationException::withMessages([
                    'quantity' => sprintf('Quantity cannot be below the %s already reserved by active orders.', rtrim(rtrim($reserved, '0'), '.')),
                ]);
            }

            $delta = number_format((float) $target - (float) $current, 3, '.', '');
            if ((float) $delta === 0.0) {
                throw ValidationException::withMessages(['quantity' => 'Stock is already at that quantity.']);
            }

            $type = str_starts_with($delta, '-') ? 'adjustment_out' : 'adjustment_in';

            $movement = StockMovement::forOwner(
                $owner,
                (int) $locked->product_id,
                (int) $locked->stock_point_id,
                $delta,
                $type,
                $reason,
                'stock-edit',
            );

            return $this->movements->apply([$movement], ['actor_id' => $actorId])[0];
        });

        $this->audit->record(
            'stock_edited',
            auth()->user(),
            [
                'stock_id' => (int) $stock->getKey(),
                'product_id' => (int) $stock->product_id,
                'stock_point_id' => (int) $stock->stock_point_id,
                'movement_type' => $ledger->movement_type,
                'quantity' => (string) $ledger->quantity,
                'balance_after' => (string) $ledger->balance_after,
                'stock_ledger_id' => $ledger->getKey(),
            ],
            reason: $reason,
        );

        return $ledger;
    }

    /**
     * The polymorphic Stock owner for Company inward/adjustment operations: the
     * active tenant's singleton Company (R1.5, R14).
     */
    private function owner(): Company
    {
        return Company::query()->firstOrFail();
    }

    /**
     * Reject a manual adjustment whose reason is missing, empty, or whitespace
     * only (R14.3). The HTTP surface rejects this with a 422 before reaching the
     * service (R14.4); this guard protects direct callers.
     *
     * @throws InvalidArgumentException when the reason is empty or whitespace.
     */
    private function requireReason(?string $reason): string
    {
        $trimmed = trim((string) $reason);

        if ($trimmed === '') {
            throw new InvalidArgumentException('A manual stock adjustment requires a non-empty reason.');
        }

        return $trimmed;
    }

    /**
     * Normalize an inward quantity to its positive magnitude. Inward always
     * increases stock, so a sign on the input is ignored rather than silently
     * decreasing stock.
     */
    private function positive(string $quantity): string
    {
        return ltrim(trim($quantity), '+-');
    }

    /**
     * Normalize a signed adjustment quantity: trim surrounding whitespace and a
     * leading `+`, preserving a leading `-` for a decrease.
     */
    private function scaleSigned(string $quantity): string
    {
        $trimmed = ltrim(trim($quantity), '+');

        return $trimmed === '' ? '0' : $trimmed;
    }
}
