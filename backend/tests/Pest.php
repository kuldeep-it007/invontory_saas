<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Pest Test Case Bindings
|--------------------------------------------------------------------------
|
| Bind the Laravel application TestCase to the Feature and Smoke suites so
| those tests have access to the booted framework (config, DB connections,
| the service container). Unit tests remain plain and need no application.
|
*/

uses(Tests\TestCase::class)->in('Feature', 'Smoke');

/*
| Property-based tests (innmind/black-box) bind to a dedicated base case that
| mixes the BlackBox trait onto the framework TestCase, so proofs can drive the
| booted application (DB connections, tenancy) and call `static::forAll()`.
*/
uses(Tests\Property\PropertyTestCase::class)->in('Property');

/*
|--------------------------------------------------------------------------
| Shared Helpers
|--------------------------------------------------------------------------
|
| MySQL and Redis are not guaranteed to be reachable in every environment
| (e.g. local WAMP without the server running, CI without services). These
| helpers let DB-dependent smoke checks skip gracefully instead of failing,
| while static/boot checks always run.
|
*/

/**
 * Determine whether a MySQL server is reachable for the given connection.
 *
 * Attempts a lightweight `SELECT 1` against the connection. Any connection
 * failure (server down, driver missing, auth failure) returns false so the
 * caller can skip the DB-gated assertions rather than fail the suite.
 */
function mysql_reachable(string $connection = 'central'): bool
{
    if (! extension_loaded('pdo_mysql')) {
        return false;
    }

    try {
        \Illuminate\Support\Facades\DB::connection($connection)->select('SELECT 1');

        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * Skip the current test unless a MySQL server is reachable.
 */
function skip_without_mysql(string $connection = 'central'): void
{
    if (! mysql_reachable($connection)) {
        test()->markTestSkipped(
            "MySQL connection [{$connection}] is not reachable; DB-gated smoke assertions skipped."
        );
    }
}
