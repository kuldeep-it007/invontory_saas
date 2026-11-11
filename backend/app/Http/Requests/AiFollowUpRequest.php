<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a follow-up question about a persisted AI report:
 * `POST /api/ai/reports/{aiReport}/followup` (R32.2).
 *
 * The body carries a single `question` — the free-text follow-up the AI layer
 * answers using ONLY the report's already-computed dataset. It is REQUIRED and
 * bounded so an empty or abusively large question is rejected at the edge; the
 * grounding, PII redaction, scoping, and audit all happen in
 * {@see \App\Services\Ai\AiReportService}.
 */
class AiFollowUpRequest extends FormRequest
{
    /**
     * The largest follow-up question we accept.
     */
    public const MAX_QUESTION_LENGTH = 2000;

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
            'question' => ['required', 'string', 'min:1', 'max:'.self::MAX_QUESTION_LENGTH],
        ];
    }

    /**
     * The trimmed follow-up question.
     */
    public function question(): string
    {
        return trim((string) $this->input('question'));
    }
}
