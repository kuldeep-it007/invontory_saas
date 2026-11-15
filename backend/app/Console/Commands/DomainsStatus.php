<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Domain;
use App\Support\HostName;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('domains:status {domain? : One custom domain; omit to list all}')]
#[Description('Show custom domains with DNS, HTTPS and renewal status (used by deploy.sh domain-status)')]
class DomainsStatus extends Command
{
    public function handle(): int
    {
        $host = $this->argument('domain') !== null ? HostName::normalize((string) $this->argument('domain')) : null;
        if ($this->argument('domain') !== null && $host === null) {
            $this->error('Not a valid domain name.');

            return self::INVALID;
        }

        $rows = Domain::query()->custom()
            ->when($host !== null, fn ($q) => $q->where('domain', $host))
            ->orderBy('domain')->orderBy('id')->get()
            ->map(fn (Domain $d): array => [
                $d->domain,
                $d->tenant_id,
                $d->status,
                $d->routing_ok ? 'yes' : 'NO',
                $d->ssl_status,
                $d->ssl_expires_at?->toDateString() ?? '-',
                $d->ssl_attempts,
                $d->ssl_renew_requested_at !== null ? 'queued' : '-',
                $d->last_error ?? '-',
            ]);

        if ($rows->isEmpty()) {
            $this->warn($host !== null ? "No custom domain {$host}." : 'No custom domains.');

            return $host !== null ? self::FAILURE : self::SUCCESS;
        }

        $this->table(['Domain', 'Tenant', 'Status', 'Routes here', 'HTTPS', 'Expires', 'Attempts', 'Renewal', 'Note'], $rows->all());

        return self::SUCCESS;
    }
}
