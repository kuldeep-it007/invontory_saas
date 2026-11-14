<?php

declare(strict_types=1);

namespace App\Services\TenantIntegrations;

use App\Exceptions\FeatureDisabledException;
use App\Jobs\CopyTenantStorage;
use App\Models\PlanFeature;
use App\Models\User;
use App\Services\SubscriptionGateService;
use App\Support\AuditRecorder;
use App\Support\HostName;
use App\Support\OutboundHostGuard;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * A tenant's own SMTP server and object storage: describe, test, save, remove.
 *
 * - Saving always runs the connection test first and refuses a failing config.
 * - Secrets are write-only. Responses carry flags and hints, never the values.
 *   A blank secret on update keeps the stored one, but only while the server /
 *   username (or endpoint / access key) are unchanged, so a stored secret can
 *   never be re-sent to a host the admin just swapped in.
 * - Hosts are checked with {@see OutboundHostGuard} before any connection.
 */
class TenantIntegrationService
{
    /** Fields that, when changed, require the secret to be entered again. */
    private const SECRET_BINDING = [
        TenantIntegrationStore::SMTP => ['host', 'port', 'username'],
        TenantIntegrationStore::STORAGE => ['endpoint', 'access_key'],
    ];

    private const FEATURES = [
        TenantIntegrationStore::SMTP => PlanFeature::CUSTOM_SMTP,
        TenantIntegrationStore::STORAGE => PlanFeature::CUSTOM_STORAGE,
    ];

    public function __construct(
        private readonly TenantIntegrationStore $store,
        private readonly ConnectionTester $tester,
        private readonly OutboundHostGuard $guard,
        private readonly SubscriptionGateService $gate,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @return array{smtp: array<string, mixed>, storage: array<string, mixed>}
     */
    public function overview(): array
    {
        return [
            'smtp' => $this->describe(TenantIntegrationStore::SMTP),
            'storage' => $this->describe(TenantIntegrationStore::STORAGE),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function describe(string $type): array
    {
        $stored = $this->store->get($type, $this->tenantId());
        $base = [
            'available' => $this->featureEnabled($type),
            'configured' => $stored !== null,
            'enabled' => (bool) ($stored['enabled'] ?? false),
            'last_test' => $stored['last_test'] ?? null,
        ];

        if ($type === TenantIntegrationStore::SMTP) {
            return $base + [
                'host' => $stored['host'] ?? null,
                'port' => $stored['port'] ?? 587,
                'encryption' => $stored['encryption'] ?? 'tls',
                'username' => $stored['username'] ?? null,
                'has_password' => ($stored['password'] ?? '') !== '',
                'from_address' => $stored['from_address'] ?? null,
                'from_name' => $stored['from_name'] ?? null,
            ];
        }

        return $base + [
            'provider' => $stored['provider'] ?? 'aws',
            'endpoint' => $stored['endpoint'] ?? null,
            'region' => $stored['region'] ?? null,
            'bucket' => $stored['bucket'] ?? null,
            'path_style' => (bool) ($stored['path_style'] ?? false),
            'access_key_hint' => isset($stored['access_key']) ? '••••'.substr((string) $stored['access_key'], -4) : null,
            'has_secret_key' => ($stored['secret_key'] ?? '') !== '',
            'copy' => $stored['copy'] ?? null,
        ];
    }

    /**
     * Validation rules for a save/test payload.
     *
     * @return array<string, mixed>
     */
    public function rules(string $type): array
    {
        if ($type === TenantIntegrationStore::SMTP) {
            return [
                'host' => ['required', 'string', 'max:253'],
                'port' => ['required', 'integer', 'between:1,65535'],
                'encryption' => ['required', Rule::in(['tls', 'ssl', 'none'])],
                'username' => ['nullable', 'string', 'max:255'],
                'password' => ['nullable', 'string', 'max:512'],
                'from_address' => ['required', 'email:rfc', 'max:255'],
                'from_name' => ['nullable', 'string', 'max:120'],
                'enabled' => ['sometimes', 'boolean'],
                'send_test_email' => ['sometimes', 'boolean'],
            ];
        }

        return [
            'provider' => ['required', Rule::in((array) config('integrations.providers'))],
            'endpoint' => ['nullable', 'required_unless:provider,aws', 'string', 'max:255'],
            'region' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'bucket' => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9._-]{1,61}[a-z0-9]$/'],
            'access_key' => ['nullable', 'string', 'max:255'],
            'secret_key' => ['nullable', 'string', 'max:512'],
            'path_style' => ['sometimes', 'boolean'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Run the connection test without saving.
     *
     * @param  array<string, mixed>  $input
     * @return array{ok: bool, message: string, tested_at: string}
     */
    public function test(string $type, array $input, ?User $user = null): array
    {
        return $this->runTest($type, $this->prepare($type, $input), $input, $user);
    }

    /**
     * Test, then save. A failing test is a 422 and nothing is stored.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function save(string $type, array $input, ?User $user = null): array
    {
        $tenantId = $this->tenantId();
        $stored = $this->store->get($type, $tenantId);
        $config = $this->prepare($type, $input);
        $result = $this->runTest($type, $config, $input, $user);

        if (! $result['ok']) {
            throw ValidationException::withMessages(['connection' => $result['message']]);
        }

        $config['last_test'] = ['ok' => true, 'at' => $result['tested_at']];
        $copy = $type === TenantIntegrationStore::STORAGE && $this->needsCopy($config, $stored);
        if ($copy) {
            $config['copy'] = ['status' => 'queued'];
        } elseif (isset($stored['copy'])) {
            $config['copy'] = $stored['copy'];
        }

        $this->store->put($type, $tenantId, $config);
        $this->audit->record('integration_'.$type.'_saved', $user, [
            'changed' => $this->changedFields($config, $stored),
            'enabled' => $config['enabled'],
        ]);

        if ($copy) {
            CopyTenantStorage::dispatch();
        }

        return $this->describe($type);
    }

    /** Drop the tenant's own config; the platform defaults apply again. */
    public function remove(string $type, ?User $user = null): void
    {
        $this->store->forget($type);
        $this->audit->record('integration_'.$type.'_removed', $user, []);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $input
     * @return array{ok: bool, message: string, tested_at: string}
     */
    private function runTest(string $type, array $config, array $input, ?User $user): array
    {
        try {
            if ($type === TenantIntegrationStore::SMTP) {
                $sendTo = ! empty($input['send_test_email']) ? $user?->email : null;
                $this->tester->smtp($config, $sendTo);
                $message = $sendTo !== null ? "Connected, and a test message was sent to {$sendTo}." : 'Connected and signed in.';
            } else {
                $this->tester->storage($config, $this->tenantId());
                $message = 'Bucket is reachable: a test file was written, read and removed.';
            }
            $ok = true;
        } catch (RuntimeException $e) {
            $ok = false;
            $message = $e->getMessage();
        }

        return ['ok' => $ok, 'message' => $message, 'tested_at' => now()->toIso8601String()];
    }

    /**
     * Merge the submitted values with the stored secrets and check the target host.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function prepare(string $type, array $input): array
    {
        if (! $this->featureEnabled($type)) {
            throw new FeatureDisabledException(self::FEATURES[$type]);
        }

        $stored = $this->store->get($type, $this->tenantId());

        return $type === TenantIntegrationStore::SMTP
            ? $this->prepareSmtp($input, $stored)
            : $this->prepareStorage($input, $stored);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>|null  $stored
     * @return array<string, mixed>
     */
    private function prepareSmtp(array $input, ?array $stored): array
    {
        $host = HostName::normalize((string) $input['host']) ?? (filter_var($input['host'], FILTER_VALIDATE_IP) !== false ? (string) $input['host'] : null);
        if ($host === null) {
            throw ValidationException::withMessages(['host' => 'Enter a valid server name, such as smtp.example.com.']);
        }

        if (! config('integrations.allow_private_hosts') && ! in_array((int) $input['port'], (array) config('integrations.smtp_ports'), true)) {
            throw ValidationException::withMessages(['port' => 'Use one of the ports: '.implode(', ', (array) config('integrations.smtp_ports')).'.']);
        }

        $config = [
            'host' => $host,
            'port' => (int) $input['port'],
            'encryption' => (string) $input['encryption'],
            'username' => $this->blankToNull($input['username'] ?? null),
            'password' => $this->blankToNull($input['password'] ?? null),
            'from_address' => (string) $input['from_address'],
            'from_name' => $this->blankToNull($input['from_name'] ?? null),
            'enabled' => (bool) ($input['enabled'] ?? true),
        ];

        $config['password'] = $this->resolveSecret($config, $stored, 'password', TenantIntegrationStore::SMTP);
        if ($config['username'] !== null && $config['password'] === null) {
            throw ValidationException::withMessages(['password' => 'Enter the password for this username.']);
        }

        $this->guard->assertPublic($host, 'host');

        return $config;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>|null  $stored
     * @return array<string, mixed>
     */
    private function prepareStorage(array $input, ?array $stored): array
    {
        $endpoint = $this->blankToNull($input['endpoint'] ?? null);
        if ($endpoint !== null) {
            $endpoint = rtrim($endpoint, '/');
            $parts = parse_url($endpoint);
            $scheme = strtolower((string) ($parts['scheme'] ?? ''));
            $secure = $scheme === 'https' || (config('integrations.allow_insecure_endpoints') && $scheme === 'http');
            if (! $secure || empty($parts['host']) || isset($parts['user']) || isset($parts['query']) || isset($parts['fragment'])) {
                throw ValidationException::withMessages(['endpoint' => 'Enter a valid https:// endpoint, for example https://s3.example.com.']);
            }
        }

        $config = [
            'provider' => (string) $input['provider'],
            'endpoint' => $endpoint,
            'region' => (string) $input['region'],
            'bucket' => (string) $input['bucket'],
            'access_key' => $this->blankToNull($input['access_key'] ?? null) ?? ($stored['access_key'] ?? null),
            'secret_key' => $this->blankToNull($input['secret_key'] ?? null),
            'path_style' => (bool) ($input['path_style'] ?? false),
            'enabled' => (bool) ($input['enabled'] ?? true),
        ];

        if ($config['access_key'] === null) {
            throw ValidationException::withMessages(['access_key' => 'Enter the access key.']);
        }

        $config['secret_key'] = $this->resolveSecret($config, $stored, 'secret_key', TenantIntegrationStore::STORAGE);
        if ($config['secret_key'] === null) {
            throw ValidationException::withMessages(['secret_key' => 'Enter the secret key.']);
        }

        if ($endpoint !== null) {
            $this->guard->assertPublic((string) parse_url($endpoint, PHP_URL_HOST), 'endpoint');
        }

        return $config;
    }

    /**
     * Use the submitted secret, or keep the stored one when nothing it is bound to changed.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>|null  $stored
     */
    private function resolveSecret(array $config, ?array $stored, string $field, string $type): ?string
    {
        if ($config[$field] !== null) {
            return $config[$field];
        }

        if ($stored === null || ($stored[$field] ?? '') === '') {
            return null;
        }

        foreach (self::SECRET_BINDING[$type] as $bound) {
            if (($config[$bound] ?? null) !== ($stored[$bound] ?? null)) {
                throw ValidationException::withMessages([
                    $field => 'Enter this again: the server details changed, so the saved value will not be reused.',
                ]);
            }
        }

        return (string) $stored[$field];
    }

    /**
     * Whether saving should (re)copy existing files into the bucket: first time,
     * a different bucket, or switched back on.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>|null  $stored
     */
    private function needsCopy(array $config, ?array $stored): bool
    {
        if (! $config['enabled']) {
            return false;
        }

        $identity = static fn (array $c): string => implode('|', [$c['endpoint'] ?? '', $c['region'] ?? '', $c['bucket'] ?? '', $c['access_key'] ?? '']);

        return $stored === null || ! ($stored['enabled'] ?? false) || $identity($config) !== $identity($stored);
    }

    /**
     * Names of the fields that changed. Names only, never values.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>|null  $stored
     * @return list<string>
     */
    private function changedFields(array $config, ?array $stored): array
    {
        return array_values(array_filter(
            array_keys($config),
            static fn (string $key): bool => ! in_array($key, ['last_test', 'copy'], true) && ($config[$key] ?? null) !== ($stored[$key] ?? null),
        ));
    }

    private function featureEnabled(string $type): bool
    {
        $plan = $this->gate->activePlan($this->tenantId());
        $flag = $plan?->features->firstWhere('key', self::FEATURES[$type]);

        return $flag === null || $flag->enabled;
    }

    private function blankToNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function tenantId(): string
    {
        return (string) tenant('id');
    }
}
