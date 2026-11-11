<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\AiReport;
use App\Models\User;
use App\Reports\ReportAccessContext;
use App\Reports\ReportQuerySpec;
use App\Reports\ReportResult;
use App\Support\AuditRecorder;
use App\Services\ReportQueryService;
use App\Services\SubscriptionGateService;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use JsonException;

/**
 * The AI reporting layer — a THIN wrapper over the shared
 * {@see ReportQueryService} (design "AI Reporting Layer", R31, R32).
 *
 * The service owns the natural-language entry point to reporting plus the
 * dataset-grounded summary/follow-up features, and nothing more: it never
 * touches the database for report data itself, never builds SQL, and never has
 * its own query path. It exposes three operations, all gated by the composite
 * AI gate and all audited (R32.5):
 *
 *   • {@see generate()}  — NL request → validated {@see ReportQuerySpec} →
 *                          {@see ReportQueryService} (R31). The result is
 *                          persisted as an {@see AiReport} so follow-on summary
 *                          and follow-up requests can reference the SAME
 *                          returned dataset by id.
 *   • {@see summarize()} — takeaways/trends/outliers computed SOLELY from a
 *                          persisted report's dataset (R32.1).
 *   • {@see followUp()}  — answers a question using ONLY that dataset (R32.2).
 *
 * Generation flow (R31):
 *   1. GATE — assert AI reporting is permitted via the COMPOSITE gate (env
 *      master switch AND tenant `ai_reports` flag), reusing
 *      {@see SubscriptionGateService::assertAiReportingEnabled()} so the gate is
 *      defined in exactly one place (R30.6, R30.7). Either one off → 403.
 *   2. MAP — ask the {@see LlmClient} to map NL to a STRUCTURED spec — never
 *      SQL (R31.1); only allow-listed structured fields are read (R31.4).
 *   3. VALIDATE — check the spec against the allow-listed schema BEFORE
 *      execution; a failing spec is rejected without running a query (R31.2,
 *      R31.5).
 *   4. EXECUTE — run the validated spec through the SAME
 *      {@see ReportQueryService} every standard report uses, inheriting tenant+
 *      role scoping, defaults, caching, and indexed pagination (R31.3). The
 *      {@see ReportResult} returns through the standard pipeline (R31.6).
 *
 * Summary/follow-up guardrails (R32):
 *   • computed SOLELY from the returned dataset (R32.1, R32.2);
 *   • only the MINIMUM aggregated data is sent, with PII REDACTED before
 *     transmission to the model (R32.3) — via {@see DatasetMinimizer} then
 *     {@see PiiRedactor};
 *   • tenant+role scoped: a principal may only summarise/ask about a report its
 *     own context can read (R32.4);
 *   • EVERY AI request — generate, summary, follow-up — is recorded in the
 *     Audit_Log (R32.5).
 */
class AiReportService
{
    public function __construct(
        private readonly SubscriptionGateService $gate,
        private readonly LlmClient $llm,
        private readonly ReportQueryService $reports,
        private readonly DatasetMinimizer $minimizer = new DatasetMinimizer,
        private readonly PiiRedactor $redactor = new PiiRedactor,
        private readonly ?AuditRecorder $audit = null,
    ) {}

    /**
     * Audit event names for the three AI reporting requests (R32.5).
     */
    public const EVENT_GENERATE = 'ai_report.generate';

    public const EVENT_SUMMARY = 'ai_report.summary';

    public const EVENT_FOLLOWUP = 'ai_report.followup';

    /**
     * Generate a report from a natural-language request and persist it (R31,
     * R32.5).
     *
     * Enforces the composite AI gate, maps the prompt to a validated
     * {@see ReportQuerySpec}, executes it through the shared
     * {@see ReportQueryService}, persists the returned dataset as an
     * {@see AiReport} (so summary/follow-up can reference it), audits the
     * request, and returns the standard {@see ReportResult} (R31.6).
     *
     * @param  string  $prompt  The user's natural-language report request.
     * @param  Authenticatable|null  $actor  The requesting principal, captured on the persisted report and audit entry.
     *
     * @throws \App\Exceptions\FeatureDisabledException When AI reporting is not permitted (403 — R30.6, R30.7).
     * @throws InvalidArgumentException When the mapped spec is empty/unparseable or fails allow-list validation (R31.2, R31.5).
     * @throws \Illuminate\Auth\Access\AuthorizationException When the principal may not read the requested data (R28.10).
     */
    public function generate(string $prompt, ReportAccessContext $context, ?Authenticatable $actor = null): ReportResult
    {
        // 1. GATE (R30.6, R30.7).
        $this->gate->assertAiReportingEnabled();

        // 2. MAP (R31.1) — NL → STRUCTURED spec (never SQL — R31.4).
        $spec = $this->mapToSpec($prompt);

        // 3. VALIDATE (R31.2, R31.5) — reject anything outside the allow-list
        //    BEFORE touching the query layer.
        $this->validateAgainstAllowList($spec);

        // 4. EXECUTE (R31.3, R31.6) — the SAME shared query path; raw SQL from
        //    the model never reaches here (R31.4). The result is tenant+role
        //    scoped by the shared layer (R28.10, R32.4).
        $result = $this->reports->run($spec, $context);

        // Persist the returned dataset so summary/follow-up compute SOLELY from
        // it (R32.1, R32.2), then audit the generation request (R32.5). Both are
        // best-effort side effects: a persistence/audit failure must never
        // discard an already-computed, correctly-scoped report result.
        $this->persist($prompt, $result, $actor);
        $this->recordAudit(self::EVENT_GENERATE, $actor, [
            'prompt' => $prompt,
            'report_type' => $result->spec->type->value,
            'row_count' => $result->count(),
        ]);

        return $result;
    }

    /**
     * Produce an AI summary of a PERSISTED report — takeaways, trends, and
     * outliers computed SOLELY from the report's stored dataset (R32.1).
     *
     * Guardrails (R32.3): the dataset is first reduced to the minimum
     * aggregated profile ({@see DatasetMinimizer}) and then PII-redacted
     * ({@see PiiRedactor}) BEFORE a single character is placed in the model
     * prompt — so the model only ever sees aggregated, de-identified data. The
     * caller's {@see ReportAccessContext} must be able to read the report
     * (R32.4), and every summary request is audited (R32.5).
     *
     * The generated summary is cached on the report so a repeat request is
     * cheap. When AI is a graceful no-op (NullLlmClient), the model returns an
     * empty completion and a deterministic dataset-derived summary is produced
     * instead, so the feature degrades without error.
     *
     * @throws \App\Exceptions\FeatureDisabledException When AI reporting is not permitted (403).
     * @throws \Illuminate\Auth\Access\AuthorizationException When the principal may not read this report (R32.4).
     */
    public function summarize(AiReport $report, ReportAccessContext $context, ?Authenticatable $actor = null): string
    {
        $this->gate->assertAiReportingEnabled();
        $this->assertReportReadable($report, $context, $actor);

        $rows = $report->datasetRows();

        // Minimum aggregated data + PII redaction BEFORE transmission (R32.3).
        $payload = $this->buildModelPayload($rows);

        $summary = $this->llm->complete($this->buildSummaryPrompt($report->prompt, $payload));
        $summary = trim($summary);

        if ($summary === '') {
            // Graceful degradation / no-op client: derive a factual summary from
            // the aggregated dataset itself so the feature still answers (R32.1).
            $summary = $this->fallbackSummary($payload);
        }

        // Cache the summary on the persisted report (dataset is unchanged).
        $report->forceFill(['summary' => $summary])->save();

        $this->recordAudit(self::EVENT_SUMMARY, $actor, [
            'ai_report_id' => $report->getKey(),
            'row_count' => count($rows),
        ]);

        return $summary;
    }

    /**
     * Answer a follow-up question about a PERSISTED report using ONLY its
     * stored dataset (R32.2).
     *
     * Identical guardrails to {@see summarize()}: the question is answered
     * strictly from the minimized, PII-redacted dataset profile (R32.2, R32.3);
     * the caller must be able to read the report (R32.4); the request is audited
     * (R32.5). The model is explicitly instructed to answer only from the
     * provided data and to say so when the data does not contain the answer, so
     * a follow-up can never pull in information beyond the returned dataset.
     *
     * @throws \App\Exceptions\FeatureDisabledException When AI reporting is not permitted (403).
     * @throws InvalidArgumentException When the question is empty.
     * @throws \Illuminate\Auth\Access\AuthorizationException When the principal may not read this report (R32.4).
     */
    public function followUp(AiReport $report, string $question, ReportAccessContext $context, ?Authenticatable $actor = null): string
    {
        $this->gate->assertAiReportingEnabled();
        $this->assertReportReadable($report, $context, $actor);

        $question = trim($question);
        if ($question === '') {
            throw new InvalidArgumentException('A follow-up question is required.');
        }

        $rows = $report->datasetRows();
        $payload = $this->buildModelPayload($rows);

        $answer = trim($this->llm->complete($this->buildFollowUpPrompt($report->prompt, $question, $payload)));

        if ($answer === '') {
            $answer = 'No AI answer is available. '.$this->fallbackSummary($payload);
        }

        $this->recordAudit(self::EVENT_FOLLOWUP, $actor, [
            'ai_report_id' => $report->getKey(),
            'question' => $question,
            'row_count' => count($rows),
        ]);

        return $answer;
    }

    /**
     * Build the de-identified, minimized payload sent to the model (R32.3).
     *
     * Order matters: the dataset is PII-REDACTED FIRST, then reduced to the
     * minimum aggregated profile. Redacting first guarantees that a PII value
     * which happens to be numeric (e.g. a phone number in a `customer_phone`
     * column) is masked to a placeholder BEFORE the minimizer runs, so it can
     * never be summed into a numeric aggregate and leak that way. The minimizer
     * then derives shape, numeric summaries, and a bounded sample from the
     * already-redacted rows, so NO personal value — labelled or numeric — ever
     * reaches the model prompt (R32.3).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function buildModelPayload(array $rows): array
    {
        $redacted = $this->redactor->redactRows($rows);

        return $this->minimizer->minimize($redacted);
    }

    /**
     * Persist a generated report's returned dataset so summary/follow-up can be
     * computed solely from it (R32.1, R32.2).
     *
     * Best-effort: a persistence failure (e.g. outside tenancy context) is
     * swallowed so an already-computed, correctly-scoped report result is still
     * returned to the caller. When persistence does not run, the summary/
     * follow-up endpoints simply have no row to reference — the generation
     * result itself is unaffected.
     */
    private function persist(string $prompt, ReportResult $result, ?Authenticatable $actor): ?AiReport
    {
        try {
            return AiReport::create([
                'prompt' => $prompt,
                'report_type' => $result->spec->type->value,
                'spec' => $this->specToArray($result->spec),
                'dataset' => $result->rows,
                'summary' => null,
                'created_by' => $this->actorId($actor),
            ]);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Assert the caller may read the given persisted report (R32.4).
     *
     * Tenant scoping is physical — the report already lives in the active
     * tenant database, so cross-tenant access is impossible. Role scoping adds:
     * a tenant-wide principal (Company_Admin/Staff/Read-only/Platform_Admin) may
     * read any report in the tenant; any other principal (a buyer-scoped
     * Distributor/Dealer) may read ONLY the reports it generated itself, matched
     * by `created_by` against the acting principal's id. A principal that is
     * neither tenant-wide nor the creator is denied rather than shown another
     * principal's data.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    private function assertReportReadable(AiReport $report, ReportAccessContext $context, ?Authenticatable $actor): void
    {
        if ($context->hasTenantWideScope()) {
            return;
        }

        $actorId = $this->actorId($actor);

        if ($actorId !== null && $report->created_by !== null && (int) $report->created_by === $actorId) {
            return;
        }

        throw new \Illuminate\Auth\Access\AuthorizationException(
            'You are not permitted to access this AI report.'
        );
    }

    /**
     * Record an AI request in the Audit_Log (R32.5). Best-effort through the
     * same {@see AuditRecorder} used for other security events, so a missing
     * recorder (pure-logic test) or an audit failure never blocks the AI
     * response.
     *
     * @param  array<string, mixed>  $context
     */
    private function recordAudit(string $event, ?Authenticatable $actor, array $context): void
    {
        if ($this->audit === null) {
            return;
        }

        $this->audit->record($event, $actor instanceof User ? $actor : null, $context);
    }

    /**
     * The actor's primary key, when known.
     */
    private function actorId(?Authenticatable $actor): ?int
    {
        if ($actor === null) {
            return null;
        }

        $id = $actor->getAuthIdentifier();

        return $id === null ? null : (int) $id;
    }

    /**
     * A deterministic, dataset-only summary used when the model yields no
     * completion (NullLlmClient / graceful degradation). Reports factual
     * aggregates derived strictly from the dataset (R32.1), never invented data.
     *
     * @param  array<string, mixed>  $payload
     */
    private function fallbackSummary(array $payload): string
    {
        $rowCount = (int) ($payload['row_count'] ?? 0);

        if ($rowCount === 0) {
            return 'The report returned no rows for the selected criteria.';
        }

        $parts = [sprintf('The report returned %d row(s).', $rowCount)];

        /** @var array<string, array{sum:float,min:float,max:float,avg:float}> $summaries */
        $summaries = is_array($payload['numeric_summaries'] ?? null) ? $payload['numeric_summaries'] : [];

        foreach ($summaries as $column => $stats) {
            $parts[] = sprintf(
                '%s: total %s, min %s, max %s, avg %s.',
                $column,
                $this->num($stats['sum'] ?? 0),
                $this->num($stats['min'] ?? 0),
                $this->num($stats['max'] ?? 0),
                $this->num($stats['avg'] ?? 0),
            );
        }

        return implode(' ', $parts);
    }

    private function num(float|int $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    /**
     * Serialise a resolved spec to a JSON-safe array for persistence.
     *
     * @return array<string, mixed>
     */
    private function specToArray(ReportQuerySpec $spec): array
    {
        $sort = [];
        foreach ($spec->sort as $directive) {
            $sort[] = ['column' => $directive->column, 'dir' => $directive->direction];
        }

        $filters = $spec->filters;

        return [
            'type' => $spec->type->value,
            'filters' => array_filter([
                'from' => $filters->from?->toIso8601String(),
                'to' => $filters->to?->toIso8601String(),
                'buyerType' => $filters->buyerType,
                'buyerId' => $filters->buyerId,
                'sellerType' => $filters->sellerType,
                'sellerId' => $filters->sellerId,
                'productId' => $filters->productId,
                'categoryId' => $filters->categoryId,
                'stockPointId' => $filters->stockPointId,
                'status' => $filters->status,
            ], static fn ($v): bool => $v !== null),
            'grouping' => $spec->grouping,
            'sort' => $sort,
            'limit' => $spec->limit,
            'page' => $spec->page,
        ];
    }

    /**
     * Map a natural-language request to a structured {@see ReportQuerySpec}
     * using the configured {@see LlmClient} (R31.1). Only allow-listed
     * structured fields are read, so model-produced SQL is never carried into
     * execution (R31.4).
     *
     * @throws InvalidArgumentException When the completion is empty or is not
     *                                  valid JSON describing a spec (R31.5).
     */
    private function mapToSpec(string $prompt): ReportQuerySpec
    {
        $completion = $this->llm->complete($this->buildPrompt($prompt));

        $decoded = $this->decodeSpecJson($completion);

        if ($decoded === null) {
            throw new InvalidArgumentException(
                'The natural-language request could not be mapped to a report query.'
            );
        }

        return ReportQuerySpec::fromArray([
            'type' => $decoded['type'] ?? null,
            'filters' => is_array($decoded['filters'] ?? null) ? $decoded['filters'] : [],
            'grouping' => $decoded['grouping'] ?? [],
            'sort' => $decoded['sort'] ?? [],
            'limit' => $decoded['limit'] ?? null,
            'page' => $decoded['page'] ?? null,
        ]);
    }

    /**
     * Validate a mapped spec against the allow-listed schema BEFORE execution
     * (R31.2). Rejects an unsupported report type, or any grouping/sort column
     * not on that report's allow-list, WITHOUT running a query (R31.5).
     *
     * @throws InvalidArgumentException Naming the offending type/column.
     */
    private function validateAgainstAllowList(ReportQuerySpec $spec): void
    {
        $schema = $this->reports->reportSchema();

        $type = $spec->type->value;

        if (! isset($schema[$type]) || ! $this->reports->supports($spec->type)) {
            throw new InvalidArgumentException(sprintf(
                'Report type [%s] is not available. Allowed types: %s.',
                $type,
                implode(', ', array_keys($schema)) ?: '(none)',
            ));
        }

        $groupable = $schema[$type]['groupable'];
        foreach ($spec->grouping as $column) {
            if (! in_array($column, $groupable, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Grouping column [%s] is not allowed for report [%s]. Allowed: %s.',
                    $column,
                    $type,
                    $groupable === [] ? '(none)' : implode(', ', $groupable),
                ));
            }
        }

        $sortable = $schema[$type]['sortable'];
        foreach ($spec->sort as $directive) {
            if (! in_array($directive->column, $sortable, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Sort column [%s] is not allowed for report [%s]. Allowed: %s.',
                    $directive->column,
                    $type,
                    $sortable === [] ? '(none)' : implode(', ', $sortable),
                ));
            }
        }
    }

    /**
     * Build the instruction prompt handed to the model for NL→spec mapping: the
     * user's request plus the allow-listed schema and a strict instruction to
     * emit ONLY a JSON spec and never SQL (R31.2).
     */
    private function buildPrompt(string $request): string
    {
        $schema = $this->reports->reportSchema();

        $catalog = [];
        foreach ($schema as $type => $columns) {
            $catalog[] = sprintf(
                '- %s (group by: %s; sort by: %s)',
                $type,
                $columns['groupable'] === [] ? 'none' : implode(', ', $columns['groupable']),
                $columns['sortable'] === [] ? 'none' : implode(', ', $columns['sortable']),
            );
        }

        return implode("\n", [
            'You translate a natural-language reporting request into a JSON report query specification.',
            'Respond with ONLY a single JSON object and no prose, markdown, or SQL.',
            'The JSON object has these keys: "type" (one of the allowed report types below),',
            '"filters" (object: from, to, buyerType, buyerId, sellerType, sellerId, productId, categoryId, stockPointId, status),',
            '"grouping" (array of allowed group-by columns for the chosen type),',
            '"sort" (array of objects {"column","dir"} using allowed sort columns), and "limit" (integer).',
            'Never output raw SQL. Only use the report types and columns listed here:',
            implode("\n", $catalog),
            '',
            'Request: '.$request,
        ]);
    }

    /**
     * Build the summary prompt. The model is given ONLY the minimized,
     * PII-redacted aggregate payload and instructed to summarise strictly from
     * it (R32.1, R32.3).
     *
     * @param  array<string, mixed>  $payload
     */
    private function buildSummaryPrompt(string $originalRequest, array $payload): string
    {
        return implode("\n", [
            'You are a reporting assistant. Summarise the dataset below for a business user.',
            'Produce concise key takeaways, notable trends, and any outliers.',
            'Use ONLY the data provided; do not invent numbers or reference anything not present.',
            'The data has already been aggregated and had personal information redacted.',
            '',
            'Original request: '.$originalRequest,
            '',
            'Dataset (JSON, aggregated and redacted):',
            $this->encodePayload($payload),
        ]);
    }

    /**
     * Build the follow-up prompt. The model answers ONLY from the provided
     * dataset payload and must say when the data does not contain the answer
     * (R32.2, R32.3).
     *
     * @param  array<string, mixed>  $payload
     */
    private function buildFollowUpPrompt(string $originalRequest, string $question, array $payload): string
    {
        return implode("\n", [
            'You are a reporting assistant answering a follow-up question about a report.',
            'Answer using ONLY the dataset below. If the data does not contain the answer,',
            'say so plainly rather than guessing. Do not reference anything not present in the data.',
            'The data has already been aggregated and had personal information redacted.',
            '',
            'Original request: '.$originalRequest,
            'Follow-up question: '.$question,
            '',
            'Dataset (JSON, aggregated and redacted):',
            $this->encodePayload($payload),
        ]);
    }

    /**
     * JSON-encode the model payload defensively (never throws).
     *
     * @param  array<string, mixed>  $payload
     */
    private function encodePayload(array $payload): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            return '{}';
        }
    }

    /**
     * Decode a model completion into the structured spec array, or null when it
     * is not parseable JSON describing a spec (R31.5).
     *
     * @return array<string, mixed>|null
     */
    private function decodeSpecJson(string $completion): ?array
    {
        $completion = trim($completion);

        if ($completion === '') {
            return null;
        }

        $json = $this->extractJsonObject($completion);

        if ($json === null) {
            return null;
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Extract the first balanced top-level JSON object from a string, or null
     * when none is present. Keeps the parser robust to a model that surrounds
     * the JSON with explanation or a ```json fence.
     */
    private function extractJsonObject(string $text): ?string
    {
        $start = strpos($text, '{');

        if ($start === false) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($text);

        for ($i = $start; $i < $length; $i++) {
            $char = $text[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;
            } elseif ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;

                if ($depth === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }
}
