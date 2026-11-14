<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Services\TenantIntegrations\IntegrationConfig;
use App\Services\TenantIntegrations\TenantIntegrationStore;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;
use Throwable;

/**
 * Applies a tenant's own SMTP server and object storage for as long as tenancy
 * is initialised, then puts the platform defaults back.
 *
 * Runs for web requests and for queue jobs alike (the queue bootstrapper
 * initialises tenancy per job), so every mailable, password reset and upload
 * picks the right settings with no per-call code, and no credentials travel in
 * a job payload.
 *
 * Mail: the tenant's SMTP is the first choice, wrapped in Laravel's `failover`
 * transport with the platform's default mailer behind it, so a tenant server
 * that is down (or no tenant SMTP at all) never stops mail from going out.
 *
 * Both the mailer and the disk are rebuilt on every initialisation and
 * discarded on revert. Laravel caches them by name, so without that a long
 * running worker would send the next tenant's mail through the previous
 * tenant's server.
 */
class TenantIntegrationsBootstrapper implements TenancyBootstrapper
{
    private const SMTP_MAILER = 'tenant_smtp';

    private const FAILOVER_MAILER = 'tenant_mail';

    /** @var array{default: mixed, from: mixed}|null */
    private ?array $original = null;

    public function __construct(private readonly TenantIntegrationStore $store) {}

    public function bootstrap(Tenant $tenant): void
    {
        $this->revert();

        $id = (string) $tenant->getTenantKey();

        try {
            $smtp = $this->store->get(TenantIntegrationStore::SMTP, $id);
            $storage = $this->store->get(TenantIntegrationStore::STORAGE, $id);
        } catch (Throwable) {
            // The tenant database or its tables may not exist yet (provisioning).
            return;
        }

        $this->original = ['default' => config('mail.default'), 'from' => config('mail.from')];

        if ($smtp !== null && ($smtp['enabled'] ?? false)) {
            $this->applySmtp($smtp);
        }

        if ($storage !== null && ($storage['enabled'] ?? false)) {
            config(['filesystems.disks.'.IntegrationConfig::DISK => IntegrationConfig::disk($storage)]);
            Storage::forgetDisk(IntegrationConfig::DISK);
        }
    }

    public function revert(): void
    {
        if ($this->original === null) {
            return;
        }

        config([
            'mail.default' => $this->original['default'],
            'mail.from' => $this->original['from'],
            'mail.mailers.'.self::SMTP_MAILER => null,
            'mail.mailers.'.self::FAILOVER_MAILER => null,
            'filesystems.disks.'.IntegrationConfig::DISK => null,
            'integrations.smtp_active' => false,
        ]);

        $this->forgetMailers();
        Storage::forgetDisk(IntegrationConfig::DISK);
        $this->original = null;
    }

    /**
     * @param  array<string, mixed>  $smtp
     */
    private function applySmtp(array $smtp): void
    {
        config([
            'mail.mailers.'.self::SMTP_MAILER => IntegrationConfig::mailer($smtp),
            'mail.mailers.'.self::FAILOVER_MAILER => [
                'transport' => 'failover',
                'mailers' => [self::SMTP_MAILER, $this->original['default']],
                'retry_after' => 60,
            ],
            'mail.default' => self::FAILOVER_MAILER,
            // Password-reset and other notification mail has no per-mailable sender.
            'mail.from' => [
                'address' => $smtp['from_address'],
                'name' => ($smtp['from_name'] ?? '') !== '' ? $smtp['from_name'] : ($this->original['from']['name'] ?? null),
            ],
            'integrations.smtp_active' => true,
        ]);

        $this->forgetMailers();
    }

    private function forgetMailers(): void
    {
        Mail::purge(self::SMTP_MAILER);
        Mail::purge(self::FAILOVER_MAILER);
    }
}
