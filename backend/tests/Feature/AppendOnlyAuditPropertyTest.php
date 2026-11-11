<?php

declare(strict_types=1);

use App\Exceptions\ImmutableAuditException;
use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Task 1.11 — Property 9 (audit portion): Append-only ledgers reject mutation
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 9: Append-only ledgers reject mutation (audit portion)
| Validates: Requirements 34.2
|
| For ANY existing `audit_log` entry, every update and delete attempt is
| rejected (App\Exceptions\ImmutableAuditException) and the stored row remains
| byte-for-byte unchanged.
|
| Inputs are generated with innmind/black-box (NOT hand-rolled): varied audit
| entry field values — event name, affected-record morph identity, old/new
| value maps, reason, URL, IP address, user-agent, and tags — are produced by
| black-box Sets and driven through a minimum of 100 generated iterations via
| `->take(100)->enumerate()`.
|
| Append-only enforcement lives in the model persistence hooks (AuditLog
| `updating`/`deleting`), which require a real row on a real connection, so the
| property is DB-GATED: it provisions an isolated tenant database (stancl
| tenancy needs CREATE DATABASE) and skips gracefully when MySQL is
| unreachable, mirroring the existing audit/auth suites. It is correct against
| real MySQL.
|
*/

/**
 * Minimum number of generated iterations required by the plan.
 */
const APPEND_ONLY_AUDIT_ITERATIONS = 100;

/**
 * A black-box Set of varied audit-entry field maps.
 *
 * Each generated value is an associative array of column => value ready to
 * persist as an `audit_logs` row. The Sets explore the input space
 * intelligently rather than hand-rolling a fixed list:
 *   - `event`: owen-it lifecycle events plus platform security events (R7.6);
 *   - `auditable_type` / `auditable_id`: varied morph identity of the affected
 *     record (R34.1), ids spanning the positive bigint range;
 *   - `old_values` / `new_values`: small JSON-castable maps, sometimes null;
 *   - `reason`, `url`, `ip_address`, `user_agent`, `tags`: owen-it context
 *     columns, nullable and of varied content.
 *
 * @return Set<array<string, mixed>>
 */
function auditEntrySet(): Set
{
    // owen-it lifecycle events plus platform security events (R7.6, R34.1).
    $event = Set::either(
        Set::of('created', 'updated', 'deleted', 'restored'),
        Set::of('login', 'logout', 'password_reset_requested', 'otp_verified'),
    );

    // Varied morph type of the affected record (R34.1): known models plus
    // free-form ASCII class-ish strings.
    $auditableType = Set::either(
        Set::of('App\\Models\\User', 'App\\Models\\Company', 'App\\Models\\Order'),
        // Valid UTF-8 text: these are stored in a text column and (for
        // old/new_values) JSON-encoded, so the input space is valid UTF-8 — the
        // raw byte generator emits sequences no text column / json_encode can
        // store, which is outside this property's domain.
        Set::strings()->unicode()->between(1, 40)
            ->filter(static fn (string $s): bool => trim($s) !== ''),
    );

    // Positive bigint identifiers, including boundary values.
    $auditableId = Set::either(
        Set::integers()->between(1, PHP_INT_MAX),
        Set::of(1, 2, 9223372036854775807),
    );

    // Small JSON-castable attribute maps (or null, e.g. the create case):
    // a sequence of string keys turned into a key => value map.
    // old/new_values are array-cast → persisted as JSON, so each generated key
    // must survive json_encode. Unicode code points can include unpaired
    // surrogates (valid "unicode" but invalid UTF-8 for JSON), so the map is
    // built from a JSON-safe alphabet — the property is about immutability of a
    // stored entry, not the exoticness of its JSON payload.
    $values = Set::either(
        Set::of(null),
        Set::sequence(
            Set::sequence(Set::of(...str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 _-')))
                ->between(1, 12)
                ->map(static fn (array $chars): string => implode('', $chars))
        )
            ->between(0, 4)
            ->map(static function (array $keys): array {
                $map = [];
                foreach (array_values($keys) as $i => $key) {
                    $map[$key.$i] = 'v'.$i;
                }

                return $map;
            }),
    );

    // owen-it context columns: nullable, varied text. Constrained to a
    // DB-storable, JSON-safe alphabet (the `tags` column participates in the
    // byte-for-byte reload comparison, and raw/surrogate bytes are outside the
    // text domain these columns model).
    $textChars = Set::of(...str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 .,:-_/'));
    $nullableText = Set::either(
        Set::of(null, '', 'override: manual stock correction', 'impersonation session'),
        Set::sequence($textChars)
            ->between(0, 64)
            ->map(static fn (array $chars): string => implode('', $chars)),
    );

    $ip = Set::of(null, '127.0.0.1', '10.0.0.1', '192.168.1.254', '::1', '255.255.255.255');

    return Set::compose(
        static fn (string $event, string $type, int $id, $old, $new, $reason, $ipAddr, $ua, $tags): array => [
            'event' => $event,
            'auditable_type' => $type,
            'auditable_id' => $id,
            'old_values' => $old,
            'new_values' => $new,
            'reason' => $reason,
            'url' => 'https://tenant.example.test/path',
            'ip_address' => $ipAddr,
            'user_agent' => $ua,
            'tags' => $tags,
        ],
        $event,
        $auditableType,
        $auditableId,
        $values,
        $values,
        $nullableText,
        $ip,
        $nullableText,
        $nullableText,
    )->toSet();
}

/**
 * Persist one audit entry directly (append is always permitted — only the
 * subsequent update/delete is guarded), returning the fresh stored model.
 */
function insertAuditEntry(array $attributes): AuditLog
{
    $entry = new AuditLog;
    $entry->forceFill($attributes);
    $entry->save();

    return $entry->fresh();
}

it('rejects update and delete of every existing audit entry and leaves it unchanged (Property 9 audit portion, R34.2)', function () {
    skip_without_mysql('central');

    $tenants = [];

    try {
        $suffix = strtolower(bin2hex(random_bytes(4)));

        /** @var Tenant $tenant */
        $tenant = Tenant::create([
            'id' => 'append'.$suffix,
            'subdomain' => 'append-'.$suffix,
        ]);
        $tenant->domains()->create(['domain' => 'append-'.$suffix.'.example.test']);
        $tenants[] = $tenant;

        $tenant->run(function () {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--realpath' => false,
                '--force' => true,
            ]);

            $checked = 0;

            foreach (auditEntrySet()->take(APPEND_ONLY_AUDIT_ITERATIONS)->enumerate() as $attributes) {
                // Append is permitted: the first insert must succeed.
                $entry = insertAuditEntry($attributes);
                $key = $entry->getKey();

                // Snapshot the stored row straight from the tenant database.
                $before = AuditLog::query()->find($key)->getAttributes();

                // 1. Every update attempt is rejected.
                expect(fn () => $entry->update(['event' => 'tampered-'.$checked]))
                    ->toThrow(ImmutableAuditException::class);

                // A raw save() (e.g. changing a different column) is also rejected.
                $entry->reason = 'tampered-reason';
                expect(fn () => $entry->save())
                    ->toThrow(ImmutableAuditException::class);

                // 2. Every delete attempt is rejected.
                expect(fn () => $entry->delete())
                    ->toThrow(ImmutableAuditException::class);

                // 3. The stored entry still exists and is byte-for-byte unchanged.
                $reloaded = AuditLog::query()->find($key);
                expect($reloaded)->not->toBeNull()
                    ->and($reloaded->getAttributes())->toEqual($before);

                $checked++;
            }

            // Guard: the generator really produced the required iterations.
            expect($checked)->toBeGreaterThanOrEqual(APPEND_ONLY_AUDIT_ITERATIONS);
        });
    } finally {
        foreach ($tenants as $t) {
            try {
                $t->database()->getTenantDatabaseManager()->deleteDatabase($t);
            } catch (Throwable $e) {
                // best-effort teardown
            }
            $t->domains()->delete();
            $t->delete();
        }
    }
});
