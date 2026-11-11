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
 * Account-created notification sent when a Distributor or Dealer account is
 * created from an approved prospect (R9.4, R27.1).
 *
 * Queued ({@see ShouldQueue}) so the slow mail send never blocks the approval
 * response, and sent using the active Tenant's own outbound sender identity via
 * {@see AppliesTenantSender} (R3.3) — the `from` is injected by
 * {@see AppliesTenantSender::tenantEnvelope()} when the envelope does not set
 * one explicitly.
 */
class AccountCreatedMail extends Mailable implements ShouldQueue
{
    use AppliesTenantSender;
    use Queueable;
    use SerializesModels;

    /**
     * @param  string  $accountName  The created buyer's display name.
     * @param  string  $buyerType  'distributor' | 'dealer'.
     * @param  string|null  $brandName  The tenant brand name for the greeting, when known.
     */
    public function __construct(
        public readonly string $accountName,
        public readonly string $buyerType,
        public readonly ?string $brandName = null,
    ) {}

    public function envelope(): Envelope
    {
        // tenantEnvelope() stamps the active tenant's sender identity on `from`
        // (R3.3) when the envelope has not set one.
        return $this->tenantEnvelope(new Envelope(
            subject: 'Your account has been created',
        ));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.account-created',
            with: [
                'accountName' => $this->accountName,
                'buyerType' => $this->buyerType,
                'brandName' => $this->brandName,
            ],
        );
    }
}
