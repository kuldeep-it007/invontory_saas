<?php

declare(strict_types=1);

namespace App\Logging;

use DateTimeZone;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Writes one JSON line per record into a per-tenant (or platform) daily file:
 *
 *   {base}/tenants/{tenant_id}/{Y-m-d}.jsonl
 *   {base}/platform/{Y-m-d}.jsonl
 *
 * Dates and timestamps are in IST. `logs:archive` ships finished days to the
 * master bucket. The base path is fixed when the config loads, because tenancy
 * re-roots storage_path() per tenant.
 */
class ArchiveLogHandler extends AbstractProcessingHandler
{
    private DateTimeZone $timezone;

    public function __construct(private readonly string $basePath, string $timezone = 'Asia/Kolkata', int|string|Level $level = Level::Info)
    {
        parent::__construct($level, true);
        $this->timezone = new DateTimeZone($timezone);
    }

    protected function write(LogRecord $record): void
    {
        $at = $record->datetime->setTimezone($this->timezone);
        $tenantId = $record->extra['tenant_id'] ?? $record->context['tenant_id'] ?? null;
        $tenantId = is_string($tenantId) && preg_match('/^[A-Za-z0-9_-]{1,64}$/', $tenantId) === 1 ? $tenantId : null;

        $dir = $tenantId !== null ? $this->basePath.'/tenants/'.$tenantId : $this->basePath.'/platform';
        if (! is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        $line = json_encode([
            'ts' => $at->format('Y-m-d\TH:i:s.vP'),
            'level' => $record->level->getName(),
            'channel' => $record->channel,
            'event' => $record->context['event'] ?? $record->message,
            'message' => $record->message,
            'tenant_id' => $tenantId,
            'user_id' => $record->extra['user_id'] ?? null,
            'request_id' => $record->extra['request_id'] ?? null,
            'ip' => $record->extra['ip'] ?? null,
            'context' => $record->context === [] ? null : $record->context,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        @file_put_contents($dir.'/'.$at->format('Y-m-d').'.jsonl', $line."\n", FILE_APPEND | LOCK_EX);
    }
}
