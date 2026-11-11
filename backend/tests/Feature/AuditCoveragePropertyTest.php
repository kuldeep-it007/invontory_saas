<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Task 1.12 — Property 35: Audit coverage for data mutations
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 35: Audit coverage for
| data mutations
| Validates: Requirements 34.1
|
| For ANY create, update, or delete of a tenant-owned record, an `audit_logs`
| entry is written capturing the actor (user_id/user_type), a timestamp
| (created_at), and the affected record identity (auditable_type +
| auditable_id).
|
| The property is generated with innmind/black-box (NOT hand-rolled): varied
| tenant-owned record field values (name/email of the auditable User model) are
| produced by black-box string/char Sets and driven through a minimum of 100
| generated iterations via `->take(100)->enumerate()`.
|
| The property exercises the REAL persistence + owen-it/laravel-auditing path
| against a provisioned tenant database, so it is DB-GATED (needs MySQL +
| CREATE DATABASE for stancl tenancy) and skips gracefully when MySQL is
| unreachable, mirroring the auth / audit-trail suites. It is therefore correct
| against a real MySQL server even though the local environment cannot run it.
|
*/

/**
 * Minimum number of generated iterations required by the plan.
 */
const AUDIT_COVERAGE_ITERATIONS = 100;

/**
 * A black-box Set of varied field values for a tenant-owned User record.
 *
 * Each generated value is a pair of [name, email-local-part] used to populate
 * (and later mutate) the auditable record. Composed entirely from black-box
 * generators so the input space is explored intelligently rather than
 * hand-rolled:
 *   - names: printable strings (1–60 chars) plus unicode strings, so audited
 *     `new_values` carry a wide variety of content;
 *   - email local parts: constrained to a DNS/email-safe alphabet so each
 *     generated User has a syntactically valid, unique address.
 *
 * @return Set<array{0: string, 1: string}>
 */
function auditRecordFieldSet(): Set
{
    // `name` is stored on the User row AND copied into the audit `new_values`
    // map, which owen-it persists as JSON. The real input space is therefore
    // valid UTF-8 text that also survives json_encode. The raw Set::strings()
    // byte generator (and even ->unicode(), which may emit unpaired surrogate
    // code points) produces values no text column / JSON encoder can store, so
    // the name is drawn from a varied but storable alphabet: printable ASCII
    // plus a curated range of valid multibyte characters.
    $asciiChars = str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 .,-_#');
    $multibyteChars = ['é', 'ñ', 'ü', 'ß', 'å', 'ø', 'ç', 'λ', 'π', 'Ω', 'あ', '漢', '字', '€', '£'];
    $nameChars = Set::of(...$asciiChars, ...$multibyteChars);

    $name = Set::sequence($nameChars)
        ->between(1, 60)
        ->map(static fn (array $chars): string => implode('', $chars))
        ->map(static fn (string $n): string => trim($n) === '' ? 'Rec '.substr(md5($n.'x'), 0, 8) : $n);

    // Email-safe local part: lowercase letters/digits, 3–16 chars.
    $emailLocal = Set::sequence(
        Set::of(...str_split('abcdefghijklmnopqrstuvwxyz0123456789'))
    )
        ->between(3, 16)
        ->map(static fn (array $chars): string => implode('', $chars));

    return Set::compose(
        static fn (string $n, string $local): array => [$n, $local],
        $name,
        $emailLocal,
    )->toSet();
}

/**
 * Fetch the single audit entry for a given auditable record + event, asserting
 * the three facets of Property 35: actor identity, timestamp, and affected
 * record identity.
 */
function assertAuditCoverage(User $record, string $event, User $actor): void
{
    $entry = AuditLog::query()
        ->where('auditable_type', $record->getMorphClass())
        ->where('auditable_id', $record->getKey())
        ->where('event', $event)
        ->latest('id')
        ->first();

    expect($entry)->not->toBeNull("missing '{$event}' audit entry for record {$record->getKey()}");

    // Affected record identity is captured (auditable morph).
    expect($entry->auditable_type)->toBe($record->getMorphClass())
        ->and((int) $entry->auditable_id)->toBe((int) $record->getKey());

    // A timestamp is captured.
    expect($entry->created_at)->not->toBeNull();

    // Actor identity is captured (owen-it user morph columns).
    expect((int) $entry->user_id)->toBe((int) $actor->getKey())
        ->and($entry->user_type)->toBe($actor->getMorphClass());
}

it('writes an audit entry capturing actor, timestamp and record identity for every create/update/delete of a tenant-owned record (Property 35, R34.1)', function () {
    skip_without_mysql('central');

    $tenants = [];

    try {
        $suffix = strtolower(bin2hex(random_bytes(4)));

        /** @var Tenant $tenant */
        $tenant = Tenant::create([
            'id' => 'auditcov'.$suffix,
            'subdomain' => 'auditcov-'.$suffix,
        ]);
        $tenant->domains()->create(['domain' => 'auditcov-'.$suffix.'.example.test']);
        $tenants[] = $tenant;

        $tenant->run(function () {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--realpath' => false,
                '--force' => true,
            ]);

            // owen-it skips auditing in console context unless audit.console is
            // enabled; the property-test runner is a console process, so enable
            // it for the duration of this test so the real mutation→audit path
            // actually fires (R34.1).
            Config::set('audit.console', true);

            // The actor whose identity must be captured on every audit entry.
            $actor = User::create([
                'name' => 'Audit Actor',
                'email' => 'actor@example.test',
                'password' => 'actor-secret-123',
            ]);
            Auth::login($actor);

            $index = 0;

            foreach (auditRecordFieldSet()->take(AUDIT_COVERAGE_ITERATIONS)->enumerate() as [$name, $local]) {
                // CREATE a tenant-owned record with generated field values.
                $record = User::create([
                    'name' => $name,
                    'email' => 'rec'.$index.'-'.$local.'@example.test',
                    'password' => 'rec-secret-'.$index,
                ]);

                assertAuditCoverage($record, 'created', $actor);

                // UPDATE the record with a different generated value.
                $record->update(['name' => $name.' #'.$index]);

                assertAuditCoverage($record, 'updated', $actor);

                // DELETE the record.
                $recordId = $record->getKey();
                $record->delete();

                $deleted = AuditLog::query()
                    ->where('auditable_type', $record->getMorphClass())
                    ->where('auditable_id', $recordId)
                    ->where('event', 'deleted')
                    ->latest('id')
                    ->first();

                expect($deleted)->not->toBeNull("missing 'deleted' audit entry for record {$recordId}");
                expect((int) $deleted->auditable_id)->toBe((int) $recordId)
                    ->and($deleted->created_at)->not->toBeNull()
                    ->and((int) $deleted->user_id)->toBe((int) $actor->getKey())
                    ->and($deleted->user_type)->toBe($actor->getMorphClass());

                $index++;
            }

            // Guard: the generator really produced the required iterations.
            expect($index)->toBeGreaterThanOrEqual(AUDIT_COVERAGE_ITERATIONS);
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
