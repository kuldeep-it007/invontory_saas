<?php

declare(strict_types=1);

namespace App\Logging;

use App\Http\Middleware\AssignRequestContext;
use Illuminate\Support\Facades\Context;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Stancl\Tenancy\Tenancy;
use Throwable;

/**
 * Stamps tenant, user, request id and source IP onto every log record.
 *
 * The request id and IP come from Laravel's Context (set by
 * {@see AssignRequestContext} and carried into queued jobs
 * automatically). Tenant and user are read at log time, because tenancy and
 * authentication are resolved after the request has started.
 */
class AddRequestContext implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $extra = $record->extra;

        try {
            $extra['request_id'] ??= Context::get('request_id');
            $extra['ip'] ??= Context::get('ip');

            if (function_exists('tenancy') && app()->bound(Tenancy::class) && tenancy()->initialized) {
                $extra['tenant_id'] ??= (string) tenant('id');
            }

            $auth = app('auth');
            if ($auth->hasResolvedGuards() && $auth->guard()->hasUser()) {
                $extra['user_id'] ??= $auth->guard()->id();
            }
        } catch (Throwable) {
            // Context is best-effort; a log line must never fail because of it.
        }

        return $record->with(extra: array_filter($extra, static fn (mixed $value): bool => $value !== null));
    }
}
