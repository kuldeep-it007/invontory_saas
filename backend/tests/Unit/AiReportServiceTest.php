<?php

declare(strict_types=1);

use App\Auth\Roles;
use App\Exceptions\FeatureDisabledException;
use App\Reports\ReportAccessContext;
use App\Reports\ReportDefinition;
use App\Reports\ReportFilters;
use App\Reports\ReportQuerySpec;
use App\Reports\ReportResult;
use App\Reports\ReportType;
use App\Services\Ai\AiReportService;
use App\Services\Ai\LlmClient;
use App\Services\ReportQueryService;
use App\Services\SubscriptionGateService;
use Illuminate\Database\Query\Builder;

/*
|--------------------------------------------------------------------------
| Task 13.3 — AiReportService: AI gating + NL->validated spec over the shared
| ReportQueryService (pure logic, no DB)
|--------------------------------------------------------------------------
|
| Validates: Requirements 30.6, 30.7, 31.1, 31.2, 31.3, 31.4, 31.5, 31.6
|
| The AI layer is a THIN wrapper over the single shared ReportQueryService. It:
|   • GATES on the composite AI gate (env master switch AND tenant flag) and
|     denies with a 403 otherwise (R30.6, R30.7);
|   • MAPS a natural-language request to a STRUCTURED ReportQuerySpec via the
|     LlmClient (R31.1) — never SQL;
|   • VALIDATES the mapped spec against the allow-list BEFORE execution and
|     rejects an invalid spec WITHOUT running a query (R31.2, R31.5);
|   • EXECUTES the validated spec through the SAME ReportQueryService standard
|     reports use (R31.3), returning the standard ReportResult (R31.6);
|   • NEVER executes raw SQL the model emits (R31.4).
|
| These are pure-logic proofs: a stub SubscriptionGateService toggles the gate,
| a stub LlmClient returns canned completions, and a spying ReportQueryService
| records whether/with-what the query layer was reached — so no database, no
| tenancy, and no real LLM are required.
|
*/

/**
 * A stub gate whose composite-AI decision is injected. Mirrors
 * SubscriptionGateService's assert/predicate pair so AiReportService can depend
 * on the real type while the test controls the gate.
 */
function aiStubGate(bool $allowed): SubscriptionGateService
{
    return new class($allowed) extends SubscriptionGateService
    {
        public function __construct(private readonly bool $allowed) {}

        public function aiReportingEnabled(string|int|null $tenantId = null): bool
        {
            return $this->allowed;
        }

        public function assertAiReportingEnabled(string|int|null $tenantId = null): void
        {
            if (! $this->allowed) {
                throw new FeatureDisabledException(\App\Models\PlanFeature::AI_REPORTS);
            }
        }
    };
}

/**
 * A stub LlmClient returning a fixed completion for any prompt.
 */
function aiStubLlm(string $completion): LlmClient
{
    return new class($completion) implements LlmClient
    {
        public function __construct(private readonly string $completion) {}

        public function complete(string $prompt): string
        {
            return $this->completion;
        }
    };
}

/**
 * A spying ReportQueryService over a single stock-on-hand definition with the
 * given allow-lists. Its DB-touching aggregation is replaced by a spy that
 * records the spec it was handed and returns a canned ReportResult, so a test
 * can assert both WHETHER the query layer executed and WITH WHICH spec — with
 * no database. The real run() pipeline (defaults, scoping, allow-list) still
 * runs around the spy.
 */
function aiSpyReportService(array $groupable = [], array $sortable = []): ReportQueryService
{
    $definition = new class($groupable, $sortable) implements ReportDefinition
    {
        public function __construct(public array $groupable, public array $sortable) {}

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
            throw new RuntimeException('baseQuery() must not be reached: the spy replaces aggregation.');
        }

        public function applyGrouping(Builder $query, array $grouping): void {}

        public function sortExpression(string $column): string
        {
            return $column;
        }
    };

    return new class([$definition]) extends ReportQueryService
    {
        public int $executions = 0;

        public ?ReportQuerySpec $executedSpec = null;

        protected function executeIndexedAggregation(ReportQuerySpec $spec, ReportDefinition $definition): ReportResult
        {
            $this->executions++;
            $this->executedSpec = $spec;

            return new ReportResult(
                rows: [['product_id' => 1, 'quantity' => 7]],
                spec: $spec,
                page: $spec->page,
                perPage: $spec->limit,
                total: 1,
            );
        }
    };
}

function aiCompanyAdminContext(): ReportAccessContext
{
    return new ReportAccessContext(
        roles: [Roles::COMPANY_ADMIN],
        canViewReports: true,
    );
}

// ---------------------------------------------------------------------------
// GATE (R30.6, R30.7)
// ---------------------------------------------------------------------------

it('denies with a 403 when the composite AI gate is off, before mapping or executing (R30.6, R30.7)', function () {
    $reports = aiSpyReportService();
    // A well-formed spec would otherwise succeed — the denial must come first.
    $llm = aiStubLlm(json_encode(['type' => 'stock_on_hand']));

    $service = new AiReportService(aiStubGate(false), $llm, $reports);

    $thrown = null;
    try {
        $service->generate('stock on hand', aiCompanyAdminContext());
    } catch (FeatureDisabledException $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeInstanceOf(FeatureDisabledException::class);
    expect($thrown->getStatusCode())->toBe(403);
    // Nothing executed: the gate short-circuits before the query layer.
    expect($reports->executions)->toBe(0);
});

// ---------------------------------------------------------------------------
// MAP + EXECUTE (R31.1, R31.3, R31.6)
// ---------------------------------------------------------------------------

it('maps a natural-language request to a spec and runs it through the shared ReportQueryService (R31.1, R31.3)', function () {
    $reports = aiSpyReportService(groupable: ['product'], sortable: ['quantity']);
    $llm = aiStubLlm(json_encode([
        'type' => 'stock_on_hand',
        'grouping' => ['product'],
        'sort' => [['column' => 'quantity', 'dir' => 'desc']],
        'limit' => 25,
    ]));

    $service = new AiReportService(aiStubGate(true), $llm, $reports);

    $result = $service->generate('top stock on hand by product', aiCompanyAdminContext());

    // Executed exactly once through the shared query layer (R31.3) with the
    // mapped spec (R31.1), and returned the standard ReportResult (R31.6).
    expect($reports->executions)->toBe(1);
    expect($reports->executedSpec->type)->toBe(ReportType::STOCK_ON_HAND);
    expect($reports->executedSpec->grouping)->toBe(['product']);
    expect($result)->toBeInstanceOf(ReportResult::class);
    expect($result->toArray()['meta']['type'])->toBe('stock_on_hand');
});

it('tolerates a chatty model that wraps the JSON spec in prose / a code fence (R31.1)', function () {
    $reports = aiSpyReportService();
    $llm = aiStubLlm("Sure! Here is your spec:\n```json\n{\"type\":\"stock_on_hand\",\"limit\":10}\n```\nHope that helps.");

    $service = new AiReportService(aiStubGate(true), $llm, $reports);

    $result = $service->generate('stock on hand', aiCompanyAdminContext());

    expect($reports->executions)->toBe(1);
    expect($result->toArray()['meta']['type'])->toBe('stock_on_hand');
});

// ---------------------------------------------------------------------------
// VALIDATE / REJECT WITHOUT EXECUTING (R31.2, R31.5)
// ---------------------------------------------------------------------------

it('rejects an unknown report type and executes nothing (R31.2, R31.5)', function () {
    $reports = aiSpyReportService();
    $llm = aiStubLlm(json_encode(['type' => 'definitely_not_a_report']));

    $service = new AiReportService(aiStubGate(true), $llm, $reports);

    expect(fn () => $service->generate('a bogus report', aiCompanyAdminContext()))
        ->toThrow(InvalidArgumentException::class);
    expect($reports->executions)->toBe(0);
});

it('rejects a grouping column outside the allow-list and executes nothing (R31.2, R31.5)', function () {
    $reports = aiSpyReportService(groupable: ['product'], sortable: []);
    $llm = aiStubLlm(json_encode([
        'type' => 'stock_on_hand',
        'grouping' => ['secret_column'],
    ]));

    $service = new AiReportService(aiStubGate(true), $llm, $reports);

    expect(fn () => $service->generate('group by a forbidden column', aiCompanyAdminContext()))
        ->toThrow(InvalidArgumentException::class);
    expect($reports->executions)->toBe(0);
});

it('rejects a sort column outside the allow-list and executes nothing (R31.2, R31.5)', function () {
    $reports = aiSpyReportService(groupable: [], sortable: ['quantity']);
    $llm = aiStubLlm(json_encode([
        'type' => 'stock_on_hand',
        'sort' => [['column' => 'injected', 'dir' => 'asc']],
    ]));

    $service = new AiReportService(aiStubGate(true), $llm, $reports);

    expect(fn () => $service->generate('sort by a forbidden column', aiCompanyAdminContext()))
        ->toThrow(InvalidArgumentException::class);
    expect($reports->executions)->toBe(0);
});

it('rejects an empty / unparseable model completion and executes nothing (R31.5)', function () {
    $reports = aiSpyReportService();
    // The NullLlmClient path: an empty completion yields no spec.
    $llm = aiStubLlm('');

    $service = new AiReportService(aiStubGate(true), $llm, $reports);

    expect(fn () => $service->generate('anything', aiCompanyAdminContext()))
        ->toThrow(InvalidArgumentException::class);
    expect($reports->executions)->toBe(0);
});

// ---------------------------------------------------------------------------
// NEVER EXECUTE RAW SQL (R31.4)
// ---------------------------------------------------------------------------

it('never carries raw SQL from the model into execution — only the structured fields are read (R31.4)', function () {
    $reports = aiSpyReportService();
    // The model tries to smuggle raw SQL alongside a valid structured spec. The
    // `sql` key must be ignored entirely; only the structured spec runs.
    $llm = aiStubLlm(json_encode([
        'type' => 'stock_on_hand',
        'sql' => 'DROP TABLE stock; SELECT * FROM users',
        'limit' => 5,
    ]));

    $service = new AiReportService(aiStubGate(true), $llm, $reports);

    $result = $service->generate('give me everything', aiCompanyAdminContext());

    // It executed via the structured spec only — the spec the query layer saw
    // is a plain ReportQuerySpec with no SQL anywhere on it.
    expect($reports->executions)->toBe(1);
    expect($reports->executedSpec)->toBeInstanceOf(ReportQuerySpec::class);
    expect($reports->executedSpec->type)->toBe(ReportType::STOCK_ON_HAND);
    expect($result->toArray()['meta']['type'])->toBe('stock_on_hand');
});
