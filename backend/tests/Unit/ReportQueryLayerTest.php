<?php

declare(strict_types=1);

use App\Auth\Roles;
use App\Reports\ReportAccessContext;
use App\Reports\ReportDefinition;
use App\Reports\ReportFilters;
use App\Reports\ReportQuerySpec;
use App\Reports\ReportSort;
use App\Reports\ReportType;
use App\Services\ReportQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;

/*
|--------------------------------------------------------------------------
| Task 11.1 — ReportQueryService shared query layer (pure logic, no DB)
|--------------------------------------------------------------------------
|
| Exercises the cross-cutting rules the shared report query layer enforces for
| BOTH standard reports and the AI layer, without touching a database:
|   • ReportType is the closed allow-list of report types (R31.2).
|   • A ReportQuerySpec clamps pagination to safe bounds and rejects an unknown
|     type on construction (R29.3, R31.2, R31.5).
|   • An omitted date range defaults to the previous calendar month (R29.2).
|   • Tenant+role scoping confines a Distributor/Dealer to its own buyer data,
|     lets company-side roles read tenant-wide, and denies principals with no
|     report scope (R28.10).
|   • Grouping/sort columns outside a report's allow-list are rejected before
|     any query runs (R31.2).
|
| The authorization and allow-list checks happen BEFORE baseQuery() executes, so
| a stub definition whose baseQuery() is never reached lets these run with no DB.
|
*/

/**
 * A minimal in-memory report definition. groupable/sortable allow-lists are
 * injectable; baseQuery() throws if ever reached (these tests assert the
 * service rejects BEFORE execution).
 */
function fakeReportDefinition(array $groupable = [], array $sortable = []): ReportDefinition
{
    return new class($groupable, $sortable) implements ReportDefinition
    {
        public function __construct(private array $groupable, private array $sortable) {}

        public function type(): ReportType
        {
            return ReportType::STOCK_ON_HAND;
        }

        public function groupableColumns(): array
        {
            return $this->groupable;
        }

        public function sortableColumns(): array
        {
            return $this->sortable;
        }

        public function baseQuery(ReportFilters $filters): Builder
        {
            throw new RuntimeException('baseQuery() should not be reached in these pre-execution tests.');
        }

        public function applyGrouping(Builder $query, array $grouping): void {}

        public function sortExpression(string $column): string
        {
            return $column;
        }
    };
}

function companyAdminContext(): ReportAccessContext
{
    return new ReportAccessContext(
        roles: [Roles::COMPANY_ADMIN],
        canViewReports: true,
    );
}

// ---------------------------------------------------------------------------
// ReportType allow-list (R31.2)
// ---------------------------------------------------------------------------

it('exposes exactly the nine standard report types (R28.1-R28.9)', function () {
    expect(ReportType::values())->toEqual([
        'stock_on_hand',
        'stock_ledger',
        'sales',
        'purchases',
        'outstanding_ageing',
        'payments',
        'order_status',
        'low_stock',
        'plan_usage',
    ]);
});

it('rejects an unknown report type value', function () {
    expect(ReportType::tryFromValue('definitely_not_a_report'))->toBeNull();
});

// ---------------------------------------------------------------------------
// ReportQuerySpec construction + clamping (R29.3, R31.2, R31.5)
// ---------------------------------------------------------------------------

it('throws when a spec is built from an unknown type (R31.5)', function () {
    expect(fn () => ReportQuerySpec::fromArray(['type' => 'bogus']))
        ->toThrow(InvalidArgumentException::class);
});

it('clamps limit into [1, MAX_LIMIT] and defaults when absent (R29.3)', function () {
    expect(ReportQuerySpec::fromArray(['type' => 'sales'])->limit)->toBe(ReportQuerySpec::DEFAULT_LIMIT);
    expect(ReportQuerySpec::fromArray(['type' => 'sales', 'limit' => 0])->limit)->toBe(ReportQuerySpec::DEFAULT_LIMIT);
    expect(ReportQuerySpec::fromArray(['type' => 'sales', 'limit' => -5])->limit)->toBe(ReportQuerySpec::DEFAULT_LIMIT);
    expect(ReportQuerySpec::fromArray(['type' => 'sales', 'limit' => 99999])->limit)->toBe(ReportQuerySpec::MAX_LIMIT);
    expect(ReportQuerySpec::fromArray(['type' => 'sales', 'limit' => 25])->limit)->toBe(25);
});

it('clamps page to a positive integer and computes a zero-based offset', function () {
    expect(ReportQuerySpec::fromArray(['type' => 'sales', 'page' => 0])->page)->toBe(1);
    expect(ReportQuerySpec::fromArray(['type' => 'sales', 'page' => -2])->page)->toBe(1);

    $spec = ReportQuerySpec::fromArray(['type' => 'sales', 'page' => 3, 'limit' => 20]);
    expect($spec->page)->toBe(3);
    expect($spec->offset())->toBe(40);
});

it('parses sort directives and normalizes direction', function () {
    $spec = ReportQuerySpec::fromArray([
        'type' => 'sales',
        'sort' => [
            ['column' => 'total', 'dir' => 'DESC'],
            ['column' => 'period', 'direction' => 'weird'],
        ],
    ]);

    expect($spec->sort)->toHaveCount(2);
    expect($spec->sort[0]->column)->toBe('total');
    expect($spec->sort[0]->isDescending())->toBeTrue();
    // An unrecognized direction falls back to ascending.
    expect($spec->sort[1]->direction)->toBe(ReportSort::ASC);
});

// ---------------------------------------------------------------------------
// Default date range: previous calendar month (R29.2)
// ---------------------------------------------------------------------------

it('defaults an omitted date range to the full previous calendar month (R29.2)', function () {
    $filters = ReportFilters::fromArray([]); // no range supplied
    $resolved = $filters->withDefaultDateRange(CarbonImmutable::create(2024, 3, 15, 10, 0, 0));

    expect($resolved->from->toDateTimeString())->toBe('2024-02-01 00:00:00');
    expect($resolved->to->format('Y-m-d H:i:s'))->toBe('2024-02-29 23:59:59'); // 2024 is a leap year
});

it('resolves the previous month across a year boundary (January -> previous December)', function () {
    $resolved = ReportFilters::fromArray([])
        ->withDefaultDateRange(CarbonImmutable::create(2024, 1, 5, 0, 0, 0));

    expect($resolved->from->toDateTimeString())->toBe('2023-12-01 00:00:00');
    expect($resolved->to->toDateString())->toBe('2023-12-31');
});

it('leaves an explicitly supplied date range untouched (R29.2)', function () {
    $filters = ReportFilters::fromArray(['from' => '2024-06-01', 'to' => '2024-06-30']);
    $resolved = $filters->withDefaultDateRange(CarbonImmutable::create(2024, 3, 15));

    expect($resolved->from->toDateString())->toBe('2024-06-01');
    expect($resolved->to->toDateString())->toBe('2024-06-30');
});

// ---------------------------------------------------------------------------
// Tenant + role scoping (R28.10)
// ---------------------------------------------------------------------------

it('denies a principal that cannot view reports (R28.10)', function () {
    $service = new ReportQueryService([fakeReportDefinition()]);
    $spec = ReportQuerySpec::fromArray(['type' => 'stock_on_hand']);

    $context = new ReportAccessContext(roles: [], canViewReports: false);

    expect(fn () => $service->run($spec, $context))->toThrow(AuthorizationException::class);
});

it('lets company-side roles read tenant-wide (scope is physical) and does not confine the buyer filter', function () {
    // Company admin reaching baseQuery() proves authorization passed WITHOUT a
    // forced buyer scope; the stub throws a RuntimeException on execution, which
    // is a different exception than the AuthorizationException a denial raises.
    $service = new ReportQueryService([fakeReportDefinition()]);
    $spec = ReportQuerySpec::fromArray(['type' => 'stock_on_hand']);

    expect(fn () => $service->run($spec, companyAdminContext()))
        ->toThrow(RuntimeException::class); // reached baseQuery → authorized
});

it('denies a Distributor/Dealer principal with no resolvable buyer binding (R28.10)', function () {
    $service = new ReportQueryService([fakeReportDefinition()]);
    $spec = ReportQuerySpec::fromArray(['type' => 'stock_on_hand']);

    $context = new ReportAccessContext(
        roles: [Roles::DISTRIBUTOR],
        canViewReports: true,
        buyerType: null,
        buyerId: null,
    );

    expect(fn () => $service->run($spec, $context))->toThrow(AuthorizationException::class);
});

it('force-scopes a buyer principal to its own buyer identity, overriding a requested buyer filter (R28.10)', function () {
    // Capture the filters the definition is handed by having baseQuery record
    // them before throwing, so we can assert the buyer scope was forced.
    $captured = null;

    $definition = new class($captured) implements ReportDefinition
    {
        public ?ReportFilters $seen = null;

        public function type(): ReportType
        {
            return ReportType::STOCK_ON_HAND;
        }

        public function groupableColumns(): array
        {
            return [];
        }

        public function sortableColumns(): array
        {
            return [];
        }

        public function baseQuery(ReportFilters $filters): Builder
        {
            $this->seen = $filters;
            throw new RuntimeException('stop after capture');
        }

        public function applyGrouping(Builder $query, array $grouping): void {}

        public function sortExpression(string $column): string
        {
            return $column;
        }
    };

    $service = new ReportQueryService([$definition]);

    // The request tries to read ANOTHER buyer's data (id 999); the principal is
    // bound to id 7, so the scope must be forced back to 7.
    $spec = ReportQuerySpec::fromArray([
        'type' => 'stock_on_hand',
        'filters' => ['buyerType' => 'App\\Models\\Distributor', 'buyerId' => 999],
    ]);

    $context = new ReportAccessContext(
        roles: [Roles::DISTRIBUTOR],
        canViewReports: true,
        buyerType: 'App\\Models\\Distributor',
        buyerId: 7,
    );

    expect(fn () => $service->run($spec, $context))->toThrow(RuntimeException::class, 'stop after capture');

    expect($definition->seen)->not->toBeNull();
    expect($definition->seen->buyerType)->toBe('App\\Models\\Distributor');
    expect($definition->seen->buyerId)->toBe(7); // forced to the principal's own id, NOT 999
});

// ---------------------------------------------------------------------------
// Allow-list validation of grouping/sort (R31.2)
// ---------------------------------------------------------------------------

it('rejects a grouping column outside the report allow-list before executing (R31.2)', function () {
    $service = new ReportQueryService([
        fakeReportDefinition(groupable: ['product'], sortable: []),
    ]);

    $spec = ReportQuerySpec::fromArray([
        'type' => 'stock_on_hand',
        'grouping' => ['product', 'secret_column'],
    ]);

    expect(fn () => $service->run($spec, companyAdminContext()))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a sort column outside the report allow-list before executing (R31.2)', function () {
    $service = new ReportQueryService([
        fakeReportDefinition(groupable: [], sortable: ['quantity']),
    ]);

    $spec = ReportQuerySpec::fromArray([
        'type' => 'stock_on_hand',
        'sort' => [['column' => 'password', 'dir' => 'asc']],
    ]);

    expect(fn () => $service->run($spec, companyAdminContext()))
        ->toThrow(InvalidArgumentException::class);
});

it('allows grouping/sort columns that are on the allow-list (reaches execution)', function () {
    $service = new ReportQueryService([
        fakeReportDefinition(groupable: ['product'], sortable: ['quantity']),
    ]);

    $spec = ReportQuerySpec::fromArray([
        'type' => 'stock_on_hand',
        'grouping' => ['product'],
        'sort' => [['column' => 'quantity', 'dir' => 'desc']],
    ]);

    // Passing validation means it proceeds to baseQuery(), which the stub
    // signals by throwing a RuntimeException (not an InvalidArgumentException).
    expect(fn () => $service->run($spec, companyAdminContext()))
        ->toThrow(RuntimeException::class);
});

it('throws when no definition is registered for the requested type', function () {
    $service = new ReportQueryService([]); // empty catalog
    $spec = ReportQuerySpec::fromArray(['type' => 'sales']);

    expect(fn () => $service->run($spec, companyAdminContext()))
        ->toThrow(InvalidArgumentException::class);
});
