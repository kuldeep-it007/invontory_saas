<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CustomDomainSslService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('domains:ssl-report')]
#[Description('Record certificate results from the host script. Reads JSON on stdin: [{"host","status":"issued|failed","expires"}]')]
class DomainsSslReport extends Command
{
    public function handle(CustomDomainSslService $ssl): int
    {
        $payload = json_decode((string) stream_get_contents(STDIN), true);

        if (! is_array($payload)) {
            $this->error('Expected a JSON array on stdin.');

            return self::INVALID;
        }

        $this->info('Updated '.$ssl->report($payload).' domain(s).');

        return self::SUCCESS;
    }
}
