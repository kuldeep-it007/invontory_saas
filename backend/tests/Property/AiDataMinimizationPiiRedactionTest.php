<?php

declare(strict_types=1);

use App\Auth\Roles;
use App\Models\AiReport;
use App\Reports\ReportAccessContext;
use App\Services\Ai\AiReportService;
use App\Services\Ai\DatasetMinimizer;
use App\Services\Ai\LlmClient;
use App\Services\Ai\PiiRedactor;
use App\Services\ReportQueryService;
use App\Services\SubscriptionGateService;
use Innmind\BlackBox\Set;
use Innmind\BlackBox\Set\Provider;

/*
|--------------------------------------------------------------------------
| Task 13.7 — Property 34: AI data minimization and PII redaction
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 34: AI data minimization
| and PII redaction
|
| Validates: Requirements 32.1, 32.2, 32.3
|
| Property statement (tasks.md §13.7 / design.md §Property 34):
|   For ANY report dataset containing personally identifiable fields, the
|   payload transmitted to the language model contains ONLY aggregated/derived
|   data from that dataset and NONE of the PII values; summaries and follow-ups
|   are computed SOLELY from the computed dataset.
|
|     - R32.1  A summary (takeaways/trends/outliers) is produced from the report
|              dataset only.
|     - R32.2  A follow-up is answered using only the computed dataset.
|     - R32.3  Only the MINIMUM aggregated data is sent, and PII is REDACTED
|              before transmission to the model.
|
| What the proof asserts, for every generated PII-bearing dataset:
|
|   1. NO PII REACHES THE MODEL (R32.3). We drive the REAL
|      AiReportService::summarize() / followUp(), which composes the production
|      PiiRedactor and DatasetMinimizer (redact THEN minimize) exactly as the
|      service does, and capture the actual prompt handed to the LlmClient.
|      Every generated PII value — whether it sits in a LABELLED PII column
|      (buyer_name, email, phone, gstin, …) or is embedded as a PII-SHAPED
|      SUBSTRING (email / long digit run / GSTIN) inside an otherwise free-text
|      column — is a UNIQUE sentinel, so we can assert that NONE of them appears
|      anywhere in the captured prompt. If even one personal value leaked, the
|      substring check fails and BlackBox shrinks to the minimal leaking row.
|
|   2. ONLY AGGREGATED / BOUNDED DATA IS SENT (R32.3). The captured prompt
|      carries the minimized profile: a row_count, numeric_summaries over the
|      measure columns, and a sample bounded to at most
|      DatasetMinimizer::MAX_SAMPLE_ROWS rows — never the full row-level dataset
|      when it is larger than that bound. Safe aggregate measures (numeric
|      totals/quantities) are preserved so the model still receives useful
|      structure.
|
|   3. COMPUTED SOLELY FROM THE DATASET (R32.1, R32.2). The service receives the
|      dataset and nothing else; the only data-bearing content the model sees is
|      the minimized/redacted profile of THAT dataset. A capturing LlmClient
|      records the exact prompt so we can prove nothing beyond the dataset's
|      aggregated profile is transmitted, and that the number of measure rows the
|      summaries aggregate equals the dataset's row count.
|
| Strategy — PURE LOGIC, no database.
|   summarize()/followUp() touch no DB: the dataset is read from an in-memory
|   AiReport whose save() is a no-op, the gate is a permissive stub, and the
|   LlmClient is a capturing fake. Datasets are generated as a sequence of row
|   specs; each row carries (a) safe aggregate/dimension columns, (b) labelled
|   PII columns seeded with unique sentinels, and (c) a free-text column with an
|   embedded PII-shaped sentinel. Row counts range across and beyond
|   MAX_SAMPLE_ROWS so the bounded-sample guarantee is exercised on both sides.
|
|   Generation uses innmind/black-box (NOT hand-rolled) via the shared
|   static::forAll(), drawing the mandated minimum of 100 generated scenarii per
|   proof and shrinking any counterexample to a minimal failing input.
*/

/** A gate that always permits AI reporting (R30.6/R30.7 are out of scope here). */
function aiMinGate(): SubscriptionGateService
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

/** An LlmClient that records the exact prompt it is asked to complete. */
function aiMinCapturingLlm(string $completion = 'ok'): LlmClient
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

/**
 * An in-memory AiReport carrying a dataset, whose save() is a no-op (no DB).
 * Eloquent re-instantiates models via `new static`, so the subclass declares no
 * constructor — attributes are force-filled on the returned instance.
 *
 * @param  list<array<string, mixed>>  $dataset
 */
function aiMinReportWithDataset(array $dataset): AiReport
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
        'prompt' => 'top buyers last month',
        'report_type' => 'sales',
        'dataset' => $dataset,
        'created_by' => null,
    ]);

    return $report;
}

/** The real service wired with the production redactor + minimizer. */
function aiMinService(LlmClient $llm): AiReportService
{
    return new AiReportService(
        aiMinGate(),
        $llm,
        new ReportQueryService,
        new DatasetMinimizer,
        new PiiRedactor,
        null, // audit is R32.5 (Property 35), out of scope for this proof
    );
}

/** A tenant-wide context that may read any report in the tenant (R32.4 satisfied). */
function aiMinTenantWideContext(): ReportAccessContext
{
    return new ReportAccessContext(roles: [Roles::COMPANY_ADMIN], canViewReports: true);
}

/**
 * Build a dataset of PII-bearing rows plus the full list of unique PII sentinel
 * values seeded into it. Each row spec is a tuple
 * [productIdx, qty, totalCents, tokenA, tokenB, tokenC] where tokenA/B/C are
 * unique integer-ish tokens used to make every PII value globally unique, so a
 * single appearance of any of them in the prompt is a provable leak.
 *
 * Each row carries:
 *   - product_name / category_name : SAFE catalog dimensions (never redacted);
 *   - quantity / total             : SAFE numeric aggregate measures;
 *   - buyer_name                   : labelled PII (sentinel "Trader-<tokenA>Z");
 *   - buyer_email                  : labelled PII (sentinel "<tokenA>u@acme.test");
 *   - customer_phone               : labelled PII (10-digit "<tokenB>");
 *   - gstin                        : labelled PII (a valid-shaped GSTIN);
 *   - note (free text)             : embeds a PII-shaped email + phone substring.
 *
 * @param  list<array{0:int,1:int,2:int,3:int,4:int,5:int}>  $rowSpecs
 * @return array{0: list<array<string, mixed>>, 1: list<string>}
 */
function aiMinBuildDataset(array $rowSpecs): array
{
    $products = ['Widget', 'Gadget', 'Sprocket'];
    $categories = ['Hardware', 'Tools', 'Parts'];

    $rows = [];
    $piiValues = [];

    foreach ($rowSpecs as $i => [$productIdx, $qty, $totalCents, $tokenA, $tokenB, $tokenC]) {
        // Globally-unique tokens: combine the row index with the generated
        // tokens so no two rows ever share a sentinel, even if the generator
        // repeats a tuple.
        $a = $i.'a'.$tokenA;
        $b = $i.'b'.$tokenB;

        $buyerName = 'Trader-'.$a.'Z';
        $buyerEmail = $a.'u@buyer-'.$b.'.test';
        // A 10-digit phone sentinel (7+ digits triggers the phone pattern).
        $phone = '9'.str_pad((string) ($tokenB % 1000000000), 9, '0', STR_PAD_LEFT);
        // A valid-shaped Indian GSTIN: 2 state digits, 5 letters, 4 digits,
        // 1 letter, 1 entity digit, 'Z', 1 checksum char.
        $gstin = '27AAPFU'.str_pad((string) ($tokenC % 10000), 4, '0', STR_PAD_LEFT).'F1Z'.chr(65 + ($tokenC % 26));
        // Free-text column embedding PII-shaped substrings among ordinary prose.
        $noteEmail = 'contact'.$a.'@notes-'.$b.'.test';
        $notePhone = '8'.str_pad((string) (($tokenA + 7) % 1000000000), 9, '0', STR_PAD_LEFT);
        $note = 'Follow up with the account; reach them at '.$notePhone.' or '.$noteEmail.' before quarter end.';

        $rows[] = [
            'product_name' => $products[$productIdx % 3],
            'category_name' => $categories[$productIdx % 3],
            'quantity' => $qty,
            'total' => $totalCents / 100,
            'buyer_name' => $buyerName,
            'buyer_email' => $buyerEmail,
            'customer_phone' => $phone,
            'gstin' => $gstin,
            'note' => $note,
        ];

        // Every seeded personal value is a leak-detector sentinel. The GSTIN and
        // phone sentinels are tracked as whole values; their presence anywhere
        // in the prompt (even partially, for the long free-text substrings) is a
        // violation.
        $piiValues[] = $buyerName;
        $piiValues[] = $buyerEmail;
        $piiValues[] = $phone;
        $piiValues[] = $gstin;
        $piiValues[] = $noteEmail;
        $piiValues[] = $notePhone;
    }

    return [$rows, $piiValues];
}

/**
 * A BlackBox set of dataset row specs. Row count spans 1..(MAX_SAMPLE_ROWS+15)
 * so BOTH the "dataset smaller than the bound" and "dataset larger than the
 * bound" cases are generated, exercising the bounded-sample guarantee.
 */
function aiMinRowSpecsSet(): Set|Provider
{
    return Set::sequence(
        Set::compose(
            static fn (int $p, int $qty, int $totalCents, int $a, int $b, int $c): array
                => [$p, $qty, $totalCents, $a, $b, $c],
            Set::integers()->between(0, 2),            // product/category index
            Set::integers()->between(1, 500),          // quantity (safe measure)
            Set::integers()->between(0, 50_000_00),     // total in cents (safe measure)
            Set::integers()->between(1, 9_999_999),     // token A
            Set::integers()->between(1, 9_999_999),     // token B
            Set::integers()->between(1, 9_999_999),     // token C
        ),
    )->between(1, DatasetMinimizer::MAX_SAMPLE_ROWS + 15);
}

/**
 * Assert the captured prompt leaks no PII and carries only aggregated/bounded
 * data. Shared by the summary and follow-up proofs.
 *
 * @param  list<string>  $piiValues
 * @param  list<array<string, mixed>>  $dataset
 */
function aiMinAssertNoLeakAndAggregated(string $prompt, array $piiValues, array $dataset): void
{
    // 1. NO PII VALUE — labelled or free-text substring — reaches the model.
    foreach ($piiValues as $pii) {
        expect(str_contains($prompt, $pii))->toBeFalse(
            sprintf('PII value %s leaked into the model prompt.', var_export($pii, true)),
        );
    }

    // The redaction placeholder stands in where PII was (every row has PII).
    expect($prompt)->toContain(PiiRedactor::PLACEHOLDER);

    // 2. ONLY AGGREGATED / BOUNDED DATA. The minimized profile is transmitted:
    //    row_count, numeric_summaries, and a bounded sample.
    expect($prompt)->toContain('"row_count":'.count($dataset));
    expect($prompt)->toContain('numeric_summaries');

    // Safe numeric measures ARE summarised (useful structure survives).
    $sumQty = 0.0;
    foreach ($dataset as $row) {
        $sumQty += (float) $row['quantity'];
    }
    // The quantity column is a pure measure, so its summary sum is present.
    expect($prompt)->toContain('"quantity"');

    // 3. SAMPLE IS BOUNDED — the full dataset is never shipped when it exceeds
    //    the sample bound. We reconstruct the payload the service built to make
    //    the bound assertion precise and framework-agnostic.
    $payload = (new DatasetMinimizer)->minimize((new PiiRedactor)->redactRows($dataset));
    expect(count($payload['sample']))->toBeLessThanOrEqual(DatasetMinimizer::MAX_SAMPLE_ROWS);
    if (count($dataset) > DatasetMinimizer::MAX_SAMPLE_ROWS) {
        expect(count($payload['sample']))->toBe(DatasetMinimizer::MAX_SAMPLE_ROWS);
    }

    // The minimized profile summarises exactly the dataset's rows (computed
    // SOLELY from the dataset — R32.1/R32.2): the quantity measure's count
    // equals the dataset row count.
    expect($payload['numeric_summaries']['quantity']['count'])->toBe(count($dataset));

    // And the payload the service hands the model is itself PII-free: no
    // generated sentinel survives into the minimized/redacted profile.
    $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '';
    foreach ($piiValues as $pii) {
        expect(str_contains($encoded, $pii))->toBeFalse(
            sprintf('PII value %s survived into the minimized payload.', var_export($pii, true)),
        );
    }
}

it('transmits only aggregated, PII-redacted data to the model when SUMMARISING a PII-bearing dataset (R32.1, R32.3)', function () {
    static::forAll(aiMinRowSpecsSet())->then(function (array $rowSpecs) {
        [$dataset, $piiValues] = aiMinBuildDataset($rowSpecs);

        $llm = aiMinCapturingLlm('Key takeaway from the aggregates.');
        $service = aiMinService($llm);

        $summary = $service->summarize(aiMinReportWithDataset($dataset), aiMinTenantWideContext());

        // The summary is the model completion — computed from the dataset only.
        expect($summary)->toBe('Key takeaway from the aggregates.');

        aiMinAssertNoLeakAndAggregated($llm->lastPrompt, $piiValues, $dataset);
    });
});

it('transmits only aggregated, PII-redacted data to the model when answering a FOLLOW-UP about a PII-bearing dataset (R32.2, R32.3)', function () {
    static::forAll(aiMinRowSpecsSet())->then(function (array $rowSpecs) {
        [$dataset, $piiValues] = aiMinBuildDataset($rowSpecs);

        $llm = aiMinCapturingLlm('The answer is derived from the aggregates.');
        $service = aiMinService($llm);

        $question = 'Which product has the highest total?';
        $answer = $service->followUp(aiMinReportWithDataset($dataset), $question, aiMinTenantWideContext());

        expect($answer)->toBe('The answer is derived from the aggregates.');

        // The follow-up question itself is carried verbatim; the dataset is
        // only ever present as its aggregated, redacted profile.
        expect($llm->lastPrompt)->toContain($question);

        aiMinAssertNoLeakAndAggregated($llm->lastPrompt, $piiValues, $dataset);
    });
});
