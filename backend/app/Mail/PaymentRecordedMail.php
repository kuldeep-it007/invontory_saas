<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Payment-recorded notification sent when a manual Payment is recorded against
 * a Buyer's account (R24.4, R27.4).
 *
 * Queued ({@see ShouldQueue}) so the slow mail send never blocks the
 * payment-recording response, and sent using the active Tenant's own outbound
 * sender identity via {@see AppliesTenantSender} (R3.3) — the `from` is injected
 * by {@see AppliesTenantSender::tenantEnvelope()} when the envelope does not set
 * one explicitly. Mirrors {@see AccountCreatedMail} one-to-one in style.
 */
class PaymentRecordedMail extends Mailable implements ShouldQueue
{
    use AppliesTenantSender;
    use Queueable;
    use SerializesModels;

    /**
     * @param  string  $buyerName  The Buyer the payment was recorded against.
     * @param  string  $amount  The formatted payment amount.
     * @param  string  $method  The payment method (cash, cheque, ...).
     * @param  string  $paidAt  The payment date (ISO date string).
     * @param  string  $outstandingBalance  The Buyer's Outstanding_Balance after the payment (latest balance_after).
     * @param  string|null  $reference  The payment's external reference, when provided.
     * @param  string|null  $brandName  The tenant brand name for the greeting, when known.
     */
    public function __construct(
        public readonly string $buyerName,
        public readonly string $amount,
        public readonly string $method,
        public readonly string $paidAt,
        public readonly string $outstandingBalance,
        public readonly ?string $reference = null,
        public readonly ?string $brandName = null,
    ) {}

    public function envelope(): Envelope
    {
        // tenantEnvelope() stamps the active tenant's sender identity on `from`
        // (R3.3) when the envelope has not set one.
        return $this->tenantEnvelope(new Envelope(
            subject: 'Payment recorded',
        ));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.payment-recorded',
            with: [
                'buyerName' => $this->buyerName,
                'amount' => $this->amount,
                'method' => $this->method,
                'paidAt' => $this->paidAt,
                'outstandingBalance' => $this->outstandingBalance,
                'reference' => $this->reference,
                'brandName' => $this->brandName,
            ],
        );
    }
}
