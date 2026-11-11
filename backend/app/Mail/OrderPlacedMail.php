<?php

declare(strict_types=1);

namespace App\Mail;

use App\Services\CartSubmissionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Order-placed notification sent when a Buyer submits a cart into an Order
 * (R27.2). Dispatched by {@see CartSubmissionService} AFTER the
 * cart-submission transaction commits, so the email is never queued for a
 * submission that rolled back (R27.2, R35.2).
 *
 * Queued ({@see ShouldQueue}) so the slow mail send never blocks the
 * order-placement response, and sent using the active Tenant's own outbound
 * sender identity via {@see AppliesTenantSender} (R3.3). Mirrors
 * {@see OrderConfirmedMail} / {@see PaymentRecordedMail} one-to-one in style.
 */
class OrderPlacedMail extends Mailable implements ShouldQueue
{
    use AppliesTenantSender;
    use Queueable;
    use SerializesModels;

    /**
     * @param  string  $buyerName  The Buyer that placed the order.
     * @param  int  $orderId  The placed order's id.
     * @param  string  $amount  The formatted placed order total.
     * @param  string|null  $brandName  The tenant brand name for the greeting, when known.
     */
    public function __construct(
        public readonly string $buyerName,
        public readonly int $orderId,
        public readonly string $amount,
        public readonly ?string $brandName = null,
    ) {}

    public function envelope(): Envelope
    {
        // tenantEnvelope() stamps the active tenant's sender identity on `from`
        // (R3.3) when the envelope has not set one.
        return $this->tenantEnvelope(new Envelope(
            subject: 'Order placed',
        ));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.order-placed',
            with: [
                'buyerName' => $this->buyerName,
                'orderId' => $this->orderId,
                'amount' => $this->amount,
                'brandName' => $this->brandName,
            ],
        );
    }
}
