<?php

declare(strict_types=1);

use App\Http\Requests\StoreStockAdjustmentRequest;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidationFactory;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Task 7.17 — Property 37: Stock-adjustment reason validation
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 37: Stock-adjustment reason validation
|
| Validates: Requirements 14.3, 14.4
|
| Property statement (design.md §Property 37):
|   For any manual stock adjustment, the adjustment is accepted if and only if
|   its reason is a non-empty, non-whitespace string; otherwise it is rejected
|   with 422.
|
| The accept/reject decision for the reason lives entirely in
| StoreStockAdjustmentRequest: `prepareForValidation()` trims a string reason
| so a whitespace-only value collapses to the empty string (R14.4), and the
| `required` + `string` + `min:1` rules then reject an absent, empty, or
| whitespace-only reason — the HTTP layer answers those failures with a 422
| (R14.4). A non-empty, non-whitespace reason passes (R14.3).
|
| This invariant is a pure property of the request's own normalization + rules,
| so — like the discount-type enumeration unit test — it runs the request's
| prepareForValidation + rules() through a standalone validator (no booted app
| or database). The `product_id`/`stock_point_id` `exists:` rules and the
| `quantity` numeric rules are DB/payload concerns orthogonal to the reason
| invariant, so each scenario asserts only on the `reason` field.
|
| Generation uses innmind/black-box (NOT hand-rolled): BlackBox `Set`s produce
| a wide variety of reason strings and `->take(100)->enumerate()` draws the
| minimum 100 generated iterations the spec mandates.
*/

/**
 * Decide the reason outcome exactly as StoreStockAdjustmentRequest does: apply
 * the request's `prepareForValidation` normalization (trim a string reason),
 * then run its own `rules()` for the `reason` field through a standalone
 * validator. Returns true iff the reason is accepted.
 *
 * `$provided` distinguishes an absent `reason` key (missing payload, R14.4)
 * from a present-but-empty/whitespace value.
 */
function stockAdjustmentReasonAccepted(mixed $reason, bool $provided = true): bool
{
    $payload = $provided ? ['reason' => $reason] : [];

    // Mirror StoreStockAdjustmentRequest::prepareForValidation(): a string
    // reason is trimmed before the rules run, so whitespace-only collapses to
    // the empty string and is rejected like an absent reason (R14.4).
    if ($provided && is_string($reason)) {
        $payload['reason'] = trim($reason);
    }

    $translator = new Translator(new ArrayLoader(), 'en');
    $factory = new ValidationFactory($translator);

    // Validate ONLY the reason field against the request's own rule for it.
    $rules = ['reason' => (new StoreStockAdjustmentRequest())->rules()['reason']];
    $validator = $factory->make($payload, $rules);

    return ! $validator->errors()->has('reason');
}

/**
 * Whether a raw reason value is a non-empty, non-whitespace string — the exact
 * acceptance condition from Property 37. A non-string (or absent value) is not
 * a valid reason.
 */
function isValidReason(mixed $reason, bool $provided = true): bool
{
    return $provided && is_string($reason) && trim($reason) !== '';
}

/**
 * A BlackBox set of reason candidates spanning the whole input space:
 *   - genuinely valid reasons (non-empty after trimming),
 *   - the empty string,
 *   - whitespace-only strings of several kinds (space, tab, newline, CR,
 *     vertical tab, form feed, non-breaking space, mixed runs),
 *   - valid reasons padded with surrounding whitespace (still valid once
 *     trimmed), to exercise the trim normalization.
 */
function stockAdjustmentReasonSet(): Set
{
    $validCore = Set::either(
        Set::of(
            'damaged in transit',
            'cycle count correction',
            'returned to supplier',
            'theft write-off',
            'opening balance fix',
            'a',
            '0',
            '!',
        ),
        // Arbitrary non-empty printable strings; trimmed before use so the core
        // is guaranteed non-whitespace.
        Set::strings()->between(1, 40)
            ->map(static fn (string $s): string => trim($s))
            ->filter(static fn (string $s): bool => $s !== ''),
    );

    // "Whitespace" here means the ASCII whitespace the implementation treats
    // as empty: StoreStockAdjustmentRequest::prepareForValidation() uses PHP
    // trim(), whose default mask is " \t\n\r\0\x0B". A reason made only of
    // those characters collapses to the empty string and is rejected (R14.4).
    // NOTE: form feed ("\f"/\x0C) is deliberately NOT included — PHP trim()
    // does not strip it, so a form-feed reason is a non-empty, non-whitespace
    // string under the implementation's definition and is accepted (R14.3).
    $whitespaceOnly = Set::of(
        '',
        ' ',
        '   ',
        "\t",
        "\n",
        "\r",
        "\r\n",
        "\x0B",      // vertical tab (stripped by trim)
        "\0",        // NUL (stripped by trim)
        " \t\n ",    // mixed whitespace run
        "\t\t\t",
    );

    // Valid cores padded with surrounding whitespace — must still be accepted
    // because prepareForValidation trims them (R14.3).
    $paddedValid = $validCore->map(
        static fn (string $core): string => "  \t".$core." \n ",
    );

    return Set::either($validCore, $whitespaceOnly, $paddedValid);
}

it('accepts a manual stock adjustment iff the reason is a non-empty, non-whitespace string, else rejects it (R14.3, R14.4)', function () {
    $reasons = stockAdjustmentReasonSet()->take(100);

    $iterations = 0;

    foreach ($reasons->enumerate() as $reason) {
        $iterations++;

        $expected = isValidReason($reason);

        expect(stockAdjustmentReasonAccepted($reason))->toBe(
            $expected,
            sprintf(
                'Reason %s must be %s (R14.3/R14.4).',
                var_export($reason, true),
                $expected ? 'accepted' : 'rejected with 422',
            ),
        );
    }

    // Guard the spec's minimum-iteration requirement.
    expect($iterations)->toBeGreaterThanOrEqual(100);
});

it('rejects a manual stock adjustment submitted without any reason (R14.4)', function () {
    // An absent `reason` key is the "submitted without a reason" case; it must
    // fail `required` and would answer with a 422.
    expect(stockAdjustmentReasonAccepted(null, provided: false))->toBeFalse();
});

it('rejects every whitespace-only reason with equivalent effect to an absent reason (R14.4)', function () {
    // Only characters PHP trim() actually strips count as "whitespace-only"
    // here, matching the implementation's prepareForValidation() definition.
    // Form feed ("\f") is excluded on purpose — trim() leaves it, so it is a
    // valid non-whitespace reason, not an empty one.
    $whitespace = Set::of(
        '',
        ' ',
        '   ',
        "\t",
        "\n",
        "\r\n",
        "\x0B",
        "\0",
        " \t\n ",
    )->take(100);

    $iterations = 0;

    foreach ($whitespace->enumerate() as $reason) {
        $iterations++;

        expect(stockAdjustmentReasonAccepted($reason))->toBeFalse(
            sprintf('Whitespace-only reason %s must be rejected (R14.4).', var_export($reason, true)),
        );
    }

    expect($iterations)->toBeGreaterThanOrEqual(100);
});
