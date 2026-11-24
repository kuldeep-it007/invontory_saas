<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Server monitoring dashboard
|--------------------------------------------------------------------------
|
| The metrics are collected by the host-level agent (deploy/monitor/agent.py),
| which runs as its own systemd service and writes JSON files. The API only
| reads them, so a stopped agent shows up as "no data" rather than fake numbers.
|
| `path` is the mount point of the agent's directory inside the container:
|   <path>/data    read-only   metrics, alerts, deployment history
|   <path>/config  read-write  thresholds.json (edited from the dashboard)
|
*/

return [
    'path' => env('MONITOR_PATH', '/monitor'),

    // Seconds after which the agent's snapshot counts as stale (agent samples every 15 s).
    'stale_after' => (int) env('MONITOR_STALE_AFTER', 90),

    // Default [warning, critical] thresholds. Keep in sync with agent.py DEFAULT_THRESHOLDS.
    // `ssl_days_left` is the only metric where a LOWER value is worse.
    'thresholds' => [
        'cpu_pct' => ['label' => 'CPU utilisation (%)', 'default' => [80, 95], 'max' => 100],
        'load_per_core' => ['label' => 'Load per core (1 min)', 'default' => [1.0, 2.0], 'max' => 50],
        'mem_pct' => ['label' => 'Memory used (%)', 'default' => [85, 95], 'max' => 100],
        'swap_pct' => ['label' => 'Swap used (%)', 'default' => [50, 80], 'max' => 100],
        'disk_pct' => ['label' => 'Disk used (%)', 'default' => [80, 90], 'max' => 100],
        'api_ms' => ['label' => 'API health probe (ms)', 'default' => [500, 2000], 'max' => 60000],
        'latency_p95_ms' => ['label' => 'Request time p95 (ms)', 'default' => [1000, 3000], 'max' => 60000],
        'error_rate_5xx_pct' => ['label' => '5xx error rate (%)', 'default' => [1, 5], 'max' => 100],
        'db_conn_pct' => ['label' => 'DB connections used (%)', 'default' => [70, 90], 'max' => 100],
        'queue_depth' => ['label' => 'Queue depth (jobs)', 'default' => [500, 5000], 'max' => 10000000],
        'ssl_days_left' => ['label' => 'SSL days left', 'default' => [21, 7], 'max' => 3650, 'lower_is_worse' => true],
    ],
];
