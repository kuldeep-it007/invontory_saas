<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Tenant integrations (own SMTP server, own object storage)
|--------------------------------------------------------------------------
|
| A tenant may bring its own SMTP credentials and S3-compatible bucket. These
| settings guard what a tenant may point the server at, because the server makes
| the outbound connection on the tenant's behalf.
*/

return [
    // Allow private / loopback / link-local targets. Leave OFF in production
    // (it would let a tenant probe internal services); enable only for local
    // development against MailHog or MinIO.
    'allow_private_hosts' => (bool) env('TENANT_INTEGRATIONS_ALLOW_PRIVATE_HOSTS', false),

    // Allow plain-http storage endpoints (local MinIO). Production keeps https only.
    'allow_insecure_endpoints' => (bool) env('TENANT_INTEGRATIONS_ALLOW_INSECURE_ENDPOINTS', false),

    // Outbound SMTP ports a tenant may use.
    'smtp_ports' => array_map('intval', array_filter(array_map(
        'trim',
        explode(',', (string) env('TENANT_INTEGRATIONS_SMTP_PORTS', '25,465,587,2525')),
    ))),

    // Storage providers a tenant can label its bucket with. The driver is always
    // S3-compatible; the label only helps the UI pre-fill sensible defaults.
    'providers' => ['aws', 'oci', 'minio', 'r2', 'spaces', 'wasabi', 'backblaze', 'other'],

    // Seconds. Connection tests and live use must never hang a worker.
    'timeouts' => [
        'smtp' => 10,
        'storage_connect' => 5,
        'storage' => 20,
    ],
];
