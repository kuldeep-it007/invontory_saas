<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Custom domain mapping
|--------------------------------------------------------------------------
|
| A tenant may map its own domain or subdomain to its workspace. A mapping is
| only served once the tenant proves DNS control (TXT record) and the host
| routes to this platform (CNAME / A record). See App\Services\CustomDomainService.
*/

$central = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CENTRAL_DOMAINS', '127.0.0.1,localhost')),
)));

return [
    // Hostname customers point CNAME records at (subdomains / www). It is a
    // single platform-owned name, so no tenant is ever hardcoded.
    'target_host' => (string) env('CUSTOM_DOMAIN_TARGET_HOST', 'domains.'.($central[0] ?? 'localhost')),

    // Public IPv4 address(es) customers point A records at (root domains).
    // Empty = use whatever the target host resolves to.
    'target_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CUSTOM_DOMAIN_TARGET_IPS', '')),
    ))),

    // TXT record name prefix: <prefix>.<domain> = "<prefix-value>=<token>".
    'txt_prefix' => '_inventory-verify',
    'txt_value_prefix' => 'inventory-verify=',

    // A claim that is still unverified after this many hours is marked failed.
    'pending_ttl_hours' => (int) env('CUSTOM_DOMAIN_PENDING_TTL_HOURS', 72),

    // Used when the tenant's plan has no `max_custom_domains` limit row.
    'max_per_tenant' => (int) env('CUSTOM_DOMAIN_MAX_PER_TENANT', 3),

    // Minimum seconds between manual "check DNS now" runs for one domain.
    'manual_check_interval' => 20,

    // Cache host -> tenant lookups (invalidated whenever a domain row changes).
    'resolver_cache' => (bool) env('DOMAIN_RESOLVER_CACHE', true),
    'resolver_cache_ttl' => (int) env('DOMAIN_RESOLVER_CACHE_TTL', 300),

    // TLS issuance (host-side certbot, see deploy/custom-domains.sh).
    'ssl' => [
        'max_attempts' => 8,
        // Seconds to wait after a failed issuance = attempts * this (capped at 24h).
        'retry_backoff' => 3600,
    ],
];
