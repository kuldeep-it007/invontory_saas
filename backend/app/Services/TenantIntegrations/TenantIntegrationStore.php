<?php

declare(strict_types=1);

namespace App\Services\TenantIntegrations;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Encrypted per-tenant integration settings (SMTP, object storage).
 *
 * Each setting is one JSON document encrypted with the application key and kept
 * in the TENANT's own `tenant_settings` table, so it is physically isolated with
 * the rest of the tenant's data. The document carries its tenant id and is
 * rejected on any other tenant, so a copied row is useless.
 *
 * Rows are written with the query builder, not the audited TenantSetting model,
 * so secrets never reach `audit_logs`.
 */
class TenantIntegrationStore
{
    public const SMTP = 'smtp';

    public const STORAGE = 'storage';

    private const KEYS = [
        self::SMTP => 'smtp_config',
        self::STORAGE => 'storage_config',
    ];

    /**
     * @return array<string, mixed>|null The decrypted settings, or null when unset, unreadable, or bound to another tenant.
     */
    public function get(string $type, string $tenantId): ?array
    {
        $raw = DB::table('tenant_settings')->where('key', self::KEYS[$type])->value('value');

        if ($raw === null || $raw === '') {
            return null;
        }

        try {
            $data = json_decode(Crypt::decryptString((string) $raw), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            Log::warning('tenant_integrations.unreadable', ['tenant_id' => $tenantId, 'type' => $type]);

            return null;
        }

        if (! is_array($data) || ($data['_tenant'] ?? null) !== $tenantId) {
            Log::warning('tenant_integrations.tenant_mismatch', ['tenant_id' => $tenantId, 'type' => $type]);

            return null;
        }

        unset($data['_tenant']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function put(string $type, string $tenantId, array $config): void
    {
        $value = Crypt::encryptString(json_encode($config + ['_tenant' => $tenantId], JSON_THROW_ON_ERROR));
        $query = DB::table('tenant_settings')->where('key', self::KEYS[$type]);

        if ($query->exists()) {
            $query->update(['value' => $value, 'updated_at' => now()]);

            return;
        }

        DB::table('tenant_settings')->insert([
            'key' => self::KEYS[$type],
            'value' => $value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Merge top-level keys into the stored document (e.g. copy progress).
     *
     * @param  array<string, mixed>  $changes
     */
    public function patch(string $type, string $tenantId, array $changes): void
    {
        $current = $this->get($type, $tenantId);

        if ($current !== null) {
            $this->put($type, $tenantId, array_merge($current, $changes));
        }
    }

    public function forget(string $type): void
    {
        DB::table('tenant_settings')->where('key', self::KEYS[$type])->delete();
    }
}
