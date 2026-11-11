<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Reporting (R30.1, R30.2, R30.6, R30.7)
    |--------------------------------------------------------------------------
    |
    | The AI reporting layer is gated behind BOTH a platform-wide env master
    | switch (`AI_REPORTS_ENABLED`, default false — R30.1) and a per-tenant
    | `ai_reports` feature flag (R30.7). The composite gate lives in
    | SubscriptionGateService; this entry pins the master switch so it is read
    | from config (cacheable) rather than env() at call time.
    |
    */

    'ai' => [
        'reports_enabled' => (bool) env('AI_REPORTS_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications — optional SMS / WhatsApp channels (R27.5)
    |--------------------------------------------------------------------------
    |
    | In-app and email notifications always run. The SMS and WhatsApp channels
    | are OPTIONAL: they are offered only WHERE the tenant's `whatsapp_
    | notifications` feature flag is enabled (R27.5), and even then they only
    | actually transmit when an outbound gateway is configured here. With no
    | gateway configured (the default), the channel degrades gracefully to a
    | logged no-op so the platform boots and sends in-app/email notifications
    | with zero optional configuration.
    |
    | `driver` selects the delivery transport:
    |   • `log`  — (default) record the message to the log; never calls out.
    |   • `null` — silently drop (used in tests / when disabled).
    |
    */

    'notifications' => [
        'sms' => [
            'driver' => env('SMS_DRIVER', 'log'),
            'from' => env('SMS_FROM'),
        ],
        'whatsapp' => [
            'driver' => env('WHATSAPP_DRIVER', 'log'),
            'from' => env('WHATSAPP_FROM'),
        ],
    ],

];
