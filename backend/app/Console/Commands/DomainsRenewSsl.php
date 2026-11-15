<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Domain;
use App\Services\CustomDomainSslService;
use App\Support\HostName;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

#[Signature('domains:renew-ssl {domain : The active custom domain}')]
#[Description('Queue a forced HTTPS certificate renewal for a custom domain (server operator; same checks and audit as the platform console)')]
class DomainsRenewSsl extends Command
{
    public function handle(CustomDomainSslService $ssl): int
    {
        $host = HostName::normalize((string) $this->argument('domain'));
        $domain = $host !== null ? Domain::query()->active()->custom()->where('domain', $host)->first() : null;

        if ($domain === null) {
            $this->error('No active custom domain '.($host ?? (string) $this->argument('domain')).'.');

            return self::FAILURE;
        }

        try {
            $ssl->requestRenewal($domain);
        } catch (ConflictHttpException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Renewal queued for {$domain->domain}.");

        return self::SUCCESS;
    }
}
