<?php

declare(strict_types=1);

use App\Auth\Roles;
use App\Models\AiReport;
use App\Models\User;
use App\Reports\ReportAccessContext;
use App\Reports\ReportDefinition;
use App\Reports\ReportFilters;
use App\Reports\ReportQuerySpec;
use App\Reports\ReportResult;
use App\Reports\ReportType;
use App\Services\Ai\AiReportService;
use App\Services\Ai\DatasetMinimizer;
use App\Services\Ai\LlmClient;
use App\Services\Ai\PiiRedactor;
use App\Services\ReportQueryService;
use App\Services\SubscriptionGateService;
use App\Support\AuditRecorder;
use Illuminate\Database\Query\Builder;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Task 13.8 — Property 35 (AI portion): Audit coverage for AI reporting
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 35 (AI portion): Audit
| coverage for AI reporting
|
| Validates: Requirements 32.5
|
| Property statement (tasks.md §13.8 / design.md "AI Reporting Layer", R32.5):
|   EVERY AI report/summary/follow-up request produces an audit entry. Across
|   ANY generated sequence of AI requests — generate(), summarize(), and
|   followUp(), in any order and for any acting principal — each request records
|   EXACTLY ONE corresponding audit entry in the Audit_Log, and that entry
|   captures the correct event name (ai_report.generate / .summary / .followup)
|   AND the acting principal (actor).
|
| Strategy — pure logic, no database, no tenancy, no real LLM.
|   AiReportService records audits via an injected AuditRecorder (its best-effort
|   recordAudit() hook). We inject a SPY AuditRecorder that captures every
|   (event, user) tuple instead of writing a row, and drive the three operations
|   with:
|     • a permitting SubscriptionGateService stub (the gate is proven elsewhere,
|       Property 19 — here it must always permit so the request reaches the audit
|       step);
|     • a stub LlmClient returning a canned, well-formed spec for generate() and
|       a canned completion for summarize()/followUp();
|     • a spy ReportQueryService whose DB aggregation is replaced so generate()
|       runs end-to-end without a database;
|     • fake persisted AiReport rows (save() is a no-op) for summarize()/
|       followUp(), created by the acting principal so the R32.4 read scope is
|       satisfied and the request reaches the audit step.
|
|   For each generated sequence we assert the running invariant: after every
|   request the audit spy holds EXACTLY as many entries as requests issued so
|   far (one-per-request — never zero, never duplicated), and the last entry
|   carries the expected event + actor id for the kind of request just made.
|
|   Generation uses innmind/black-box (NOT hand-rolled) via the shared
|   static::forAll(), drawing the mandated minimum of 100 generated scenarii per
|   proof and shrinking any counterexample to a minimal failing input.
*/

/**
 * A gate that always permits AI reporting, so every request reaches the audit
 * step. (The composite gate itself is proven by Property 19.)
 */
function auditCovAllowGate(): SubscriptionGateService
{
    return new class extends SubscriptionGateService
    {
        public function assertAiReportingEnabled(string|int|null $tenantId = null): void {}

        public function aiReportingEnabled(string|int|null $tenantId = null): bool
        {
            return true;
        }
    };
}

/**
 * A stub LlmClient returning a fixed completion for any prompt — a well-formed
 * JSON spec drives generate(); any non-empty string drives summarize()/
 * followUp().
 */
function auditCovLlm(string $completion): LlmClient
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
 * A spy AuditRecorder that captures every (event, actorId) tuple it is asked to
 * record instead of writing to the database.
 */
function auditCovSpy(): AuditRecorder
{
    return new class extends AuditRecorder
    {
        /** @var list<array{event:string, actorId:int|null}> */
        public array $entries = [];

        public function record(string $event, ?User $user = null, array $context = [], ?\Illuminate\Http\Request $request = null, ?string $reason = null): void
        {
            $this->entries[] = [
                'event' => $event,
                'actorId' => $user?->getKey() === null ? null : (int) $user->getKey(),
            ];
        }
    };
}

/**
 * A spy ReportQueryService whose DB aggregation is replaced so generate() runs
 * end-to-end with no database. Returns a canned single-row ReportResult.
 */
function auditCovReportService(): ReportQueryService
{
    $definition = new class implements ReportDefinition
    {
        public function type(): ReportType
        {
            return ReportType::SALES;
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
        protected function executeIndexedAggregation(ReportQuerySpec $spec, ReportDefinition $definition): ReportResult
        {
            return new ReportResult(
                rows: [['product_name' => 'Widget', 'total' => 42]],
                spec: $spec,
                page: $spec->page,
                perPage: $spec->limit,
                total: 1,
            );
        }
    };
}

/**
 * Build the service under test wired to the spy audit recorder. persist() is
 * best-effort and swallows failures (no tenancy here), so generation still
 * reaches the audit step.
 */
function auditCovService(AuditRecorder $audit): AiReportService
{
    return new AiReportService(
        auditCovAllowGate(),
        auditCovLlm(json_encode(['type' => 'sales'])),
        auditCovReportService(),
        new DatasetMinimizer,
        new PiiRedactor,
        $audit,
    );
}

/**
 * A persisted-looking AiReport whose save() is a no-op (no DB), created by the
 * given principal so a tenant-wide context (used in these proofs) can read it.
 */
function auditCovReport(?int $createdBy): AiReport
{
    $report = new class extends AiReport
    {
        public function save(array $options = []): bool
        {
            return true;
        }
    };

    $report->exists = true;
    $report->forceFill([
        'id' => 1,
        'prompt' => 'sales last month',
        'report_type' => 'sales',
        'dataset' => [
            ['product_name' => 'Widget', 'total' => 42],
            ['product_name' => 'Gadget', 'total' => 8],
        ],
        'created_by' => $createdBy,
    ]);

    return $report;
}

/** A lightweight User with a known id used as the acting principal. */
function auditCovActor(int $id): User
{
    $user = new User;
    $user->forceFill(['id' => $id]);
    $user->exists = true;

    return $user;
}

/** A tenant-wide access context (reads any report in the tenant — R32.4). */
function auditCovContext(): ReportAccessContext
{
    return new ReportAccessContext(roles: [Roles::COMPANY_ADMIN], canViewReports: true);
}

/**
 * A BlackBox set of the three AI request kinds, so a generated sequence mixes
 * generate / summary / follow-up in any order.
 */
function auditCovKindSet(): Set
{
    return Set::of('generate', 'summary', 'followup');
}

/**
 * The audit event name AiReportService records for each request kind — an
 * independent oracle (not a re-call of the service) so a drift in the mapping
 * is caught.
 */
function auditCovExpectedEvent(string $kind): string
{
    return match ($kind) {
        'generate' => AiReportService::EVENT_GENERATE,
        'summary' => AiReportService::EVENT_SUMMARY,
        'followup' => AiReportService::EVENT_FOLLOWUP,
    };
}

it('records exactly one audit entry — capturing the event and actor — for every AI request across any generated sequence of generate/summary/follow-up (R32.5)', function () {
    static::forAll(
        // A sequence of 1..8 request kinds, in any order.
        Set::sequence(auditCovKindSet())->between(1, 8),
        // The acting principal's id for the whole sequence.
        Set::integers()->between(1, 100000),
    )->then(function (array $kinds, int $actorId) {
        $audit = auditCovSpy();
        $service = auditCovService($audit);
        $actor = auditCovActor($actorId);
        $context = auditCovContext();

        $issued = 0;

        foreach ($kinds as $kind) {
            match ($kind) {
                'generate' => $service->generate('sales last month', $context, $actor),
                // Report created BY this actor so the tenant-wide context reads
                // it and the request reaches the audit step (R32.4).
                'summary' => $service->summarize(auditCovReport($actorId), $context, $actor),
                'followup' => $service->followUp(auditCovReport($actorId), 'which product leads?', $context, $actor),
            };

            $issued++;

            // One-per-request invariant: after N requests there are EXACTLY N
            // audit entries — never zero (every request is audited) and never
            // duplicated.
            expect($audit->entries)->toHaveCount(
                $issued,
                sprintf('After %d AI request(s) the audit log must hold exactly %d entries.', $issued, $issued),
            );

            // The entry just written carries the correct event + actor (R32.5).
            $last = $audit->entries[$issued - 1];
            expect($last['event'])->toBe(
                auditCovExpectedEvent($kind),
                sprintf('A [%s] request must record the [%s] audit event.', $kind, auditCovExpectedEvent($kind)),
            );
            expect($last['actorId'])->toBe(
                $actorId,
                'The audit entry must capture the acting principal.',
            );
        }

        // Total coverage: one audit entry per request issued, no more, no less.
        expect($audit->entries)->toHaveCount(count($kinds));
    });
});

it('records a generate audit entry for every single generation request (R32.5)', function () {
    static::forAll(
        Set::integers()->between(1, 100000),
    )->then(function (int $actorId) {
        $audit = auditCovSpy();
        $service = auditCovService($audit);

        $service->generate('sales last month', auditCovContext(), auditCovActor($actorId));

        expect($audit->entries)->toHaveCount(1);
        expect($audit->entries[0]['event'])->toBe(AiReportService::EVENT_GENERATE);
        expect($audit->entries[0]['actorId'])->toBe($actorId);
    });
});
