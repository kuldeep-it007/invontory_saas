<?php

use App\Logging\CreateArchiveLogger;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | Log archive (master bucket) settings
    |--------------------------------------------------------------------------
    | Retention cannot be set below 365 days. `path` is resolved here, before
    | tenancy can re-root storage_path() for a tenant.
    */
    'archive' => [
        'path' => storage_path('logs/archive'),
        'retention_days' => max(365, (int) env('LOG_ARCHIVE_RETENTION_DAYS', 400)),
        'access_log' => (bool) env('LOG_ARCHIVE_ACCESS_LOG', true),
        'slow_query_ms' => (int) env('LOG_SLOW_QUERY_MS', 500),
        'alert_email' => env('LOG_ALERT_EMAIL'),
        'tenant_prefix' => 'tenant-logs',
        'platform_prefix' => 'platform-logs',
        'timezone' => env('LOG_ARCHIVE_TIMEZONE', 'Asia/Kolkata'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that is utilized to write
    | messages to your logs. The value provided here should match one of
    | the channels present in the list of "channels" configured below.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Laravel
    | utilizes the Monolog PHP logging library, which includes a variety
    | of powerful log handlers and formatters that you're free to use.
    |
    | Available drivers: "single", "daily", "monthly", "slack", "syslog",
    |                    "errorlog", "monolog", "custom", "stack"
    |
    */

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            // The archive channel is always appended (unless disabled), so every
            // environment ships logs to the master bucket without editing LOG_STACK.
            'channels' => array_values(array_unique(array_merge(
                explode(',', (string) env('LOG_STACK', 'single')),
                env('LOG_ARCHIVE_ENABLED', true) ? ['archive'] : [],
            ))),
            'ignore_exceptions' => false,
        ],

        // Tenant-segregated JSON lines (IST), shipped daily to the master bucket by
        // `logs:archive`. Its own level: production keeps LOG_LEVEL=warning for the
        // text log while the archive still records info-level audit and access events.
        'archive' => env('LOG_ARCHIVE_ENABLED', true) ? [
            'driver' => 'custom',
            'via' => CreateArchiveLogger::class,
            'path' => storage_path('logs/archive'),
            'level' => env('LOG_ARCHIVE_LEVEL', 'info'),
            'timezone' => env('LOG_ARCHIVE_TIMEZONE', 'Asia/Kolkata'),
        ] : [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'max_files' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],

        'monthly' => [
            'driver' => 'monthly',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'max_files' => 3,
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('LOG_SLACK_USERNAME', env('APP_NAME', 'Laravel')),
            'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

];
