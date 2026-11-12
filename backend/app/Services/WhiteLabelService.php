<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\AppliesTenantSender;
use App\Models\Company;
use App\Models\TenantSetting;
use App\Support\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * White-label settings domain service (R3).
 *
 * Reads and updates a Tenant's white-label settings as one coherent object
 * composed from the key/value {@see TenantSetting} rows in the active tenant
 * database (R3.1): logo, brand name, colour theme, favicon, custom domain, and
 * the outbound email sender identity (from address + from name).
 *
 * Thin controllers (`GET` / `PATCH /tenant/settings`) delegate here; the
 * service owns the key allow-list, the read/compose logic, and the
 * apply-then-best-effort-audit update flow.
 *
 * **Audit is best-effort (R3.5).** A white-label update applies the settings
 * first and commits them, then records the change through {@see AuditRecorder}
 * (which itself swallows failures) OUTSIDE the write transaction. The update is
 * never rolled back because the audit write failed — settings are applied
 * regardless of whether the Audit_Log write succeeds.
 *
 * **Per-tenant mailer sender identity (R3.3).** {@see senderIdentity()} resolves
 * the configured outbound `from` address/name for the active tenant so outbound
 * mail uses that Tenant's identity. The mail hook that consumes it is documented
 * on {@see AppliesTenantSender}.
 */
class WhiteLabelService
{
    /**
     * The white-label setting keys this service manages, mapped to their
     * tenant_settings row `key`. Only these keys may be read or written through
     * the white-label surface; anything else is ignored (R3.1).
     *
     * @var array<string, string>
     */
    private const KEYS = [
        'logo' => 'logo',
        'brand_name' => 'brand_name',
        'color_theme' => 'color_theme',
        'favicon' => 'favicon',
        'custom_domain' => 'custom_domain',
        'mail_from_address' => 'mail_from_address',
        'mail_from_name' => 'mail_from_name',
    ];

    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * Return the coherent white-label settings object for the active tenant.
     *
     * Every managed key is present in the result; keys with no stored row
     * resolve to null, so callers (API resource, theme loader) get a stable
     * shape (R3.1, R3.2).
     *
     * @return array<string, string|null>
     */
    public function get(): array
    {
        $stored = TenantSetting::query()
            ->whereIn('key', array_values(self::KEYS))
            ->pluck('value', 'key');

        $settings = [];
        foreach (self::KEYS as $field => $key) {
            $settings[$field] = $stored[$key] ?? null;
        }

        return $settings;
    }

    /**
     * Apply a partial white-label update and return the full, refreshed settings
     * object.
     *
     * Only recognised keys from {@see KEYS} are persisted; unknown keys are
     * ignored. A `null` value clears the setting. The upserts run inside a
     * single transaction so the write is all-or-nothing; the audit entry is
     * recorded afterwards and best-effort — a failed audit write never undoes
     * the applied settings (R3.5).
     *
     * @param  array<string, string|null>  $changes  Partial map of field => value.
     * @return array<string, string|null> The full settings object after applying.
     */
    public function update(array $changes): array
    {
        $before = $this->get();

        // Keep only recognised white-label keys.
        $applied = array_intersect_key($changes, self::KEYS);

        // 1) Apply + commit the settings. This MUST succeed/rollback on its own
        //    merits, independent of auditing.
        DB::transaction(function () use ($applied): void {
            foreach ($applied as $field => $value) {
                TenantSetting::query()->updateOrCreate(
                    ['key' => self::KEYS[$field]],
                    ['value' => $value],
                );
            }
        });

        $after = $this->get();

        // 2) Best-effort audit AFTER the settings are durably applied (R3.5).
        //    AuditRecorder already swallows failures; the extra guard documents
        //    the invariant that no audit failure may affect the applied update.
        try {
            $this->audit->record('white_label_updated', auth()->user(), [
                'changed' => array_keys($applied),
                'old_values' => array_intersect_key($before, $applied),
                'new_values' => array_intersect_key($after, $applied),
            ]);
        } catch (Throwable $e) {
            // Intentionally ignored: the update has already been applied (R3.5).
        }

        return $after;
    }

    /**
     * The PUBLIC branding for the active tenant: only what a visitor's browser
     * needs to render the workspace (name, logo, favicon, colours). Safe to
     * serve without authentication — the login page needs it before anyone has
     * signed in. Mail sender settings and the custom domain are deliberately
     * excluded.
     *
     * When no brand name is configured, the tenant's Company name is used, so a
     * workspace is always labelled with the company it belongs to.
     *
     * @return array{brand_name: string|null, logo: string|null, favicon: string|null, color_theme: string|null}
     */
    public function publicBranding(): array
    {
        $settings = $this->get();

        $brandName = $settings['brand_name'];
        if ($brandName === null || trim($brandName) === '') {
            $brandName = Company::query()->value('name');
        }

        return [
            'brand_name' => $brandName,
            'logo' => $settings['logo'],
            'favicon' => $settings['favicon'],
            'color_theme' => $settings['color_theme'],
        ];
    }

    /**
     * Resolve the active tenant's outbound email sender identity (R3.3).
     *
     * Returns the configured `from` address and name for the tenant, falling
     * back to the application's global `mail.from` config when a tenant has not
     * set its own identity. The mail hook {@see AppliesTenantSender}
     * applies this to every outbound Mailable so a Tenant's mail is sent from
     * that Tenant's identity.
     *
     * @return array{address: string|null, name: string|null}
     */
    public function senderIdentity(): array
    {
        $settings = $this->get();

        return [
            'address' => $settings['mail_from_address'] ?: config('mail.from.address'),
            'name' => $settings['mail_from_name'] ?: config('mail.from.name'),
        ];
    }
}
