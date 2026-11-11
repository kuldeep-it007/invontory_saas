<?php

declare(strict_types=1);

use App\Auth\Roles;
use App\Exceptions\FeatureDisabledException;
use App\Models\AiReport;
use App\Reports\ReportAccessContext;
use App\Services\Ai\AiReportService;
use App\Services\Ai\DatasetMinimizer;
use App\Services\Ai\LlmClient;
use App\Services\Ai\PiiRedactor;
use App\Services\ReportQueryService;
use App\Services\SubscriptionGateService;
use App\Support\AuditRecorder;
use Illuminate\Auth\Access\AuthorizationException;

/*
|--------------------------------------------------------------------------
| Task 13.6 — AI summaries, follow-ups, PII redaction, and audit
|--------------------------------------------------------------------------
|
| Supports Requirements 32.1, 32.2, 32.3, 32.4, 32.5.
|
| Pure-logic proofs using stubs (no DB, no tenancy, no real LLM):
|   • summaries/follow-ups are computed SOLELY from the persisted dataset,
|     evidenced by the model receiving the dataset payload and nothing else
|     (R32.1, R32.2);
|   • only the MINIMUM aggregated data is sent with PII REDACTED — the captured
|     prompt contains no PII values (R32.3);
|   • a principal that cannot read a report is denied (R32.4);
|   • every summary/follow-up request is recorded in the audit log (R32.5).
*/

/** A gate that always permits AI reporting. */
function aiAllowGate(): SubscriptionGateService
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

/** A gate that always denies AI reporting (403). */
function aiDenyGate(): SubscriptionGateService
{
    return new class extends SubscriptionGateService
    {
        public function assertAiReportingEnabled(string|int|null $tenantId = null): void
        {
            throw new FeatureDisabledException(\App\Models\PlanFeature::AI_REPORTS);
        }
    };
}

/** An LlmClient that captures the last prompt it was asked to complete. */
function aiCapturingLlm(string $completion): LlmClient
{
    return new class($completion) implements LlmClient
    {
        public string $lastPrompt = '';

        public function __construct(private readonly string $completion) {}

        public function complete(string $prompt): string
        {
            $this->lastPrompt = $prompt;

            return $this->completion;
        }
    };
}

/** An AuditRecorder that records the events it was asked to write. */
function aiSpyAudit(): AuditRecorder
{
    return new class extends AuditRecorder
    {
        /** @var list<string> */
        public array $events = [];

        public function record(string $event, ?\App\Models\User $user = null, array $context = [], ?\Illuminate\Http\Request $request = null, ?string $reason = null): void
        {
            $this->events[] = $event;
        }
    };
}

/**
 * A persisted-looking AiReport whose save() is a no-op (no DB).
 *
 * The anonymous subclass deliberately has NO custom constructor: Eloquent's
 * event machinery re-instantiates models via `new static`, so a required
 * constructor would break. Attributes are set on the returned instance instead.
 */
function aiReportWithDataset(array $dataset, ?int $createdBy = null): AiReport
{
    $report = new class extends AiReport
    {
        public function save(array $options = []): bool
        {
            return true; // no DB in a pure-logic test
        }
    };

    $report->exists = true;
    $report->forceFill([
        'id' => 1,
        'prompt' => 'sales last month',
        'report_type' => 'sales',
        'dataset' => $dataset,
        'created_by' => $createdBy,
    ]);

    return $report;
}

function aiService(LlmClient $llm, SubscriptionGateService $gate, ?AuditRecorder $audit = null): AiReportService
{
    // ReportQueryService is unused by summarize/followUp; a bare instance is fine.
    return new AiReportService(
        $gate,
        $llm,
        new ReportQueryService,
        new DatasetMinimizer,
        new PiiRedactor,
        $audit,
    );
}

function aiTenantWideContext(): ReportAccessContext
{
    return new ReportAccessContext(roles: [Roles::COMPANY_ADMIN], canViewReports: true);
}

// ---------------------------------------------------------------------------
// R32.1 — summary computed from the dataset; R32.3 — minimized + PII-redacted
// ---------------------------------------------------------------------------

it('summarises using only the dataset and sends no PII to the model (R32.1, R32.3)', function () {
    $llm = aiCapturingLlm('Sales are concentrated in the top product.');
    $audit = aiSpyAudit();
    $service = aiService($llm, aiAllowGate(), $audit);

    $report = aiReportWithDataset([
        ['product_name' => 'Widget', 'buyer_name' => 'Acme Traders', 'buyer_email' => 'owner@acme.test', 'total' => 5000],
        ['product_name' => 'Gadget', 'buyer_name' => 'Beta Co', 'buyer_email' => 'ceo@beta.test', 'total' => 1200],
    ]);

    $summary = $service->summarize($report, aiTenantWideContext());

    expect($summary)->toBe('Sales are concentrated in the top product.');

    // The prompt the model saw carries the aggregated dataset but NO PII values.
    expect($llm->lastPrompt)->not->toContain('Acme Traders');
    expect($llm->lastPrompt)->not->toContain('owner@acme.test');
    expect($llm->lastPrompt)->not->toContain('ceo@beta.test');
    // Aggregated measures ARE present (minimum aggregated data — R32.3).
    expect($llm->lastPrompt)->toContain('6200'); // sum of totals
    // Redaction placeholder is present where PII values were.
    expect($llm->lastPrompt)->toContain(PiiRedactor::PLACEHOLDER);

    // The summary request is audited (R32.5).
    expect($audit->events)->toContain(AiReportService::EVENT_SUMMARY);
});

it('degrades to a dataset-derived summary when the model yields nothing (R32.1)', function () {
    $llm = aiCapturingLlm(''); // NullLlmClient-style no-op
    $service = aiService($llm, aiAllowGate());

    $report = aiReportWithDataset([
        ['product_name' => 'Widget', 'total' => 10],
        ['product_name' => 'Gadget', 'total' => 30],
    ]);

    $summary = $service->summarize($report, aiTenantWideContext());

    // A factual, dataset-only fallback — never invented data.
    expect($summary)->toContain('2 row');
    expect($summary)->toContain('40'); // sum of totals
});

// ---------------------------------------------------------------------------
// R32.2 — follow-up answered only from the dataset
// ---------------------------------------------------------------------------

it('answers a follow-up using only the dataset and audits the request (R32.2, R32.5)', function () {
    $llm = aiCapturingLlm('The highest total is 5000.');
    $audit = aiSpyAudit();
    $service = aiService($llm, aiAllowGate(), $audit);

    $report = aiReportWithDataset([
        ['product_name' => 'Widget', 'customer_phone' => '9876543210', 'total' => 5000],
    ]);

    $answer = $service->followUp($report, 'What is the highest total?', aiTenantWideContext());

    expect($answer)->toBe('The highest total is 5000.');
    expect($llm->lastPrompt)->not->toContain('9876543210');
    expect($llm->lastPrompt)->toContain('What is the highest total?');
    expect($audit->events)->toContain(AiReportService::EVENT_FOLLOWUP);
});

it('rejects an empty follow-up question (R32.2)', function () {
    $service = aiService(aiCapturingLlm('x'), aiAllowGate());
    $report = aiReportWithDataset([['total' => 1]]);

    expect(fn () => $service->followUp($report, '   ', aiTenantWideContext()))
        ->toThrow(InvalidArgumentException::class);
});

// ---------------------------------------------------------------------------
// R30.6/R30.7 gate + R32.4 scope
// ---------------------------------------------------------------------------

it('denies a summary with 403 when the AI gate is off (R30.6, R30.7)', function () {
    $service = aiService(aiCapturingLlm('x'), aiDenyGate());
    $report = aiReportWithDataset([['total' => 1]]);

    expect(fn () => $service->summarize($report, aiTenantWideContext()))
        ->toThrow(FeatureDisabledException::class);
});

it('denies access to a report the principal cannot read (R32.4)', function () {
    $service = aiService(aiCapturingLlm('x'), aiAllowGate());

    // A buyer-scoped principal with no tenant-wide scope, asking about a report
    // created by someone else.
    $buyerContext = new ReportAccessContext(
        roles: [Roles::DEALER],
        canViewReports: true,
        buyerType: \App\Models\Dealer::class,
        buyerId: 7,
    );

    $report = aiReportWithDataset([['total' => 1]], createdBy: 999);

    expect(fn () => $service->summarize($report, $buyerContext))
        ->toThrow(AuthorizationException::class);
});
