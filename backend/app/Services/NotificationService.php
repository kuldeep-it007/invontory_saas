<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Notification;
use App\Models\PlanFeature;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Focused service for the notifications domain (R27).
 *
 * Owns the business rules the thin {@see \App\Http\Controllers\NotificationController}
 * and the event dispatchers (prospect approval, cart submission, order
 * confirmation, payment recording) delegate to:
 *
 *   • {@see notify()} records ONE in-app {@see Notification} for a tenant user
 *     (the durable notification-centre history — R27.6) and, WHERE the tenant's
 *     `whatsapp_notifications` feature flag is enabled, additionally offers the
 *     optional SMS/WhatsApp channels (R27.5). The in-app row is always written;
 *     the optional channels degrade gracefully to a no-op when the flag is off
 *     or no gateway is configured, so email + in-app keep working with zero
 *     optional configuration.
 *   • {@see list()} returns a user's own notification history, newest-first and
 *     paginated (R27.6).
 *   • {@see markRead()} marks a SINGLE unread notification read (R27.6).
 *   • {@see markAllRead()} marks ALL of a user's unread notifications read, so
 *     the unread count drops to zero (R27.6).
 *   • {@see unreadCount()} is the unread badge count.
 *
 * All work runs on the active tenant connection (resolved by the tenancy
 * middleware), so notifications always belong to the current tenant (physical
 * isolation). The in-app write never blocks or fails the originating action —
 * dispatchers call {@see notify()} AFTER their own transaction commits, exactly
 * like the queued email side-effects.
 */
class NotificationService
{
    public function __construct(private readonly SubscriptionGateService $gate) {}

    /**
     * Record an in-app notification for a user and, where the tenant's
     * `whatsapp_notifications` flag is enabled, offer the optional SMS/WhatsApp
     * channels too (R27.5, R27.6).
     *
     * The in-app row is ALWAYS persisted (the durable history). The optional
     * channels are attempted only when the flag is on AND a destination phone
     * is supplied; a missing gateway/phone degrades to a logged no-op so the
     * notification is never lost and the caller never fails.
     *
     * @param  int  $userId  The tenant user the notification is addressed to.
     * @param  string  $type  A stable machine key — see the {@see Notification}
     *                        TYPE_* constants.
     * @param  string  $title  The notification-centre title.
     * @param  string|null  $body  The notification-centre body.
     * @param  array<string, mixed>  $data  Optional structured context (order id,
     *                                       invoice number, amount, …).
     * @param  string|null  $phone  Destination phone for the optional SMS/WhatsApp
     *                              channels; ignored when the flag is off.
     */
    public function notify(
        int $userId,
        string $type,
        string $title,
        ?string $body = null,
        array $data = [],
        ?string $phone = null,
    ): Notification {
        $notification = Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data === [] ? null : $data,
        ]);

        // Optional SMS/WhatsApp fan-out ONLY where the tenant flag is enabled
        // (R27.5). Off by default; a disabled flag means in-app + email only.
        if ($phone !== null && $phone !== '' && $this->optionalChannelsEnabled()) {
            $this->sendOptionalChannels($type, $title, $body, $phone);
        }

        return $notification;
    }

    /**
     * Return a user's own notification history, newest-first and paginated
     * (R27.6). Selects only the columns the notification centre renders and
     * filters on the indexed `user_id` (R38.4).
     *
     * @return LengthAwarePaginator<Notification>
     */
    public function list(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage, 100));

        return Notification::query()
            ->select(['id', 'type', 'title', 'body', 'data', 'read_at', 'created_at'])
            ->forUser($userId)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Mark a SINGLE unread notification read for a user (R27.6).
     *
     * Idempotent: an already-read notification stays read and the unread count
     * is unchanged, so marking one read decreases the unread count by AT MOST
     * one (and by exactly one when it was unread). Scoped to the owning user so
     * a user can only mark their OWN notifications read.
     */
    public function markRead(int $userId, int $notificationId): Notification
    {
        /** @var Notification $notification */
        $notification = Notification::query()
            ->forUser($userId)
            ->findOrFail($notificationId);

        if ($notification->isUnread()) {
            $notification->read_at = now();
            $notification->save();
        }

        return $notification;
    }

    /**
     * Mark ALL of a user's unread notifications read, leaving zero unread
     * (R27.6). Returns the number of notifications flipped from unread → read.
     */
    public function markAllRead(int $userId): int
    {
        return (int) Notification::query()
            ->forUser($userId)
            ->unread()
            ->update(['read_at' => now()]);
    }

    /**
     * The user's current unread notification count (the badge count — R27.6).
     */
    public function unreadCount(int $userId): int
    {
        return (int) Notification::query()
            ->forUser($userId)
            ->unread()
            ->count();
    }

    /**
     * Resolve the tenant user id a Buyer's notifications should be addressed to.
     *
     * The in-app notification centre is per tenant USER; a Buyer
     * (Distributor/Dealer) maps to its linked user account when one exists.
     * Returns null when no user can be resolved, so the dispatcher simply skips
     * the in-app row without failing the originating action.
     */
    public function resolveBuyerUserId(Model $buyer): ?int
    {
        $userId = $buyer->user_id ?? null;

        return $userId === null ? null : (int) $userId;
    }

    /**
     * Whether the active tenant offers the optional SMS/WhatsApp channels
     * (R27.5): the `whatsapp_notifications` feature flag must be enabled on the
     * tenant's plan. A tenant with no resolvable plan is treated as DISABLED,
     * matching the "optional modules off by default" convention.
     */
    public function optionalChannelsEnabled(): bool
    {
        return $this->gate->featureEnabled(PlanFeature::WHATSAPP_NOTIFICATIONS);
    }

    /**
     * Fan the notification out over the optional SMS and WhatsApp channels
     * (R27.5). Each channel degrades gracefully: with no gateway configured
     * (the default `log` driver) it records the message rather than calling an
     * external provider, and a `null` driver drops it silently. This keeps the
     * platform booting and notifying with zero optional configuration while the
     * hook is ready for a real gateway to be wired in.
     */
    private function sendOptionalChannels(string $type, string $title, ?string $body, string $phone): void
    {
        $message = trim($title.($body !== null && $body !== '' ? ': '.$body : ''));

        foreach (['sms', 'whatsapp'] as $channel) {
            $this->sendOverChannel($channel, $type, $phone, $message);
        }
    }

    /**
     * Deliver a single message over one optional channel using its configured
     * driver. `log` records the intended send (safe default, no external call);
     * `null` drops it. An unknown driver is treated as `null` so a
     * misconfiguration never throws into the originating action.
     */
    private function sendOverChannel(string $channel, string $type, string $phone, string $message): void
    {
        $driver = (string) config("services.notifications.{$channel}.driver", 'log');

        if ($driver === 'log') {
            Log::info('notification.optional_channel', [
                'channel' => $channel,
                'type' => $type,
                'to' => $phone,
                'message' => $message,
            ]);
        }

        // `null`/unknown drivers: intentional no-op (graceful degradation,
        // R27.5). A real gateway driver would transmit here.
    }
}
