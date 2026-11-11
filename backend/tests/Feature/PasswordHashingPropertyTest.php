<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Task 1.8 — Property 21: Password hashing
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 21: Password hashing
| Validates: Requirements 7.3
|
| For ANY password, the stored credential is a bcrypt/argon2 hash that is
| never equal to the plaintext and verifies against the original.
|
| The property is generated with innmind/black-box (NOT hand-rolled): varied
| passwords are produced by black-box string/char Sets and driven through a
| minimum of 100 generated iterations via `->take(100)->enumerate()`.
|
| The core property exercises Laravel's `Hash` facade directly — the exact
| mechanism behind the User model's `password => 'hashed'` cast (R7.3) — so it
| runs in any environment WITHOUT a tenant database. A second, DB-gated check
| exercises the real User model persistence path and skips gracefully when
| MySQL is unreachable, mirroring the existing auth suite.
|
*/

/**
 * Minimum number of generated iterations required by the plan.
 */
const PASSWORD_HASHING_ITERATIONS = 100;

/**
 * A black-box Set of varied, non-empty passwords.
 *
 * Composed entirely from black-box generators so the input space is explored
 * intelligently rather than hand-rolled:
 *   - ASCII printable strings (common password characters, 1–64 chars);
 *   - unicode strings (multibyte / emoji / accents, 1–32 chars);
 *   - a handful of boundary passwords (single char, long 72+ byte strings
 *     that straddle bcrypt's 72-byte input limit, whitespace-only).
 *
 * Empty strings are excluded: an empty password is not a credential we store.
 *
 * @return Set<string>
 */
function passwordSet(): Set
{
    $printable = Set::strings()->between(1, 64);
    $unicode = Set::strings()->unicode()->between(1, 32);
    $boundary = Set::of(
        'a',
        '   ',
        str_repeat('x', 72),   // exactly bcrypt's input byte limit
        str_repeat('y', 200),  // well beyond the bcrypt limit
        'påsswörd-🔐-Ünïcøde',
    );

    return Set::either($printable, $unicode, $boundary)
        // A credential is a non-empty string with no NUL byte: bcrypt (and the
        // underlying password_hash) reject NUL bytes, and a NUL byte is not a
        // realistic password, so we constrain the input space to valid
        // credentials rather than hand-roll values.
        ->filter(static fn (string $password): bool => $password !== '' && ! str_contains($password, "\0"));
}

/**
 * Assert the three sub-properties of Property 21 for a single plaintext and
 * its stored credential.
 */
function assertPasswordHashProperty(string $plaintext, string $stored): void
{
    // 1. The stored credential is NEVER the plaintext.
    expect($stored)->not->toBe($plaintext);

    // 2. The stored credential is a bcrypt OR argon2 hash (format check).
    //    bcrypt: $2y$ / $2a$ / $2b$   argon2: $argon2i$ / $argon2id$
    expect($stored)->toMatch('/^\$(2[aby]|argon2(id|i|d))\$/');

    // 3. The stored credential verifies against the ORIGINAL plaintext.
    expect(Hash::check($plaintext, $stored))->toBeTrue();
}

it('hashes every password to a bcrypt/argon2 credential that is never plaintext and verifies (Property 21, R7.3)', function () {
    $checked = 0;

    foreach (passwordSet()->take(PASSWORD_HASHING_ITERATIONS)->enumerate() as $password) {
        // Hash::make is the exact mechanism behind the User `hashed` cast.
        $stored = Hash::make($password);

        assertPasswordHashProperty($password, $stored);

        // A wrong password must not verify against the hash. The marker is
        // PREPENDED (not appended): bcrypt truncates input at 72 bytes, so a
        // trailing change on an already-long password would leave the first
        // 72 bytes identical and still verify — altering the prefix guarantees
        // a genuinely different credential.
        expect(Hash::check('tamper~'.$password, $stored))->toBeFalse();

        $checked++;
    }

    // Guard: the generator really produced the required number of iterations.
    expect($checked)->toBeGreaterThanOrEqual(PASSWORD_HASHING_ITERATIONS);
});

it('persists every User password through the hashed cast as a verifying bcrypt/argon2 hash (Property 21, R7.3)', function () {
    skip_without_mysql('central');

    $tenants = [];

    try {
        $suffix = strtolower(bin2hex(random_bytes(4)));

        /** @var Tenant $tenant */
        $tenant = Tenant::create([
            'id' => 'pwd'.$suffix,
            'subdomain' => 'pwd-'.$suffix,
        ]);
        $tenant->domains()->create(['domain' => 'pwd-'.$suffix.'.example.test']);
        $tenants[] = $tenant;

        $tenant->run(function () {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--realpath' => false,
                '--force' => true,
            ]);
            (new RoleSeeder)->run();

            $index = 0;

            foreach (passwordSet()->take(PASSWORD_HASHING_ITERATIONS)->enumerate() as $password) {
                $user = User::create([
                    'name' => 'Pwd User '.$index,
                    'email' => 'pwd'.$index.'@example.test',
                    'password' => $password,
                ]);

                // Read the credential straight from the tenant database.
                $stored = $user->fresh()->getAuthPassword();

                assertPasswordHashProperty($password, $stored);

                $index++;
            }

            expect($index)->toBeGreaterThanOrEqual(PASSWORD_HASHING_ITERATIONS);
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
