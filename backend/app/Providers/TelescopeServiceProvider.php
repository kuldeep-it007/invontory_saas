<?php

namespace App\Providers;

use App\Auth\Roles;
use App\Models\User;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

/**
 * Telescope for Platform_Admin: records everything the enabled watchers see
 * (see config/telescope.php for the performance-tuned watcher set), tags each
 * entry with tenant, user and request id, and never stores secrets.
 */
class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /** Request fields Telescope must never store. */
    private const HIDDEN_PARAMETERS = [
        '_token', 'password', 'password_confirmation', 'current_password', 'new_password',
        'token', 'access_token', 'refresh_token', 'secret', 'secret_key', 'access_key',
        'api_key', 'otp', 'code', 'smtp_password',
    ];

    private const HIDDEN_HEADERS = [
        'authorization', 'cookie', 'set-cookie', 'x-csrf-token', 'x-xsrf-token', 'php-auth-pw', 'x-api-key',
    ];

    public function register(): void
    {
        $this->hideSensitiveRequestDetails();

        // TELESCOPE_RECORD_ALL=false keeps only problems (exceptions, failed
        // requests/jobs, scheduled tasks, monitored tags) if load ever needs it.
        $recordAll = (bool) config('telescope.record_all', true);

        Telescope::filter(function (IncomingEntry $entry) use ($recordAll): bool {
            return $recordAll
                || $entry->isReportableException()
                || $entry->isFailedRequest()
                || $entry->isFailedJob()
                || $entry->isScheduledTask()
                || $entry->hasMonitoredTag();
        });

        Telescope::tag(function (IncomingEntry $entry): array {
            $tags = [];
            if (function_exists('tenancy') && tenancy()->initialized) {
                $tags[] = 'tenant:'.tenant('id');
            }
            if (($requestId = Context::get('request_id')) !== null) {
                $tags[] = 'request:'.$requestId;
            }

            return $tags;
        });
    }

    protected function hideSensitiveRequestDetails(): void
    {
        Telescope::hideRequestParameters(self::HIDDEN_PARAMETERS);
        Telescope::hideRequestHeaders(self::HIDDEN_HEADERS);
        Telescope::hideResponseParameters(['token', 'access_token', 'plainTextToken']);
    }

    /**
     * Only a Platform_Admin (central account) may open Telescope. Tenants never can.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function (?User $user = null): bool {
            return $user !== null && $user->hasRole(Roles::PLATFORM_ADMIN);
        });
    }
}
