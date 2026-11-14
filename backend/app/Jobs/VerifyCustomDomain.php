<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Domain;
use App\Services\CustomDomainService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Re-checks one custom domain's DNS (ownership + routing) on the central queue.
 */
class VerifyCustomDomain implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public readonly int $domainId) {}

    public function handle(CustomDomainService $domains): void
    {
        $domain = Domain::query()->find($this->domainId);

        if ($domain !== null) {
            $domains->verify($domain);
        }
    }
}
