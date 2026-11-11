<?php

declare(strict_types=1);

namespace App\Services\Ai;

/**
 * The single interface the AI reporting layer talks to (R30.4).
 *
 * Per the design ("AI Reporting Layer"), the AI layer exposes exactly one
 * abstraction — `complete(prompt): string` — resolved from configuration to a
 * concrete provider implementation ({@see OllamaClient}, {@see HttpLlmClient},
 * or {@see NullLlmClient}). Callers (e.g. the forthcoming `AiReportService`)
 * depend only on this interface, so the configured provider is swapped purely
 * through configuration and the rest of the AI layer never changes.
 *
 * Implementations MUST degrade gracefully rather than throw when a backend is
 * unconfigured or unreachable: a request must never fail merely because the AI
 * layer is off. The {@see NullLlmClient} is the canonical graceful no-op
 * (R30.5), and the HTTP-backed clients return a safe empty completion when the
 * provider is unreachable so standard (non-AI) reporting remains fully
 * functional (R30.3, R30.5, design "Degradation").
 */
interface LlmClient
{
    /**
     * Produce a text completion for the given prompt.
     *
     * Returns the model's completion as a string. Implementations return an
     * empty string (never throw) when the provider is disabled, unconfigured,
     * or unreachable, so the caller can treat an empty result as "no AI output
     * available" and fall back to standard reporting (R30.5).
     */
    public function complete(string $prompt): string;
}
