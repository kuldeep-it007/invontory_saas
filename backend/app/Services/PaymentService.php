<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\PaymentRecordedMail;
use App\Models\Concerns\TenantAuditable;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Focused service for manual payment recording (R24.1–R24.4).
 *
 * Orchestrates everything that happens when an authorized user records a
 * Payment against a Buyer's outstanding balance, all in one tenant-database
 * transaction so the payment, its ledger entry, and its invoice allocations are
 * atomic:
 *
 *   1. Persist the {@see Payment} with amount, method, reference, date, and the
 *      recorded-by identity (R24.1). The Payment is {@see TenantAuditable}, so
 *      its creation is recorded in the audit log (R34.1).
 *   2. Append a `transaction_ledger` entry via {@see AccountLedgerService} and
 *      update the running balance (R24.2) — the payment reduces the Buyer's
 *      Outstanding_Balance.
 *   3. Allocate the payment across one or more invoices (partial payments and
 *      multi-invoice allocation), enforcing that the sum of allocations never
 *      exceeds the payment amount (R24.3).
 *   4. After commit, queue a payment-recorded email using the tenant's sender
 *      identity (R24.4, R27.4) — queued so the mail send never blocks the
 *      recording response.
 *
 * All work runs on the active tenant connection (resolved by the tenancy
 * middleware), so records always belong to the current tenant (physical
 * isolation).
 */
class PaymentService
{
    public function __construct(
        private readonly AccountLedgerService $ledger,
        private readonly WhiteLabelService $whiteLabel,
        private readonly ?ReportCache $reportCache = null,
        private readonly ?NotificationService $notifications = null,
    ) {}

    /**
     * Record a manual payment against a Buyer and return the persisted Payment
     * (with its allocations loaded).
     *
     * @param  Model  $buyer  The Distributor/Dealer the payment is recorded against.
     * @param  array{
     *     amount: string|int|float,
     *     method: string,
     *     reference?: string|null,
     *     paid_at?: string|\DateTimeInterface|null,
     *     recorded_by?: int|null,
     *     allocations?: list<array{invoice_id: int, amount: string|int|float}>
     * }  $data  Validated payment attributes. `allocations` is optional; when
     *          present each entry applies part of the payment to an invoice.
     *
     * @throws UnprocessableEntityHttpException When the amount is not positive, or
     *                                          when the sum of allocations exceeds the payment amount (R24.3).
     */
    public function record(Model $buyer, array $data): Payment
    {
        $amount = $this->normalizeAmount((string) $data['amount']);
        $this->assertPositive($amount);

        $allocations = $this->normalizeAllocations($data['allocations'] ?? []);
        $this->assertAllocationsWithinAmount($amount, $allocations);

        $payment = DB::connection('tenant')->transaction(function () use ($buyer, $data, $amount, $allocations): Payment {
            // 1. Persist the payment (R24.1). Audited via TenantAuditable.
            $payment = new Payment;
            $payment->forceFill([
                'buyer_type' => $buyer->getMorphClass(),
                'buyer_id' => (int) $buyer->getKey(),
                'amount' => $amount,
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'paid_at' => $this->resolvePaidAt($data['paid_at'] ?? null),
                'recorded_by' => $data['recorded_by'] ?? auth()->id(),
            ])->save();

            // 2. Append the transaction-ledger entry + update running balance
            //    (R24.2). AccountLedgerService records the payment as a negative
            //    amount so the Outstanding_Balance drops.
            $this->ledger->appendPayment($buyer, $amount, [
                'payment_id' => (int) $payment->getKey(),
                'reference' => $data['reference'] ?? null,
                'actor_id' => $payment->recorded_by,
            ]);

            // 3. Record the invoice allocations (R24.3).
            foreach ($allocations as $allocation) {
                PaymentAllocation::create([
                    'payment_id' => (int) $payment->getKey(),
                    'invoice_id' => $allocation['invoice_id'],
                    'amount' => $allocation['amount'],
                ]);
            }

            return $payment;
        });

        // 4. After commit: invalidate the tenant's cached report aggregates — a
        //    recorded payment changes the transaction ledger and running balance
        //    that feed payments/collections and outstanding-ageing reports, so
        //    the next read must recompute (R29.8).
        ($this->reportCache ?? new ReportCache)->invalidate();

        // 5. Queue the payment-recorded email after commit (R24.4, R27.4).
        $this->sendPaymentRecordedEmail($buyer, $payment);

        // 6. Record the in-app payment-recorded notification mirror (R27.6) and,
        //    where enabled, the optional SMS/WhatsApp channels (R27.5) —
        //    post-commit so it never affects the recording transaction.
        $this->recordPaymentRecordedNotification($buyer, $payment);

        return $payment->load('allocations');
    }

    /**
     * The Buyer's Outstanding_Balance — the latest transaction-ledger
     * `balance_after` (R25.2). Convenience pass-through to
     * {@see AccountLedgerService}.
     */
    public function outstandingBalance(Model $buyer): string
    {
        return $this->ledger->outstandingBalance($buyer);
    }

    /**
     * Normalize the allocation list to `{invoice_id, amount}` with amounts as
     * fixed-scale decimal strings, rejecting non-positive allocation amounts.
     *
     * @param  list<array{invoice_id: int|string, amount: string|int|float}>  $allocations
     * @return list<array{invoice_id: int, amount: string}>
     *
     * @throws UnprocessableEntityHttpException When an allocation amount is not positive.
     */
    private function normalizeAllocations(array $allocations): array
    {
        $normalized = [];

        foreach ($allocations as $allocation) {
            $amount = $this->normalizeAmount((string) $allocation['amount']);

            if (! $this->isPositive($amount)) {
                throw new UnprocessableEntityHttpException(
                    'Each payment allocation amount must be greater than zero.',
                );
            }

            $normalized[] = [
                'invoice_id' => (int) $allocation['invoice_id'],
                'amount' => $amount,
            ];
        }

        return $normalized;
    }

    /**
     * Enforce the allocation ceiling: the sum of allocation amounts must never
     * exceed the payment amount (R24.3).
     *
     * @param  list<array{invoice_id: int, amount: string}>  $allocations
     *
     * @throws UnprocessableEntityHttpException When the sum exceeds the amount.
     */
    private function assertAllocationsWithinAmount(string $amount, array $allocations): void
    {
        $total = '0.00';
        foreach ($allocations as $allocation) {
            $total = $this->add($total, $allocation['amount']);
        }

        if ($this->compare($total, $amount) > 0) {
            throw new UnprocessableEntityHttpException(sprintf(
                'The sum of payment allocations (%s) exceeds the payment amount (%s).',
                $total,
                $amount,
            ));
        }
    }

    /**
     * Queue the payment-recorded email to the Buyer's contact address using the
     * active tenant's sender identity (R24.4, R3.3). No-op when the Buyer has no
     * contact email.
     */
    private function sendPaymentRecordedEmail(Model $buyer, Payment $payment): void
    {
        $contactEmail = $buyer->contact_email ?? null;

        if (empty($contactEmail)) {
            return;
        }

        $brandName = $this->whiteLabel->get()['brand_name'] ?? null;

        Mail::to($contactEmail)->queue(new PaymentRecordedMail(
            buyerName: (string) ($buyer->name ?? ''),
            amount: (string) $payment->amount,
            method: (string) $payment->method,
            paidAt: $payment->paid_at->toDateString(),
            outstandingBalance: $this->ledger->outstandingBalance($buyer),
            reference: $payment->reference,
            brandName: $brandName,
        ));
    }

    /**
     * Record the in-app payment-recorded notification for the user that
     * recorded the payment, and offer the optional SMS/WhatsApp channels to the
     * buyer's phone where the `whatsapp_notifications` flag is enabled (R27.4,
     * R27.5, R27.6). Best-effort and post-commit; no-op when the notifications
     * service is not wired (e.g. a unit test constructing the service directly).
     */
    private function recordPaymentRecordedNotification(Model $buyer, Payment $payment): void
    {
        if ($this->notifications === null) {
            return;
        }

        $userId = $payment->recorded_by ?? auth()->id();

        if ($userId === null) {
            return;
        }

        $this->notifications->notify(
            userId: (int) $userId,
            type: Notification::TYPE_PAYMENT_RECORDED,
            title: 'Payment recorded',
            body: sprintf('Payment of %s recorded for %s.', (string) $payment->amount, (string) ($buyer->name ?? '')),
            data: [
                'payment_id' => (int) $payment->getKey(),
                'amount' => (string) $payment->amount,
                'method' => (string) $payment->method,
            ],
            phone: $buyer->contact_phone ?? null,
        );
    }

    /**
     * Resolve the payment date, defaulting to today when not supplied (R24.1).
     */
    private function resolvePaidAt(string|\DateTimeInterface|null $paidAt): Carbon
    {
        return $paidAt === null ? Carbon::today() : Carbon::parse($paidAt);
    }

    /**
     * Guard: the payment amount must be strictly positive (R24.1).
     *
     * @throws UnprocessableEntityHttpException When the amount is not positive.
     */
    private function assertPositive(string $amount): void
    {
        if (! $this->isPositive($amount)) {
            throw new UnprocessableEntityHttpException('The payment amount must be greater than zero.');
        }
    }

    // ---------------------------------------------------------------------
    // Exact fixed-scale (DECIMAL(15,2)) decimal arithmetic. Mirrors
    // AccountLedgerService so monetary comparisons/sums are never float-based.
    // ---------------------------------------------------------------------

    private const SCALE = 2;

    private function normalizeAmount(string $value): string
    {
        return $this->add($value, '0');
    }

    private function isPositive(string $value): bool
    {
        return $this->compare($value, '0') > 0;
    }

    private function add(string $a, string $b): string
    {
        if (function_exists('bcadd')) {
            return bcadd($a, $b, self::SCALE);
        }

        return $this->fromScaledInt($this->toScaledInt($a) + $this->toScaledInt($b));
    }

    private function compare(string $a, string $b): int
    {
        if (function_exists('bccomp')) {
            return bccomp($a, $b, self::SCALE);
        }

        return $this->toScaledInt($a) <=> $this->toScaledInt($b);
    }

    private function toScaledInt(string $value): int
    {
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');

        $parts = explode('.', $value, 2);
        $whole = $parts[0] === '' ? '0' : $parts[0];
        $frac = $parts[1] ?? '';
        $frac = substr(str_pad($frac, self::SCALE, '0'), 0, self::SCALE);

        $scaled = (int) ($whole.$frac);

        return $negative ? -$scaled : $scaled;
    }

    private function fromScaledInt(int $scaled): string
    {
        $negative = $scaled < 0;
        $scaled = abs($scaled);

        $digits = str_pad((string) $scaled, self::SCALE + 1, '0', STR_PAD_LEFT);
        $whole = substr($digits, 0, -self::SCALE);
        $frac = substr($digits, -self::SCALE);

        return ($negative ? '-' : '').$whole.'.'.$frac;
    }
}
