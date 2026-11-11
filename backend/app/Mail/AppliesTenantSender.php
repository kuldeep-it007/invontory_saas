<?php

declare(strict_types=1);

namespace App\Mail;

use App\Services\WhiteLabelService;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Per-tenant outbound-mail sender identity hook (R3.3).
 *
 * Mix this trait into any Mailable sent on behalf of a Tenant (account-created,
 * order-placed/-confirmed, payment-recorded, password-reset, etc.). It resolves
 * the active Tenant's configured outbound sender identity from
 * {@see WhiteLabelService::senderIdentity()} and stamps it onto the message
 * envelope's `from`, so the email is sent using that Tenant's identity rather
 * than the application's global `mail.from` default.
 *
 * ## Mechanism
 *
 * Laravel builds a Mailable's {@see Envelope} by calling the Mailable's
 * `envelope()` method. A Mailable that uses this trait wraps its own envelope
 * through {@see tenantEnvelope()}, which injects the resolved tenant `from`
 * address when the envelope does not already specify one:
 *
 * ```php
 * class AccountCreatedMail extends Mailable
 * {
 *     use AppliesTenantSender;
 *
 *     public function envelope(): Envelope
 *     {
 *         return $this->tenantEnvelope(new Envelope(subject: 'Welcome'));
 *     }
 * }
 * ```
 *
 * Because the sender identity is read from the active tenant connection at send
 * time, each Tenant's mail automatically carries that Tenant's `from` identity.
 * When a Tenant has not configured one, {@see WhiteLabelService::senderIdentity()}
 * falls back to the global `mail.from` config, so mail still sends with zero
 * tenant configuration.
 *
 * Notification mail (Laravel `Notification`s) can achieve the same by calling
 * {@see tenantFromAddress()} inside `toMail()` and chaining `->from(...)`.
 */
trait AppliesTenantSender
{
    /**
     * Apply the active tenant's sender identity to an envelope's `from` when the
     * envelope has not already set one explicitly.
     */
    protected function tenantEnvelope(Envelope $envelope): Envelope
    {
        if ($envelope->from !== null) {
            return $envelope;
        }

        $envelope->from = $this->tenantFromAddress();

        return $envelope;
    }

    /**
     * Resolve the active tenant's outbound sender as a mail {@see Address} (R3.3).
     */
    protected function tenantFromAddress(): Address
    {
        $identity = app(WhiteLabelService::class)->senderIdentity();

        return new Address(
            (string) $identity['address'],
            $identity['name'] !== null ? (string) $identity['name'] : null,
        );
    }
}
