<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantAuditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Tenant-owned in-app notification (tenant database `notifications` table —
 * R27.6).
 *
 * A row is one entry in a user's in-app notification centre: the human
 * title/body, a stable machine `type` for the originating event, optional
 * structured `data`, and a read-state (`read_at` null while unread). Rows are
 * the in-app mirror of the queued email side-effects (R27.1–R27.4) and, where
 * the `whatsapp_notifications` flag is enabled, of the optional SMS/WhatsApp
 * channels (R27.5).
 *
 * Isolation is physical, so there is no `tenant_id` column; the row lives in
 * the active tenant database and the owning user is captured in `user_id`. The
 * model is {@see TenantAuditable}, so creating/updating a notification is
 * recorded in the tenant `audit_logs` table (R34.1).
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $title
 * @property string|null $body
 * @property array<string, mixed>|null $data
 * @property \Illuminate\Support\Carbon|null $read_at
 */
class Notification extends Model implements Auditable
{
    use TenantAuditable;

    /**
     * Canonical notification type keys. Exposed as constants so dispatchers and
     * the UI reference one source of truth rather than raw strings (R27.1–R27.4).
     */
    public const TYPE_ACCOUNT_CREATED = 'account_created';

    public const TYPE_ORDER_PLACED = 'order_placed';

    public const TYPE_ORDER_CONFIRMED = 'order_confirmed';

    public const TYPE_PAYMENT_RECORDED = 'payment_recorded';

    protected $table = 'notifications';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    /**
     * Whether this notification is still unread (R27.6).
     */
    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    /**
     * Scope to a single user's own notifications — the per-user notification
     * centre (R27.6).
     *
     * @param  Builder<Notification>  $query
     * @return Builder<Notification>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to the unread notifications (read_at IS NULL) — the set
     * mark-all-read clears and the unread count counts (R27.6).
     *
     * @param  Builder<Notification>  $query
     * @return Builder<Notification>
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }
}
