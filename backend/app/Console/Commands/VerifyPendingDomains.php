<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\VerifyCustomDomain;
use App\Models\Domain;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('domains:verify-pending {--limit=200 : Maximum domains queued per run}')]
#[Description('Queue DNS checks for pending custom domains (every 5 min for the first hour, then hourly) and daily re-checks of active ones')]
class VerifyPendingDomains extends Command
{
    public function handle(): int
    {
        $queued = 0;

        Domain::query()
            ->custom()
            ->whereIn('status', [Domain::STATUS_PENDING, Domain::STATUS_ACTIVE])
            ->where(fn ($query) => $query->whereNull('last_checked_at')->orWhere('last_checked_at', '<=', now()->subMinutes(5)))
            ->orderBy('id')
            ->lazyById()
            ->each(function (Domain $domain) use (&$queued): bool {
                if (! $this->isDue($domain)) {
                    return true;
                }

                VerifyCustomDomain::dispatch($domain->getKey());

                return ++$queued < (int) $this->option('limit');
            });

        $this->info("Queued {$queued} domain check(s).");

        return self::SUCCESS;
    }

    private function isDue(Domain $domain): bool
    {
        if ($domain->last_checked_at === null) {
            return true;
        }

        $interval = match (true) {
            $domain->isActive() => now()->subDay(),
            $domain->created_at?->gt(now()->subHour()) ?? false => now()->subMinutes(5),
            default => now()->subHour(),
        };

        return $domain->last_checked_at->lte($interval);
    }
}
