<?php

declare(strict_types=1);

namespace App\Services\Ai;

/**
 * The configured LLM provider (R30.2, R30.4).
 *
 * `LLM_PROVIDER` carries one of these allowed values; the
 * {@see LlmClientFactory} maps each to a concrete {@see LlmClient}
 * implementation:
 *
 *   - {@see LlmProvider::Ollama}           -> {@see OllamaClient}
 *   - {@see LlmProvider::OpenAiCompatible} -> {@see HttpLlmClient}
 *   - {@see LlmProvider::None}             -> {@see NullLlmClient} (graceful no-op, R30.5)
 *
 * Modelled as a backed enum so the allow-listed set of values is explicit and
 * an unrecognised configuration value is normalised to {@see LlmProvider::None}
 * rather than failing to boot (R30.3).
 */
enum LlmProvider: string
{
    case Ollama = 'ollama';
    case OpenAiCompatible = 'openai_compatible';
    case None = 'none';

    /**
     * Resolve a raw configuration value to a provider, defaulting to
     * {@see LlmProvider::None} for anything null, blank, or unrecognised so the
     * AI layer always resolves to a safe no-op when misconfigured (R30.3,
     * R30.5).
     */
    public static function fromConfig(?string $value): self
    {
        if ($value === null) {
            return self::None;
        }

        return self::tryFrom(trim(strtolower($value))) ?? self::None;
    }
}
