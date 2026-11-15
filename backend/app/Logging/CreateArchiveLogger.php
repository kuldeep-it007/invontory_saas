<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\Logger;

/**
 * `archive` log channel: JSON lines per tenant and day, masked and enriched
 * with tenant / user / request id / IP. See config/logging.php.
 */
class CreateArchiveLogger
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __invoke(array $config): Logger
    {
        $handler = new ArchiveLogHandler(
            (string) $config['path'],
            (string) ($config['timezone'] ?? 'Asia/Kolkata'),
            (string) ($config['level'] ?? 'info'),
        );
        // Handler-level, so masking also holds when the channel is used inside a stack.
        $handler->pushProcessor(new MaskSensitiveData);
        $handler->pushProcessor(new AddRequestContext);

        return new Logger('archive', [$handler], [new AddRequestContext, new MaskSensitiveData]);
    }
}
