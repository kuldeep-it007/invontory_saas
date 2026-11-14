<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CustomDomainSslService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('domains:ssl-list')]
#[Description('List active custom domains for the host certbot script (lines "KEEP <host>" / "ISSUE <host>", then "END")')]
class DomainsSslList extends Command
{
    public function handle(CustomDomainSslService $ssl): int
    {
        $work = $ssl->work();

        foreach ($work['keep'] as $host) {
            $this->line('KEEP '.$host);
        }

        foreach ($work['issue'] as $host) {
            $this->line('ISSUE '.$host);
        }

        // Sentinel: the host script refuses to delete anything from a truncated listing.
        $this->line('END');

        return self::SUCCESS;
    }
}
