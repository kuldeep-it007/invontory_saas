<?php

declare(strict_types=1);

namespace App\Services\TenantIntegrations;

/**
 * Turns a tenant's stored SMTP / storage settings into the config arrays
 * Laravel's mail and filesystem managers understand. The one place that knows
 * the mapping, shared by connection tests and live use.
 */
final class IntegrationConfig
{
    /** The disk name every tenant-storage consumer uses once tenancy is active. */
    public const DISK = 'tenant_storage';

    /**
     * @param  array<string, mixed>  $smtp
     * @return array<string, mixed>
     */
    public static function mailer(array $smtp): array
    {
        $encryption = (string) ($smtp['encryption'] ?? 'tls');

        return array_filter([
            'transport' => 'smtp',
            // ssl = implicit TLS (smtps). tls = STARTTLS that must succeed. none = plain.
            'scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
            'host' => (string) $smtp['host'],
            'port' => (int) $smtp['port'],
            'username' => ($smtp['username'] ?? '') !== '' ? $smtp['username'] : null,
            'password' => ($smtp['password'] ?? '') !== '' ? $smtp['password'] : null,
            'timeout' => (int) config('integrations.timeouts.smtp'),
            'require_tls' => $encryption === 'tls',
            'auto_tls' => $encryption !== 'none',
            'local_domain' => config('mail.mailers.smtp.local_domain'),
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $storage
     * @return array<string, mixed>
     */
    public static function disk(array $storage): array
    {
        return array_filter([
            'driver' => 's3',
            'key' => (string) $storage['access_key'],
            'secret' => (string) $storage['secret_key'],
            'region' => (string) $storage['region'],
            'bucket' => (string) $storage['bucket'],
            'endpoint' => ($storage['endpoint'] ?? '') !== '' ? $storage['endpoint'] : null,
            'use_path_style_endpoint' => (bool) ($storage['path_style'] ?? false),
            'throw' => true,
            'http' => [
                'connect_timeout' => (int) config('integrations.timeouts.storage_connect'),
                'timeout' => (int) config('integrations.timeouts.storage'),
            ],
        ], static fn (mixed $value): bool => $value !== null);
    }
}
