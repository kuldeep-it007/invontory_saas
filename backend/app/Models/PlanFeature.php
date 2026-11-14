<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Central-database PlanFeature model.
 *
 * A Feature_Flag is a PER-PLAN BOOLEAN that gates optional modules (glossary,
 * R4.5). Modelled separately from {@see PlanLimit} because a quota's 0/NULL
 * already means "unlimited" (R4.4), which would clash with a flag's "disabled".
 * Lives in the central database alongside plans.
 */
class PlanFeature extends Model
{
    protected $connection = 'central';

    /**
     * Supported feature-flag keys (R4.5). Exposed as constants so callers
     * (feature-flag guard, seeders) reference a single source of truth instead
     * of raw strings. The authoritative allow-list is {@see self::FEATURE_KEYS}.
     */
    public const WHATSAPP_NOTIFICATIONS = 'whatsapp_notifications';

    public const OTP_LOGIN = 'otp_login';

    public const ADVANCE_ORDERS = 'advance_orders';

    public const EXPORTS = 'exports';

    public const API_ACCESS = 'api_access';

    public const AI_REPORTS = 'ai_reports';

    /** Custom domain mapping. Unlike other flags it is ON unless a plan row disables it. */
    public const CUSTOM_DOMAINS = 'custom_domains';

    /** Tenant-owned SMTP server and object storage. Like custom domains, ON unless a plan row disables them. */
    public const CUSTOM_SMTP = 'custom_smtp';

    public const CUSTOM_STORAGE = 'custom_storage';

    /**
     * The documented allow-list of feature flags the platform supports (R4.5).
     * Any key outside this list is not a recognised feature flag.
     *
     * @var list<string>
     */
    public const FEATURE_KEYS = [
        self::WHATSAPP_NOTIFICATIONS,
        self::OTP_LOGIN,
        self::ADVANCE_ORDERS,
        self::EXPORTS,
        self::API_ACCESS,
        self::AI_REPORTS,
        self::CUSTOM_DOMAINS,
        self::CUSTOM_SMTP,
        self::CUSTOM_STORAGE,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plan_id',
        'key',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    /**
     * Whether a feature key is a recognised, supported flag (R4.5).
     */
    public static function isSupportedKey(string $key): bool
    {
        return in_array($key, self::FEATURE_KEYS, true);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
