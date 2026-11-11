<?php

declare(strict_types=1);

use App\Reports\ReportAccessContext;
use App\Reports\ReportDefinition;
use App\Reports\ReportFilters;
use App\Reports\ReportQuerySpec;
use App\Reports\ReportResult;
use App\Reports\ReportType;
use App\Services\Ai\AiReportService;
use App\Services\Ai\LlmClient;
use App\Services\ReportCache;
use App\Services\ReportQueryService;
use App\Services\SubscriptionGateService;
use Illuminate\Database\Query\Builder;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Task 13.4 — Property 32: AI query-spec allow-list validation
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 32: AI query-spec allow-list validation
|
| Validates: Requirements 31.2, 31.4, 31.5
|
| Property statement (tasks.md §13.4):
|   Spec executes through the standard query layer iff it passes allow-list
|   validation; raw-SQL attempts and invalid specs rejected and never executed.
|
| Concretely, for ANY natural-language request that an (adversarial) model maps
| to a structured JSON spec, AiReportService.generate():
|   • GATE already passed (the composite AI gate is held open here so the proof
|     isolates the VALIDATE/EXECUTE boundary);
|   • VALIDATES the mapped spec against the allow-listed schema (report type,
|     groupable columns, sortable columns) BEFORE any query runs (R31.2);
|   • EXECUTES the spec through the SAME shared ReportQueryService exactly once
|     IFF it passes validation (R31.3 wiring; the "iff" of Property 32);
|   • REJECTS — without executing anything — a spec with an unsupported report
|     type or a grouping/sort column off that report's allow-list (R31.5);
|   • NEVER carries raw SQL from the model into execution: a `sql` (or any
|     non-structured) key the model smuggles in is ignored entirely, and the
|     object the query layer receives is always a plain ReportQuerySpec with no
|     SQL anywhere on it (R31.4).
|
| Strategy — pure logic, no database, no tenancy, no real LLM.
|   • The gate is a stub that permits (so the proof targets validation only).
|   • The LlmClient is a fake returning a GENERATED JSON spec string — the exact
|     surface an untrusted model controls. Generators span the whole relevant
|     input space: a valid vs unknown report type; grouping/sort columns that
|     are on vs off the allow-list; and the presence of an injected raw-`sql`
|     key (plus other junk keys) that must always be ignored.
|   • The ReportQueryService is the REAL class with only its DB-touching
|     aggregation replaced by a counting/recording spy, so the genuine
|     defaulting + scoping + allow-list pipeline runs around it. The spy records
|     WHETHER it executed and WITH WHICH spec, so the proof can assert the exact
|     "executes iff valid" biconditional and that no SQL ever reaches it.
|
|   An independent oracle re-derives, from the allow-list alone, whether a given
|   generated spec SHOULD pass — so a drift in AiReportService's validator is
|   caught rather than the test merely echoing the implementation.
|
|   Generation uses innmind/black-box (NOT hand-rolled) via the shared
|   static::forAll(), drawing the mandated minimum of 100 generated scenarii per
|   proof and shrinking any counterexample to a minimal failing input.
*/

/**
 * The single report the proof's query layer knows about and its allow-lists.
 * The AI layer may only ever map to this one type; everything else is an
 * "unknown type" that must be rejected before execution.
 */
const AI_ALLOWLIST_TYPE = 'stock_on_hand';

/** @var list<string> columns this report may be grouped by. */
const AI_ALLOWLIST_GROUPABLE = ['product', 'stock_point'];

/** @var list<string> columns this report may be sorted by. */
const AI_ALLOWLIST_SORTABLE = ['quantity', 'product'];

/**
 * A stub gate that always permits AI reporting, so the proof isolates the
 * VALIDATE/EXECUTE boundary (the gate itself is proven by Property 19 / task
 * 13.3). Mirrors SubscriptionGateService's assert/predicate pair.
 */
function aiAllowListStubGate(): SubscriptionGateService
{
    return new class extends SubscriptionGateService
    {
        public function aiReportingEnabled(string|int|null $tenantId = null): bool
        {
            return true;
        }

        public function assertAiReportingEnabled(string|int|null $tenantId = null): void
        {
            // Always permitted.
        }
    };
}

/**
 * A fake LlmClient returning a fixed completion string for any prompt — the
 * untrusted surface a model controls. The generated JSON spec is fed verbatim.
 */
function aiAllowListFakeLlm(string $completion): LlmClient
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
 * The real ReportQueryService over a single stock-on-hand definition carrying
 * the fixed allow-lists, with ONLY its DB-touching aggregation replaced by a
 * spy. The authentic defaulting/scoping/allow-list pipeline runs around the
 * spy; the spy records whether it executed and with which spec so the proof can
 * assert the "executes iff valid" biconditional and that no SQL reaches it.
 */
function aiAllowListSpyReportService(): ReportQueryService
{
    $definition = new class implements ReportDefinition
    {
        public function type(): ReportType
        {
            return ReportType::STOCK_ON_HAND;
        }

        public function groupableColumns(): array
        {
            return AI_ALLOWLIST_GROUPABLE;
        }

        public function sortableColumns(): array
        {
            return AI_ALLOWLIST_SORTABLE;
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

    // A non-caching ReportCache so the spy counts EVERY run as an execution.
    // The booted Property suite has a live Cache facade (array store), so the
    // default ReportCache would otherwise serve a second logically-identical
    // resolved spec from cache and the spy would never be invoked — a harness
    // artifact that has nothing to do with the allow-list behaviour under test.
    // Correctness is unaffected: the cache is a pure performance layer whose
    // miss path is exactly this compute() (R29.8).
    $noCache = new class extends ReportCache
    {
        public function remember(ReportQuerySpec $spec, \Closure $compute): ReportResult
        {
            return $compute();
        }
    };

    return new class([$definition], $noCache) extends ReportQueryService
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

/**
 * A tenant-wide (Company_Admin) context, so role scoping is a pass-through and
 * the proof isolates allow-list validation rather than scoping (R28.10 scoping
 * is proven separately by task 11.x).
 */
function aiAllowListContext(): ReportAccessContext
{
    return new ReportAccessContext(
        roles: [\App\Auth\Roles::COMPANY_ADMIN],
        canViewReports: true,
    );
}

/**
 * The independent oracle for Property 32: from the generated spec parts and the
 * fixed allow-list ALONE, decide whether the spec SHOULD pass validation (and
 * therefore execute). Re-derives the rule from the design rather than calling
 * the service, so it genuinely cross-checks the validator.
 *
 * A spec passes iff: its type is the one allow-listed type AND every grouping
 * column is groupable AND every sort column is sortable. The injected raw-`sql`
 * key and any other junk keys are irrelevant to the decision — they must simply
 * be ignored (R31.4).
 *
 * @param  list<string>  $grouping
 * @param  list<string>  $sort
 */
function aiAllowListShouldPass(string $type, array $grouping, array $sort): bool
{
    if ($type !== AI_ALLOWLIST_TYPE) {
        return false;
    }

    foreach ($grouping as $column) {
        if (! in_array($column, AI_ALLOWLIST_GROUPABLE, true)) {
            return false;
        }
    }

    foreach ($sort as $column) {
        if (! in_array($column, AI_ALLOWLIST_SORTABLE, true)) {
            return false;
        }
    }

    return true;
}

/**
 * Build the JSON spec string a model "emits", from generated parts. When
 * $withSql is true an adversarial raw-`sql` key (plus a junk key) is injected
 * alongside the structured fields; it must be ignored and never executed
 * (R31.4).
 *
 * @param  list<string>  $grouping
 * @param  list<string>  $sort
 */
function aiAllowListEncodeSpec(string $type, array $grouping, array $sort, bool $withSql): string
{
    $payload = [
        'type' => $type,
        'grouping' => $grouping,
        'sort' => array_map(static fn (string $c): array => ['column' => $c, 'dir' => 'desc'], $sort),
        'limit' => 25,
    ];

    if ($withSql) {
        // The exact attack Property 32 forbids: raw SQL smuggled on the spec.
        $payload['sql'] = 'DROP TABLE stock; SELECT * FROM users; --';
        $payload['raw'] = 'DELETE FROM invoices';
    }

    return json_encode($payload, JSON_THROW_ON_ERROR);
}

/**
 * A set of candidate report `type` values: the single allow-listed type plus a
 * spread of unsupported ones (other real ReportTypes that this query layer does
 * NOT register, and pure garbage) — every one of which must be rejected before
 * execution (R31.5).
 */
function aiReportTypeSet(): Set
{
    return Set::of(
        AI_ALLOWLIST_TYPE,            // the only type that may execute
        'sales',                      // a real ReportType, but not registered here -> unsupported
        'purchases',                  // ditto
        'outstanding_ageing',         // ditto
        'definitely_not_a_report',    // garbage
        'stock_on_hand; DROP TABLE',  // injection-shaped garbage
    );
}

/**
 * A set of grouping-column lists: empty, each individually-allowed column, a
 * fully-allowed pair, and lists containing an off-allow-list column (which must
 * force rejection — R31.2).
 *
 * @return Set
 */
function aiGroupingSet(): Set
{
    return Set::of(
        [],
        ['product'],
        ['stock_point'],
        ['product', 'stock_point'],
        ['secret_column'],
        ['product', 'secret_column'],
        ['stock; DROP TABLE stock'],
    );
}

/**
 * A set of sort-column lists mirroring the grouping set against the SORTABLE
 * allow-list, including off-allow-list columns that must force rejection.
 *
 * @return Set
 */
function aiSortSet(): Set
{
    return Set::of(
        [],
        ['quantity'],
        ['product'],
        ['quantity', 'product'],
        ['injected'],
        ['quantity', 'injected'],
        ['1=1 OR true'],
    );
}

it('executes a mapped spec through the shared query layer IFF it passes allow-list validation, and never executes raw SQL (R31.2, R31.4, R31.5)', function () {
    static::forAll(
        aiReportTypeSet(),
        aiGroupingSet(),
        aiSortSet(),
        // Whether the model also smuggles a raw `sql` key onto the spec.
        Set::of(true, false),
    )->then(function (string $type, array $grouping, array $sort, bool $withSql) {
        $reports = aiAllowListSpyReportService();
        $llm = aiAllowListFakeLlm(aiAllowListEncodeSpec($type, $grouping, $sort, $withSql));

        $service = new AiReportService(aiAllowListStubGate(), $llm, $reports);

        $shouldPass = aiAllowListShouldPass($type, $grouping, $sort);

        $thrown = null;
        $result = null;
        try {
            $result = $service->generate('a generated natural-language request', aiAllowListContext());
        } catch (InvalidArgumentException $e) {
            $thrown = $e;
        }

        $label = sprintf(
            'type=%s grouping=%s sort=%s withSql=%s',
            $type,
            json_encode($grouping),
            json_encode($sort),
            $withSql ? 'true' : 'false',
        );

        if ($shouldPass) {
            // A valid spec: executed EXACTLY once through the shared layer and
            // returned the standard ReportResult (the forward direction of the
            // biconditional).
            expect($thrown)->toBeNull("valid spec must not be rejected: {$label}");
            expect($reports->executions)->toBe(1, "valid spec must execute exactly once: {$label}");
            expect($result)->toBeInstanceOf(ReportResult::class);

            // What the query layer received is a plain ReportQuerySpec carrying
            // ONLY structured, allow-listed fields — never any SQL (R31.4).
            $executed = $reports->executedSpec;
            expect($executed)->toBeInstanceOf(ReportQuerySpec::class);
            expect($executed->type)->toBe(ReportType::STOCK_ON_HAND);
            expect($executed->grouping)->toBe($grouping, "grouping must round-trip exactly: {$label}");
            foreach ($executed->sort as $i => $directive) {
                expect($directive->column)->toBe($sort[$i], "sort column must round-trip exactly: {$label}");
            }
            // ReportQuerySpec has no property that could carry raw SQL — assert
            // its public shape is exactly the structured fields.
            $props = array_keys(get_object_vars($executed));
            sort($props);
            expect($props)->toBe(['filters', 'grouping', 'limit', 'page', 'sort', 'type']);
        } else {
            // An invalid spec: rejected and NOTHING executed (the reverse
            // direction, and R31.5 "without executing any query").
            expect($thrown)->toBeInstanceOf(
                InvalidArgumentException::class,
                "invalid spec must be rejected: {$label}",
            );
            expect($reports->executions)->toBe(0, "invalid spec must NOT execute any query: {$label}");
            expect($reports->executedSpec)->toBeNull("invalid spec must never reach the query layer: {$label}");
        }
    });
});

it('always ignores an injected raw-SQL key: a valid spec executes identically with or without it, SQL is never carried (R31.4)', function () {
    static::forAll(
        aiGroupingSet(),
        aiSortSet(),
    )->then(function (array $grouping, array $sort) {
        // Restrict to specs that are otherwise valid so this proof isolates the
        // "raw SQL is inert" guarantee on the EXECUTE path.
        if (! aiAllowListShouldPass(AI_ALLOWLIST_TYPE, $grouping, $sort)) {
            return;
        }

        $run = function (bool $withSql) use ($grouping, $sort): ReportQuerySpec {
            $reports = aiAllowListSpyReportService();
            $llm = aiAllowListFakeLlm(aiAllowListEncodeSpec(AI_ALLOWLIST_TYPE, $grouping, $sort, $withSql));
            $service = new AiReportService(aiAllowListStubGate(), $llm, $reports);

            $service->generate('give me everything', aiAllowListContext());

            expect($reports->executions)->toBe(1);

            return $reports->executedSpec;
        };

        $withoutSql = $run(false);
        $withSql = $run(true);

        // The injected `sql`/`raw` keys change NOTHING about the executed spec:
        // same type, grouping, sort, limit, page. Raw SQL is wholly inert.
        expect($withSql->type)->toBe($withoutSql->type);
        expect($withSql->grouping)->toBe($withoutSql->grouping);
        expect($withSql->limit)->toBe($withoutSql->limit);
        expect($withSql->page)->toBe($withoutSql->page);
        expect(array_map(static fn ($d) => [$d->column, $d->direction], $withSql->sort))
            ->toBe(array_map(static fn ($d) => [$d->column, $d->direction], $withoutSql->sort));
    });
});
