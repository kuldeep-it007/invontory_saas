<?php

declare(strict_types=1);

namespace App\Mail;

use App\Services\OrderConfirmationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Order-confirmed notification sent when a Seller confirms a Buyer's Order
 * (R27.3). Dispatched by {@see OrderConfirmationService} AFTER the
 * confirmation transaction commits, so the email is never queued for a
 * confirmation that rolled back (R27.3, design "Confirmation").
 *
 * Queued ({@see ShouldQueue}) so the slow mail send never blocks the
 * confirmation response, and sent using the active Tenant's own outbound sender
 * identity via {@see AppliesTenantSender} (R3.3). Mirrors
 * {@see PaymentRecordedMail} one-to-one in style.
 */
class OrderConfirmedMail extends Mailable implements ShouldQueue
{
    use AppliesTenantSender;
    use Queueable;
    use SerializesModels;

    /**
     * @param  string  $buyerName  The Buyer whose order was confirmed.
     * @param  int  $orderId  The confirmed order's id.
     * @param  string  $invoiceSeries  The seller's invoice series the invoice was issued under.
     * @param  int  $invoiceNumber  The strictly-increasing per-series invoice number.
     * @param  string  $amount  The formatted confirmed order total (the invoice amount).
     * @param  string|null  $brandName  The tenant brand name for the greeting, when known.
     */
    public function __construct(
        public readonly string $buyerName,
        public readonly int $orderId,
        public readonly string $invoiceSeries,
        public readonly int $invoiceNumber,
        public readonly string $amount,
        public readonly ?string $brandName = null,
    ) {}

    public function envelope(): Envelope
    {
        // tenantEnvelope() stamps the active tenant's sender identity on `from`
        // (R3.3) when the envelope has not set one.
        return $this->tenantEnvelope(new Envelope(
            subject: 'Order confirmed',
        ));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.order-confirmed',
            with: [
                'buyerName' => $this->buyerName,
                'orderId' => $this->orderId,
                'invoiceSeries' => $this->invoiceSeries,
                'invoiceNumber' => $this->invoiceNumber,
                'amount' => $this->amount,
                'brandName' => $this->brandName,
            ],
        );
    }
}
