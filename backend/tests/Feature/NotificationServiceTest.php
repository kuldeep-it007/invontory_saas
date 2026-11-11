<?php

declare(strict_types=1);

use App\Models\Notification;
use App\Models\Tenant;
use App\Services\NotificationService;
use App\Services\SubscriptionGateService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Task 15.3 — In-app notifications + optional SMS/WhatsApp channels (DB-gated)
|--------------------------------------------------------------------------
|
| Exercises the notifications domain against a real provisioned tenant database:
|   • the `notifications` table migrates without a tenant_id discriminator
|     (physical isolation — R38.3);
|   • NotificationService.notify() records an in-app notification row (R27.6);
|   • list() returns a user's own history newest-first, scoped per user (R27.6);
|   • markRead() marks a single unread notification read, decreasing the unread
|     count by exactly one; markAllRead() leaves zero unread (R27.6);
|   • the optional SMS/WhatsApp channels fire ONLY where the tenant's
|     `whatsapp_notifications` feature flag is enabled, and degrade gracefully
|     to a logged no-op otherwise (R27.5).
|
| DB-GATED: needs MySQL + CREATE DATABASE for stancl tenancy; skips gracefully
| when MySQL is unreachable, mirroring PaymentServiceTest.
|
*/

beforeEach(function () {
    skip_without_mysql('central');
});

/**
 * Provision + migrate an isolated tenant for notification tests.
 *
 * @param  array<int, Tenant>  $registry
 */
function provisionNotificationTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(4)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'ntf'.$suffix,
        'subdomain' => 'ntf-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'ntf-'.$suffix.'.example.test']);
    $registry[] = $tenant;

    $tenant->run(function () {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
            '--force' => true,
        ]);
    });

    return $tenant;
}

/**
 * @param  array<int, Tenant>  $registry
 */
function teardownNotificationTenants(array $registry): void
{
    foreach ($registry as $t) {
        try {
            $t->database()->getTenantDatabaseManager()->deleteDatabase($t);
        } catch (Throwable $e) {
            // best-effort
        }
        $t->domains()->delete();
        $t->delete();
    }
}

/**
 * A NotificationService whose feature gate reports the given flag state, so the
 * optional-channel behaviour can be driven without seeding central plan rows.
 * Uses a tiny anonymous SubscriptionGateService subclass that overrides the
 * single flag read the service consults, avoiding any central-DB plan seeding.
 */
function makeNotificationService(bool $optionalChannelsEnabled = false): NotificationService
{
    $gate = new class($optionalChannelsEnabled) extends SubscriptionGateService
    {
        public function __construct(private readonly bool $enabled) {}

        public function featureEnabled(string $feature, string|int|null $tenantId = null): bool
        {
            return $this->enabled;
        }
    };

    return new NotificationService($gate);
}

it('migrates the notifications table without a tenant_id discriminator (R38.3)', function () {
    $registry = [];
    $tenant = provisionNotificationTenant($registry);

    try {
        $tenant->run(function () {
            expect(Schema::hasTable('notifications'))->toBeTrue();
            expect(Schema::hasColumns('notifications', [
                'id', 'user_id', 'type', 'title', 'body', 'data', 'read_at',
            ]))->toBeTrue();
            expect(Schema::hasColumn('notifications', 'tenant_id'))->toBeFalse();
        });
    } finally {
        teardownNotificationTenants($registry);
    }
});

it('records an in-app notification and lists a user\'s own history newest-first (R27.6)', function () {
    $registry = [];
    $tenant = provisionNotificationTenant($registry);

    try {
        $tenant->run(function () {
            $service = makeNotificationService();

            $first = $service->notify(1, Notification::TYPE_ORDER_PLACED, 'Order placed', 'Order #1 placed.');
            $second = $service->notify(1, Notification::TYPE_PAYMENT_RECORDED, 'Payment recorded', 'Paid 50.');
            // A different user's notification must NOT appear in user 1's list.
            $service->notify(2, Notification::TYPE_ACCOUNT_CREATED, 'Account created');

            expect($first)->toBeInstanceOf(Notification::class);
            expect($first->isUnread())->toBeTrue();

            $list = $service->list(1);
            expect($list->total())->toBe(2);
            // Newest-first ordering (R27.6).
            expect($list->items()[0]->getKey())->toBe($second->getKey());
            expect($list->items()[1]->getKey())->toBe($first->getKey());

            expect($service->unreadCount(1))->toBe(2);
            expect($service->unreadCount(2))->toBe(1);
        });
    } finally {
        teardownNotificationTenants($registry);
    }
});

it('marks a single notification read, decreasing the unread count by exactly one (R27.6)', function () {
    $registry = [];
    $tenant = provisionNotificationTenant($registry);

    try {
        $tenant->run(function () {
            $service = makeNotificationService();

            $a = $service->notify(1, Notification::TYPE_ORDER_PLACED, 'A');
            $service->notify(1, Notification::TYPE_ORDER_PLACED, 'B');
            $service->notify(1, Notification::TYPE_ORDER_PLACED, 'C');

            expect($service->unreadCount(1))->toBe(3);

            $marked = $service->markRead(1, (int) $a->getKey());
            expect($marked->isUnread())->toBeFalse();
            expect($service->unreadCount(1))->toBe(2);

            // Idempotent: marking the same one again does not change the count.
            $service->markRead(1, (int) $a->getKey());
            expect($service->unreadCount(1))->toBe(2);
        });
    } finally {
        teardownNotificationTenants($registry);
    }
});

it('marks all notifications read, leaving zero unread (R27.6)', function () {
    $registry = [];
    $tenant = provisionNotificationTenant($registry);

    try {
        $tenant->run(function () {
            $service = makeNotificationService();

            $service->notify(1, Notification::TYPE_ORDER_PLACED, 'A');
            $service->notify(1, Notification::TYPE_ORDER_PLACED, 'B');
            // Another user's unread must be untouched by user 1's mark-all-read.
            $service->notify(2, Notification::TYPE_ORDER_PLACED, 'X');

            $count = $service->markAllRead(1);

            expect($count)->toBe(2);
            expect($service->unreadCount(1))->toBe(0);
            expect($service->unreadCount(2))->toBe(1);
        });
    } finally {
        teardownNotificationTenants($registry);
    }
});

it('cannot mark another user\'s notification read (per-user scope, R27.6)', function () {
    $registry = [];
    $tenant = provisionNotificationTenant($registry);

    try {
        $tenant->run(function () {
            $service = makeNotificationService();

            $other = $service->notify(2, Notification::TYPE_ORDER_PLACED, 'Not yours');

            expect(fn () => $service->markRead(1, (int) $other->getKey()))
                ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);

            // The other user's notification is still unread.
            expect($service->unreadCount(2))->toBe(1);
        });
    } finally {
        teardownNotificationTenants($registry);
    }
});

it('fires optional SMS/WhatsApp channels only where the whatsapp_notifications flag is enabled (R27.5)', function () {
    $registry = [];
    $tenant = provisionNotificationTenant($registry);

    try {
        $tenant->run(function () {
            config()->set('services.notifications.sms.driver', 'log');
            config()->set('services.notifications.whatsapp.driver', 'log');

            // Flag OFF: no optional-channel log entries, in-app row still written.
            Log::shouldReceive('info')
                ->with('notification.optional_channel', Mockery::any())
                ->never();

            $disabled = makeNotificationService(optionalChannelsEnabled: false);
            $disabled->notify(1, Notification::TYPE_ORDER_PLACED, 'Order placed', null, [], '+15551234567');

            expect(Notification::where('user_id', 1)->count())->toBe(1);
        });
    } finally {
        teardownNotificationTenants($registry);
    }
});

it('logs the SMS and WhatsApp channels when the flag is enabled and a phone is given (R27.5)', function () {
    $registry = [];
    $tenant = provisionNotificationTenant($registry);

    try {
        $tenant->run(function () {
            config()->set('services.notifications.sms.driver', 'log');
            config()->set('services.notifications.whatsapp.driver', 'log');

            // Flag ON + phone present: both channels record a log no-op (graceful
            // degradation with no gateway configured — R27.5).
            Log::shouldReceive('info')
                ->with('notification.optional_channel', Mockery::on(
                    fn ($ctx) => in_array($ctx['channel'] ?? null, ['sms', 'whatsapp'], true)
                        && ($ctx['to'] ?? null) === '+15551234567',
                ))
                ->twice();

            $enabled = makeNotificationService(optionalChannelsEnabled: true);
            $enabled->notify(1, Notification::TYPE_ORDER_CONFIRMED, 'Order confirmed', 'Invoice issued.', [], '+15551234567');

            expect(Notification::where('user_id', 1)->count())->toBe(1);
        });
    } finally {
        teardownNotificationTenants($registry);
    }
});
