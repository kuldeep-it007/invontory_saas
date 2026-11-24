<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use InvalidArgumentException;

/**
 * Read-only access to what the host monitoring agent writes (metrics, alerts,
 * deployment history), plus the one thing the dashboard may write: thresholds.
 *
 * Nothing here is generated or simulated. If the agent is not running the
 * methods report `available => false` and the dashboard says so.
 */
final class MonitorStore
{
    /** Counters are summed when several samples are merged into one chart point. */
    private const SUM_KEYS = ['req', 's2xx', 's3xx', 's4xx', 's5xx', 'db_slow'];

    private const BOOL_KEYS = ['api_up', 'frontend_up', 'db_up'];

    public const RANGES = [
        '1h' => [3600, '1m'],
        '6h' => [21600, '1m'],
        '24h' => [86400, '1m'],
        '7d' => [604800, '5m'],
        '30d' => [2592000, '5m'],
    ];

    private const MAX_POINTS = 240;

    private const DEPLOYMENT_ID = '/^[0-9]{8}T[0-9]{6}Z-[0-9a-f]{6}$/';

    public function __construct(private readonly ?string $root = null) {}

    private function data(string $file = ''): string
    {
        return rtrim($this->root ?? (string) config('monitoring.path'), '/\\').'/data'.($file !== '' ? '/'.$file : '');
    }

    private function configDir(): string
    {
        return rtrim($this->root ?? (string) config('monitoring.path'), '/\\').'/config';
    }

    /** @return array<string, mixed>|null */
    private function json(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @return array{available: bool, stale: bool, age_s: int|null, data: array<string, mixed>|null} */
    public function current(): array
    {
        $file = $this->data('current.json');
        $data = $this->json($file);
        if ($data === null) {
            return ['available' => false, 'stale' => true, 'age_s' => null, 'data' => null];
        }
        $age = max(0, time() - (int) filemtime($file));

        return ['available' => true, 'stale' => $age > (int) config('monitoring.stale_after', 90), 'age_s' => $age, 'data' => $data];
    }

    /**
     * Chart series for a range, averaged into at most MAX_POINTS buckets.
     *
     * @return array{range: string, resolution: string, points: list<array<string, mixed>>}
     */
    public function series(string $range, ?int $now = null): array
    {
        if (! isset(self::RANGES[$range])) {
            throw new InvalidArgumentException('Unknown range.');
        }
        [$span, $resolution] = self::RANGES[$range];
        $now ??= time();
        $from = $now - $span;
        $path = $this->data("series-{$resolution}.jsonl");
        $bucket = max(1, (int) ceil($span / self::MAX_POINTS));
        /** @var array<int, list<array<string, mixed>>> $groups */
        $groups = [];
        if (is_file($path)) {
            $fh = fopen($path, 'rb');
            while ($fh !== false && ($line = fgets($fh)) !== false) {
                $row = json_decode($line, true);
                if (! is_array($row) || ! isset($row['ts']) || $row['ts'] < $from) {
                    continue;
                }
                $groups[intdiv((int) $row['ts'], $bucket) * $bucket][] = $row;
            }
            if ($fh !== false) {
                fclose($fh);
            }
        }
        ksort($groups);
        $points = [];
        foreach ($groups as $ts => $rows) {
            $points[] = ['ts' => $ts] + $this->merge($rows);
        }

        return ['range' => $range, 'resolution' => $resolution, 'points' => $points];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function merge(array $rows): array
    {
        $out = [];
        foreach (array_keys($rows[0] + end($rows)) as $key) {
            if ($key === 'ts') {
                continue;
            }
            $values = array_values(array_filter(array_column($rows, $key), fn ($v) => $v !== null));
            if ($values === []) {
                $out[$key] = null;
            } elseif (in_array($key, self::BOOL_KEYS, true)) {
                $out[$key] = ! in_array(false, $values, true);
            } elseif (in_array($key, self::SUM_KEYS, true)) {
                $out[$key] = array_sum($values);
            } elseif (str_ends_with($key, '_p95_ms')) {
                $out[$key] = max($values);
            } else {
                $out[$key] = round(array_sum($values) / count($values), 2);
            }
        }

        return $out;
    }

    /** @return array{active: list<array<string, mixed>>, history: list<array<string, mixed>>} */
    public function alerts(int $history = 100): array
    {
        $active = $this->json($this->data('alerts.json'))['active'] ?? [];
        $events = [];
        $path = $this->data('alerts.jsonl');
        if (is_file($path)) {
            foreach (array_slice($this->tailLines($path, 300), -$history) as $line) {
                $row = json_decode($line, true);
                if (is_array($row)) {
                    $events[] = $row;
                }
            }
        }

        return ['active' => array_values($active), 'history' => array_reverse($events)];
    }

    /** @return array<string, array{label: string, warning: float, critical: float, lower_is_worse: bool, max: float}> */
    public function thresholds(): array
    {
        $saved = $this->json($this->configDir().'/thresholds.json') ?? [];
        $out = [];
        foreach ((array) config('monitoring.thresholds') as $key => $meta) {
            $pair = $saved[$key] ?? $meta['default'];
            if (! is_array($pair) || count($pair) !== 2) {
                $pair = $meta['default'];
            }
            $out[$key] = [
                'label' => $meta['label'], 'warning' => (float) $pair[0], 'critical' => (float) $pair[1],
                'lower_is_worse' => (bool) ($meta['lower_is_worse'] ?? false), 'max' => (float) $meta['max'],
            ];
        }

        return $out;
    }

    /**
     * Validate and persist thresholds. Throws InvalidArgumentException with a field map on bad input.
     *
     * @param  array<string, mixed>  $input  metric => ['warning' => n, 'critical' => n]
     * @return array<string, array<string, mixed>>
     */
    public function saveThresholds(array $input): array
    {
        $meta = (array) config('monitoring.thresholds');
        $current = $this->thresholds();
        $store = [];
        foreach ($current as $key => $row) {
            $store[$key] = [$row['warning'], $row['critical']];
        }
        foreach ($input as $key => $pair) {
            if (! isset($meta[$key]) || ! is_array($pair) || ! is_numeric($pair['warning'] ?? null) || ! is_numeric($pair['critical'] ?? null)) {
                throw new InvalidArgumentException("Invalid threshold for {$key}.");
            }
            $warn = (float) $pair['warning'];
            $crit = (float) $pair['critical'];
            $lower = (bool) ($meta[$key]['lower_is_worse'] ?? false);
            if ($warn < 0 || $crit < 0 || max($warn, $crit) > $meta[$key]['max']) {
                throw new InvalidArgumentException("{$key}: values must be between 0 and {$meta[$key]['max']}.");
            }
            if ($lower ? $crit > $warn : $crit < $warn) {
                throw new InvalidArgumentException("{$key}: the critical value must be ".($lower ? 'at or below' : 'at or above').' the warning value.');
            }
            $store[$key] = [$warn, $crit];
        }
        $dir = $this->configDir();
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new InvalidArgumentException('The monitoring config directory is not writable.');
        }
        $tmp = $dir.'/thresholds.json.tmp';
        if (file_put_contents($tmp, json_encode($store, JSON_PRETTY_PRINT)) === false || ! rename($tmp, $dir.'/thresholds.json')) {
            throw new InvalidArgumentException('Could not save thresholds.');
        }

        return $this->thresholds();
    }

    /** @return list<array<string, mixed>> */
    public function deployments(int $limit = 50): array
    {
        $base = $this->data('deployments');
        if (! is_dir($base)) {
            return [];
        }
        $ids = array_filter(scandir($base) ?: [], fn (string $d): bool => preg_match(self::DEPLOYMENT_ID, $d) === 1);
        rsort($ids);
        $rows = [];
        foreach (array_slice($ids, 0, $limit) as $id) {
            $rec = $this->json("{$base}/{$id}/record.json");
            if ($rec !== null) {
                $rows[] = $this->sanitizeRecord($rec);
            }
        }

        return $rows;
    }

    /** @return array<string, mixed>|null */
    public function deployment(string $id): ?array
    {
        if (preg_match(self::DEPLOYMENT_ID, $id) !== 1) {
            return null;
        }
        $dir = $this->data("deployments/{$id}");
        $rec = $this->json("{$dir}/record.json");
        if ($rec === null) {
            return null;
        }

        return [
            'record' => $this->sanitizeRecord($rec),
            'gate' => $this->json("{$dir}/gate-report.json"),
            'deploy_log' => $this->logTail("{$dir}/deploy.log", 400),
            'gate_log' => $this->logTail("{$dir}/gate.log", 300),
        ];
    }

    /** @param array<string, mixed> $rec @return array<string, mixed> */
    private function sanitizeRecord(array $rec): array
    {
        foreach (['reason', 'actor', 'subject'] as $k) {
            if (isset($rec[$k]) && is_string($rec[$k])) {
                $rec[$k] = self::redact($rec[$k]);
            }
        }

        return $rec;
    }

    /** @return list<string> */
    private function logTail(string $path, int $lines): array
    {
        if (! is_file($path)) {
            return [];
        }

        return array_map(fn (string $l): string => self::redact($l), $this->tailLines($path, $lines));
    }

    /**
     * Recent ERROR-level entries from the Laravel log, redacted.
     *
     * @return list<array{time: string, level: string, message: string}>
     */
    public function recentErrors(int $limit = 50, ?string $logPath = null): array
    {
        $path = $logPath ?? storage_path('logs/laravel.log');
        if (! is_file($path)) {
            return [];
        }
        $out = [];
        foreach (array_reverse($this->tailLines($path, 4000)) as $line) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[ T][\d:]+)[^\]]*\]\s+\S+\.(ERROR|CRITICAL|ALERT|EMERGENCY):\s*(.*)$/', $line, $m) === 1) {
                $out[] = ['time' => $m[1], 'level' => $m[2], 'message' => mb_substr(self::redact($m[3]), 0, 300)];
                if (count($out) >= $limit) {
                    break;
                }
            }
        }

        return $out;
    }

    /** Last N lines of a file without loading all of it (reads at most 2 MB from the end). */
    private function tailLines(string $path, int $lines): array
    {
        $size = (int) filesize($path);
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            return [];
        }
        $read = min($size, 2_000_000);
        fseek($fh, $size - $read);
        $chunk = (string) fread($fh, $read);
        fclose($fh);
        $all = preg_split('/\r?\n/', rtrim($chunk)) ?: [];
        if ($size > $read) {
            array_shift($all); // first line is probably cut
        }

        return array_values(array_slice($all, -$lines));
    }

    /** Mask credentials that may appear in logs. Mirrors deploy/gate/deployctl.py. */
    public static function redact(string $text): string
    {
        $rules = [
            '/\b(Bearer)\s+[A-Za-z0-9._\-|]{16,}/i' => '$1 ***',
            '/(x-access-token:)[^@\s]+/' => '$1***',
            '/((?:password|passwd|secret|token|api[_-]?key|authorization)["\']?\s*[=:]\s*)[^\s"\',;]+/i' => '$1***',
            '/base64:[A-Za-z0-9+\/=]{20,}/' => 'base64:***',
            '/\b[A-Fa-f0-9]{40,}\b/' => '***',
        ];

        return (string) preg_replace(array_keys($rules), array_values($rules), $text);
    }
}
