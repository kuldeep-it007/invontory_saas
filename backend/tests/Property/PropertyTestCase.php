<?php

declare(strict_types=1);

namespace Tests\Property;

use Innmind\BlackBox\Application;
use Innmind\BlackBox\PHPUnit\Compatibility;
use Innmind\BlackBox\Runner\Given;
use Innmind\BlackBox\Set;
use Innmind\BlackBox\Set\Collapse;
use Innmind\BlackBox\Set\Composite;
use Innmind\BlackBox\Set\Provider;
use Innmind\BlackBox\Set\Randomize;
use Tests\TestCase;

/**
 * Base test case for property-based tests (innmind/black-box).
 *
 * Property tests describe a universal invariant and let BlackBox generate (by
 * default) 100 randomized scenarii per proof, shrinking any counterexample to
 * a minimal failing input. This base case drives the framework-aware
 * {@see TestCase} so proofs can exercise the booted Laravel container (DB
 * connections, tenancy, container bindings) across every generated scenario.
 *
 * ## Why a hand-rolled `forAll()` instead of the shipped `BlackBox` trait
 *
 * BlackBox's `PHPUnit\BlackBox::forAll()` builds its runner with global helper
 * functions ENABLED. When the proof executes, BlackBox `require_once`s
 * `Runner/global.php`, which declares a global `test()` function — and Pest
 * already declares `test()`, so the two collide with a fatal
 * "Cannot redeclare test()". We therefore build the exact same runner here but
 * call {@see Application::disableGlobalFunctions()} so that file is never
 * loaded. The generated-value plumbing (Collapse / Composite / Randomize /
 * Given) mirrors the shipped trait one-to-one, so behavior — including the
 * default 100 scenarii per proof and shrinking — is unchanged.
 */
abstract class PropertyTestCase extends TestCase
{
    /**
     * Begin a property proof over one or more generated {@see Set}s.
     *
     * Mirrors {@see \Innmind\BlackBox\PHPUnit\BlackBox::forAll()} but disables
     * BlackBox's global helper functions to avoid colliding with Pest's global
     * `test()`. Honors the optional `BLACKBOX_SET_SIZE` /
     * `BLACKBOX_DISABLE_SHRINKING` env overrides; defaults to 100 scenarii per
     * proof (satisfying the "minimum 100 generated iterations" requirement).
     */
    protected static function forAll(
        Set|Provider $first,
        Set|Provider ...$rest,
    ): Compatibility {
        $app = Application::new([])->disableGlobalFunctions();

        $size = getenv('BLACKBOX_SET_SIZE');
        $disableShrinking = (bool) getenv('BLACKBOX_DISABLE_SHRINKING');

        if ($size !== false) {
            $app = $app->scenariiPerProof((int) $size);
        }

        if ($disableShrinking) {
            $app = $app->disableShrinking();
        }

        $given = Collapse::of($first)->map(static fn (mixed $value) => [$value]);

        if (count($rest) > 0) {
            $given = Composite::immutable(
                static fn (mixed ...$args) => $args,
                $first,
                ...$rest,
            );
        }

        $given = Given::of(Randomize::of($given));

        return Compatibility::phpunit($app, $given);
    }
}
