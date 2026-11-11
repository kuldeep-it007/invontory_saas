<?php

declare(strict_types=1);

use App\Reports\ReportFilters;
use Carbon\CarbonImmutable;
use Innmind\BlackBox\Set;
use Innmind\BlackBox\Set\Provider;

/*
|--------------------------------------------------------------------------
| Task 11.2 — Property 27: Default report date range
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 27: Default report date range
|
| Validates: Requirements 29.2
|
| Property statement (design.md §Property 27):
|   For any report request that omits a date range, the applied range is exactly
|   the first through last day of the previous calendar month relative to the
|   request date.
|
| The defaulting rule lives entirely in the ReportFilters value object:
| withDefaultDateRange() resolves an absent range to the previous calendar
| month — the first instant of its first day (00:00:00.000000) through the last
| instant of its last day (23:59:59.999999), inclusive — relative to a reference
| ("request") date (R29.2). Because every report (standard or AI-driven) inherits
| this one shared default, the invariant is a pure property of ReportFilters and
| runs with no booted app or database.
|
| Generation uses innmind/black-box (NOT hand-rolled): BlackBox `Set`s produce a
| wide spread of reference dates — spanning leap and non-leap Februarys, 28/29/
| 30/31-day months, year boundaries (January -> previous December), first/last
| days of the month, and every time-of-day — and `->take(100)->enumerate()`
| draws the minimum 100 generated iterations the spec mandates.
|
| Each scenario checks the resolved range against an INDEPENDENT reference
| computed with Carbon's month arithmetic, so the test does not merely restate
| the implementation.
*/

/**
 * The independently-computed expected previous-calendar-month window for a
 * reference date: first instant of the first day through the last instant of
 * the last day of the month immediately preceding `$reference`'s month.
 *
 * Uses subMonthNoOverflow() so a reference on, e.g., the 31st does not skip a
 * shorter previous month (March 31 -> February, not "February 31" -> March).
 *
 * @return array{from: CarbonImmutable, to: CarbonImmutable}
 */
function expectedPreviousMonthWindow(CarbonImmutable $reference): array
{
    $previousMonth = $reference->subMonthNoOverflow();

    return [
        'from' => $previousMonth->startOfMonth(),
        'to' => $previousMonth->endOfMonth(),
    ];
}

/**
 * A BlackBox set of reference ("request") dates spanning the full input space
 * that matters for previous-calendar-month resolution:
 *   - years across a wide range, including leap years,
 *   - every month 1..12 (so January exercises the year-boundary case),
 *   - days 1..31 clamped to each month's real length (so the 1st and the last
 *     day of 28/29/30/31-day months are all reachable),
 *   - arbitrary times of day (so the from/to instants are asserted regardless
 *     of the request's clock time).
 */
function referenceDateSet(): Set|Provider
{
    return Set::compose(
        static function (int $year, int $month, int $dayPick, int $hour, int $minute, int $second): CarbonImmutable {
            // Clamp the picked day into the chosen month's real length so we
            // land on valid dates (incl. Feb 28/29 and 30/31-day month ends).
            $daysInMonth = (int) CarbonImmutable::create($year, $month, 1)->daysInMonth;
            $day = (($dayPick - 1) % $daysInMonth) + 1;

            return CarbonImmutable::create($year, $month, $day, $hour, $minute, $second);
        },
        Set::integers()->between(1971, 2099),
        Set::integers()->between(1, 12),
        Set::integers()->between(1, 31),
        Set::integers()->between(0, 23),
        Set::integers()->between(0, 59),
        Set::integers()->between(0, 59),
    );
}

it('resolves an omitted date range to the full previous calendar month relative to the request date (R29.2)', function () {
    $references = referenceDateSet()->take(100);

    $iterations = 0;

    foreach ($references->enumerate() as $reference) {
        $iterations++;

        // A request that omits the date range entirely.
        $resolved = ReportFilters::fromArray([])->withDefaultDateRange($reference);

        $expected = expectedPreviousMonthWindow($reference);

        // The range must be fully defined after defaulting.
        expect($resolved->hasDateRange())->toBeTrue(
            sprintf('Range must be set after defaulting for reference %s.', $reference->toDateTimeString()),
        );

        // from = first instant of the first day of the previous calendar month.
        expect($resolved->from->equalTo($expected['from']))->toBeTrue(
            sprintf(
                'from for reference %s must be %s, got %s (R29.2).',
                $reference->toDateTimeString(),
                $expected['from']->format('Y-m-d H:i:s.u'),
                $resolved->from->format('Y-m-d H:i:s.u'),
            ),
        );

        // to = last instant of the last day of the previous calendar month.
        expect($resolved->to->equalTo($expected['to']))->toBeTrue(
            sprintf(
                'to for reference %s must be %s, got %s (R29.2).',
                $reference->toDateTimeString(),
                $expected['to']->format('Y-m-d H:i:s.u'),
                $resolved->to->format('Y-m-d H:i:s.u'),
            ),
        );

        // Structural guarantees of the window: it starts on day 1 at 00:00:00,
        // ends on the month's last day at 23:59:59, both ends share the SAME
        // (previous) month, and that month is exactly one calendar month before
        // the reference's month.
        expect($resolved->from->day)->toBe(1);
        expect($resolved->from->format('H:i:s'))->toBe('00:00:00');
        expect($resolved->to->format('H:i:s'))->toBe('23:59:59');
        expect($resolved->to->day)->toBe((int) $resolved->to->daysInMonth);
        expect($resolved->from->month)->toBe($resolved->to->month);
        expect($resolved->from->year)->toBe($resolved->to->year);
        expect($resolved->from->addMonthNoOverflow()->format('Y-m'))
            ->toBe($reference->format('Y-m'));
    }

    // Guard the spec's minimum-iteration requirement.
    expect($iterations)->toBeGreaterThanOrEqual(100);
});

it('leaves an explicitly supplied date range untouched regardless of the request date (R29.2)', function () {
    // When a request DOES supply a range, defaulting must never override it;
    // the previous-calendar-month rule applies only to omitted ranges.
    $references = referenceDateSet()->take(100);

    $iterations = 0;

    foreach ($references->enumerate() as $reference) {
        $iterations++;

        $filters = ReportFilters::fromArray(['from' => '2024-06-10', 'to' => '2024-07-20']);
        $resolved = $filters->withDefaultDateRange($reference);

        expect($resolved->from->toDateString())->toBe('2024-06-10');
        expect($resolved->to->toDateString())->toBe('2024-07-20');
    }

    expect($iterations)->toBeGreaterThanOrEqual(100);
});
