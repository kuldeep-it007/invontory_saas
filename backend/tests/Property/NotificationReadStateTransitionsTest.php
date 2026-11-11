<?php

declare(strict_types=1);

use App\Models\Notification;
use App\Models\Tenant;
use App\Services\NotificationService;
use App\Services\SubscriptionGateService;
use Illuminate\Support\Facades\Artisan;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Task 15.4 — Property 36: Notification read-state transitions
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 36: Notification
| read-state transitions
|
| Validates: Requirements 27.6
|
| Property statement (tasks.md 15.4):
|   Mark-all-read leaves zero unread; marking one unread notification read
|   decreases the unread count by exactly one.
|
| R27.6 — "THE Platform SHALL present in-app notifications with history and
| support marking a single notification read and marking all notifications
| read." The notification centre is per tenant USER, so the read-state rules
| are scoped to the owning user and never reach across users.
|
| Four universal invariants are generated per scenario by innmind/black-box
| (NOT hand-rolled) via the shared static::forAll(), which draws the mandated
| minimum of 100 generated scenarii per proof and shrinks any counterexample to
| a minimal failing input. For every generated (notification-count, operation-
| sequence) pair we assert, against a REAL provisioned tenant database:
|
|   mark-one-decrements-by-one — marking a currently-UNREAD notification read
|       drops the owner's unread count by EXACTLY one; marking an ALREADY-READ
|       notification read is idempotent and leaves the count unchanged. So a
|       single markRead() lowers the unread count by exactly one iff the target
|       was unread, and never by more than one.
|
|   read-state-is-monotonic — read-state only ever moves unread → read; a
|       notification observed read in a scenario is never observed unread again
|       after any later operation. The service exposes no "unread" transition,
|       so the per-notification state is monotone and the unread count is
|       non-increasing across markRead()/markAllRead() calls.
|
|   mark-all-leaves-zero — after markAllRead() the owner has ZERO unread
|       notifications, regardless of how many were read beforehand, and the
|       return value equals the number that were still unread at that moment.
|
|   per-user-scope — one user's markRead()/markAllRead() NEVER changes another
|       user's notifications: a user cannot mark a notification they do not own
|       read (findOrFail misses it), and mark-all-read for one user leaves every
|       other user's unread count untouched.
|
| DB-GATED. Provisioning a real per-tenant MySQL database needs a reachable
| MySQL server with CREATE DATABASE privileges and the stancl/tenancy MySQL
| manager; when MySQL is unreachable (local WAMP down, CI without MySQL) the
| proof skips gracefully via skip_without_mysql('central'). The assertions are
| written to be correct against a real MySQL instance.
*/

/**
 * Provision + migrate an isolated tenant for the read-state proof. Registered
 * for teardown through the passed-by-reference registry.
 *
 * @param  array<int, Tenant>  $registry
 */
function provisionReadStateTenant(array &$registry): Tenant
{
    $suffix = strtolower(bin2hex(random_bytes(5)));

    /** @var Tenant $tenant */
    $tenant = Tenant::create([
        'id' => 'nrs'.$suffix,
        'subdomain' => 'nrs-'.$suffix,
    ]);
    $tenant->domains()->create(['domain' => 'nrs-'.$suffix.'.example.test']);
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
 * Best-effort teardown: drop each provisioned tenant database and its central
 * registry rows so repeated proof runs stay clean.
 *
 * @param  array<int, Tenant>  $registry
 */
function teardownReadStateTenants(array $registry): void
{
    tenancy()->end();

    foreach ($registry as $t) {
        try {
            $t->database()->getTenantDatabaseManager()->deleteDatabase($t);
        } catch (\Throwable $e) {
            // best-effort cleanup
        }

        try {
            $t->domains()->delete();
            $t->delete();
        } catch (\Throwable $e) {
            // best-effort cleanup
        }
    }
}

/**
 * A NotificationService whose feature gate reports the optional-channel flag as
 * DISABLED, so the proof exercises ONLY the in-app read-state rules (R27.6)
 * without needing any central-DB plan seeding or gateway configuration.
 */
function makeReadStateService(): NotificationService
{
    $gate = new class extends SubscriptionGateService
    {
        public function __construct() {}

        public function featureEnabled(string $feature, string|int|null $tenantId = null): bool
        {
            return false;
        }
    };

    return new NotificationService($gate);
}

it('transitions notification read-state monotonically, decrements unread by exactly one, zeroes on mark-all, and never crosses users (R27.6)', function () {
    skip_without_mysql('central');

    $registry = [];

    try {
        $tenant = provisionReadStateTenant($registry);

        $tenant->run(function () {
            // ------------------------------------------------------------------
            // Generated invariant (100+ scenarii, BlackBox default), drawn via
            // the shared static::forAll() — NOT a hand-rolled loop.
            //
            //   • $count       — how many notifications the owning user starts
            //                    with (1..8), all unread.
            //   • $operations  — a sequence (length 0..12) of read-state
            //                    operations to apply, each an index drawn from a
            //                    space WIDER than $count so some ops target an
            //                    out-of-range id (a no-op markRead that still
            //                    must not throw past findOrFail) and the sentinel
            //                    -1 which means "markAllRead".
            // ------------------------------------------------------------------
            static::forAll(
                Set::integers()->between(1, 8),
                Set::sequence(Set::integers()->between(-1, 9))->atMost(12),
            )->then(function (int $count, array $operations) {
                $service = makeReadStateService();

                // Two distinct users so the per-user-scope invariant has a
                // bystander whose state must never be touched by the owner.
                $owner = 1;
                $bystander = 2;

                // Namespace nothing — each scenario runs in a clean subset by
                // isolating on freshly created ids. Create the owner's unread
                // notifications and remember their primary keys in order.
                /** @var array<int, int> $ownerIds */
                $ownerIds = [];
                for ($i = 0; $i < $count; $i++) {
                    $ownerIds[] = (int) $service
                        ->notify($owner, Notification::TYPE_ORDER_PLACED, 'N'.$i)
                        ->getKey();
                }

                // One untouched bystander notification (unread) to witness
                // cross-user isolation.
                $bystanderId = (int) $service
                    ->notify($bystander, Notification::TYPE_ORDER_PLACED, 'B')
                    ->getKey();

                // Baseline: everything the owner has is unread.
                expect($service->unreadCount($owner))->toBe($count);
                expect($service->unreadCount($bystander))->toBe(1);

                // Track the read-state we EXPECT per owner notification so we can
                // assert both the exact-one decrement and monotonicity.
                /** @var array<int, bool> $expectedRead keyed by notification id */
                $expectedRead = array_fill_keys($ownerIds, false);
                $expectedUnread = $count;

                foreach ($operations as $op) {
                    $before = $service->unreadCount($owner);
                    // Monotonic non-increase holds at every step too.
                    expect($before)->toBe($expectedUnread);

                    if ($op === -1) {
                        // --- markAllRead: zero unread for the owner ------------
                        $flipped = $service->markAllRead($owner);

                        // The return equals exactly the still-unread count.
                        expect($flipped)->toBe($before);
                        // Mark-all-read leaves ZERO unread (R27.6).
                        expect($service->unreadCount($owner))->toBe(0);

                        foreach ($ownerIds as $id) {
                            $expectedRead[$id] = true;
                        }
                        $expectedUnread = 0;

                        // The bystander is entirely untouched.
                        expect($service->unreadCount($bystander))->toBe(1);

                        continue;
                    }

                    if ($op >= 1 && $op <= $count) {
                        // --- markRead on an in-range owner notification --------
                        $targetId = $ownerIds[$op - 1];
                        $wasUnread = ! $expectedRead[$targetId];

                        $marked = $service->markRead($owner, $targetId);

                        // The target is read afterwards, and stays read.
                        expect($marked->isUnread())->toBeFalse();

                        $after = $service->unreadCount($owner);

                        if ($wasUnread) {
                            // Marking an UNREAD one read drops the count by
                            // EXACTLY one (R27.6).
                            expect($after)->toBe($before - 1);
                            $expectedRead[$targetId] = true;
                            $expectedUnread = $before - 1;
                        } else {
                            // Idempotent: already-read stays read, count
                            // unchanged — never decreases by more than one.
                            expect($after)->toBe($before);
                            $expectedUnread = $before;
                        }

                        // Bystander untouched by the owner's markRead.
                        expect($service->unreadCount($bystander))->toBe(1);

                        continue;
                    }

                    // --- out-of-range id: the owner owns no such notification --
                    // Mapping an op onto an id the owner does not own must behave
                    // like trying to mark a foreign notification: findOrFail
                    // misses and the unread count is unchanged. We route it at
                    // the bystander's real id to also prove a user CANNOT mark
                    // another user's notification read (per-user scope, R27.6).
                    expect(fn () => $service->markRead($owner, $bystanderId))
                        ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);

                    // Neither side's unread count moved.
                    expect($service->unreadCount($owner))->toBe($before);
                    expect($service->unreadCount($bystander))->toBe(1);
                }

                // Final monotonicity cross-check: every notification we expect
                // read is read, and the live unread count matches the expected
                // number — read-state only ever moved unread → read.
                $liveUnread = 0;
                foreach ($ownerIds as $id) {
                    $fresh = Notification::query()->forUser($owner)->find($id);
                    expect($fresh)->not->toBeNull();
                    expect($fresh->isUnread())->toBe(! $expectedRead[$id]);
                    if ($fresh->isUnread()) {
                        $liveUnread++;
                    }
                }
                expect($liveUnread)->toBe($expectedUnread);
                expect($service->unreadCount($owner))->toBe($expectedUnread);

                // The bystander's single notification is still unread after the
                // owner's entire operation sequence — total cross-user isolation.
                expect($service->unreadCount($bystander))->toBe(1);

                // Clean this scenario's rows so the next scenario starts fresh
                // within the same tenant database (100+ scenarii per proof).
                Notification::query()->whereIn('user_id', [$owner, $bystander])->delete();
            });
        });
    } finally {
        teardownReadStateTenants($registry);
    }
});
