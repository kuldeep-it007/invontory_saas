<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Stancl\Tenancy\Database\Models\Domain as BaseDomain;
use Stancl\Tenancy\Exceptions\DomainOccupiedByOtherTenantException;

/**
 * Central-database Domain model (stancl/tenancy).
 *
 * Maps a subdomain or custom domain to a {@see Tenant}. This is the resolution
 * table consulted on every tenant request to identify the tenant and switch
 * the default connection to that tenant's database (R1.4, R3.4).
 *
 * Only rows with status `active` resolve. A custom domain starts `pending`
 * and becomes `active` once its DNS ownership record is verified.
 *
 * `active_key` mirrors `domain` while the row is active (NULL otherwise) and is
 * UNIQUE, so many tenants may hold unverified claims on one host but only one
 * can ever serve it.
 *
 * @property int $id
 * @property string $domain
 * @property string $tenant_id
 * @property string $type
 * @property string $status
 * @property string|null $verification_token
 * @property bool $routing_ok
 * @property bool $is_primary
 * @property string $ssl_status
 */
class Domain extends BaseDomain
{
    public const TYPE_PLATFORM = 'platform';

    public const TYPE_CUSTOM = 'custom';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DISABLED = 'disabled';

    public const SSL_NONE = 'none';

    public const SSL_PENDING = 'pending';

    public const SSL_ISSUED = 'issued';

    public const SSL_FAILED = 'failed';

    protected $attributes = [
        'type' => self::TYPE_PLATFORM,
        'status' => self::STATUS_ACTIVE,
    ];

    protected $hidden = ['verification_token'];

    protected function casts(): array
    {
        return [
            'routing_ok' => 'boolean',
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'expires_at' => 'datetime',
            'ssl_expires_at' => 'datetime',
            'ssl_checked_at' => 'datetime',
        ];
    }

    /**
     * Replaces stancl's "domain is globally unique" guard (same method name, so
     * Eloquent boots this one). Occupation now means another ACTIVE row holds the
     * host; the active_key UNIQUE index backs this up against races.
     */
    public static function bootEnsuresDomainIsNotOccupied(): void
    {
        static::saving(function (self $domain): void {
            $domain->active_key = $domain->status === self::STATUS_ACTIVE ? $domain->domain : null;

            if ($domain->active_key === null) {
                return;
            }

            $occupied = static::query()
                ->where('active_key', $domain->active_key)
                ->when($domain->exists, fn (Builder $query) => $query->whereKeyNot($domain->getKey()))
                ->exists();

            if ($occupied) {
                throw new DomainOccupiedByOtherTenantException($domain->domain);
            }
        });

    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCustom(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_CUSTOM);
    }

    public function isCustom(): bool
    {
        return $this->type === self::TYPE_CUSTOM;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** DNS name of the ownership TXT record, e.g. `_inventory-verify.shop.example.com`. */
    public function txtRecordName(): string
    {
        return config('domains.txt_prefix').'.'.$this->domain;
    }

    /** Exact value the ownership TXT record must contain. */
    public function txtRecordValue(): string
    {
        return config('domains.txt_value_prefix').$this->verification_token;
    }

    /** Whether the stored value is a complete hostname (not a bare subdomain label). */
    public function isFullHost(): bool
    {
        return str_contains($this->domain, '.');
    }
}
