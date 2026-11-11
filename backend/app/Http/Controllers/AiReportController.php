<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AiFollowUpRequest;
use App\Http\Requests\AiReportRequest;
use App\Models\AiReport;
use App\Reports\ReportAccessContext;
use App\Services\Ai\AiReportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Natural-language AI reporting HTTP surface: `POST /api/ai/reports` (R31).
 *
 * Thin controller: it builds the caller's {@see ReportAccessContext} from the
 * authenticated principal and hands the validated prompt to the
 * {@see AiReportService}, which enforces the composite AI gate, maps the
 * natural-language request to a validated {@see \App\Reports\ReportQuerySpec},
 * and runs it through the SAME shared {@see \App\Services\ReportQueryService}
 * every standard report uses (R31.3). The {@see \App\Reports\ReportResult}
 * returns through the identical list envelope the standard report endpoint
 * uses, so the AI path flows through the standard render/export pipeline
 * (R31.6).
 *
 * The route is mounted behind the composite AI gate (`ai.enabled` middleware)
 * so a disabled master switch or tenant flag yields a 403 before this
 * controller runs (R30.6, R30.7); the service re-asserts the same gate as
 * defence in depth. No business logic lives here.
 */
class AiReportController extends Controller
{
    public function __construct(private readonly AiReportService $ai) {}

    /**
     * POST /api/ai/reports — generate a report from a natural-language request
     * (R31.1–R31.6).
     *
     * A prompt that cannot be mapped to an allow-listed spec, or a mapped spec
     * that fails allow-list validation, is a 422 (surfaced from the service's
     * InvalidArgumentException) and nothing is executed (R31.2, R31.5). A
     * principal not permitted to read the requested data is a 403 (R28.10). A
     * disabled AI gate is a 403 raised by the middleware/service (R30.6, R30.7).
     */
    public function store(AiReportRequest $request): JsonResponse
    {
        $user = $request->user();
        $context = ReportAccessContext::fromUser($user);

        try {
            $result = $this->ai->generate($request->prompt(), $context, $user);
        } catch (AuthorizationException $e) {
            abort(Response::HTTP_FORBIDDEN, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, $e->getMessage());
        }

        return response()->json($result->toArray());
    }

    /**
     * POST /api/ai/reports/{aiReport}/summary — summarise a persisted report's
     * dataset (R32.1).
     *
     * Takeaways/trends/outliers are computed SOLELY from the report's stored
     * dataset; only the minimum aggregated, PII-redacted data is sent to the
     * model (R32.3). A principal not permitted to read the report is a 403
     * (R32.4). Every request is audited (R32.5).
     */
    public function summary(AiReport $aiReport): JsonResponse
    {
        $user = request()->user();
        $context = ReportAccessContext::fromUser($user);

        try {
            $summary = $this->ai->summarize($aiReport, $context, $user);
        } catch (AuthorizationException $e) {
            abort(Response::HTTP_FORBIDDEN, $e->getMessage());
        }

        return response()->json([
            'data' => [
                'ai_report_id' => $aiReport->getKey(),
                'summary' => $summary,
            ],
        ]);
    }

    /**
     * POST /api/ai/reports/{aiReport}/followup — answer a follow-up question
     * about a persisted report using ONLY its dataset (R32.2).
     *
     * Identical guardrails to {@see summary()}: dataset-only, minimum
     * aggregated + PII-redacted data, tenant+role scoped, audited (R32.2–R32.5).
     */
    public function followup(AiFollowUpRequest $request, AiReport $aiReport): JsonResponse
    {
        $user = $request->user();
        $context = ReportAccessContext::fromUser($user);

        try {
            $answer = $this->ai->followUp($aiReport, $request->question(), $context, $user);
        } catch (AuthorizationException $e) {
            abort(Response::HTTP_FORBIDDEN, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, $e->getMessage());
        }

        return response()->json([
            'data' => [
                'ai_report_id' => $aiReport->getKey(),
                'answer' => $answer,
            ],
        ]);
    }
}
