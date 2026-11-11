<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a natural-language AI report request: `POST /api/ai/reports`
 * (R31.1).
 *
 * The body carries a single `prompt` — the free-text reporting request the AI
 * layer maps to a {@see \App\Reports\ReportQuerySpec}. It is REQUIRED and
 * bounded so an empty or abusively large prompt is rejected at the edge before
 * the (gated) AI layer is invoked. The actual mapping, allow-list validation,
 * and execution all happen in {@see \App\Services\Ai\AiReportService}; this
 * request only guarantees the controller receives a usable prompt string.
 */
class AiReportRequest extends FormRequest
{
    /**
     * The largest prompt we accept. Keeps the request bounded; the composite AI
     * gate (env master switch + tenant flag) still authorizes the action.
     */
    public const MAX_PROMPT_LENGTH = 2000;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'min:1', 'max:'.self::MAX_PROMPT_LENGTH],
        ];
    }

    /**
     * The trimmed natural-language prompt the AI layer should map to a report
     * query.
     */
    public function prompt(): string
    {
        return trim((string) $this->input('prompt'));
    }
}
