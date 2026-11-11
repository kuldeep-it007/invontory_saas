<?php

declare(strict_types=1);

namespace App\Services\Ai;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * Resolves the configured {@see LlmClient} implementation from the AI config
 * (R30.4) — the one place the `config/ai.php` keys are mapped to a concrete
 * provider.
 *
 * Resolution rules (design "AI Reporting Layer"):
 *
 *   - AI disabled (`ai.enabled` false — the default, R30.1)  -> {@see NullLlmClient}
 *   - `LLM_PROVIDER=ollama`                                  -> {@see OllamaClient}
 *   - `LLM_PROVIDER=openai_compatible`                       -> {@see HttpLlmClient}
 *   - `LLM_PROVIDER=none` or any unrecognised value          -> {@see NullLlmClient} (R30.5)
 *
 * A provider whose required connection detail is missing (no `base_url`) also
 * degrades to the {@see NullLlmClient} rather than constructing a client that
 * can only ever fail — so the AI layer is always safe to resolve, even with
 * partial configuration, and the app boots with zero AI config (R30.3, R30.5).
 */
final class LlmClientFactory
{
    public function __construct(private readonly Config $config) {}

    /**
     * Build the {@see LlmClient} the current configuration maps to.
     */
    public function make(): LlmClient
    {
        // When AI reporting is switched off entirely, resolve the no-op client
        // regardless of any provider value (R30.5). This mirrors the design's
        // "LLM_PROVIDER=none | AI disabled -> NullLlmClient" branch.
        if (! (bool) $this->config->get('ai.enabled', false)) {
            return new NullLlmClient;
        }

        $provider = LlmProvider::fromConfig(
            $this->stringOrNull($this->config->get('ai.provider')),
        );

        return match ($provider) {
            LlmProvider::Ollama => $this->makeOllamaClient(),
            LlmProvider::OpenAiCompatible => $this->makeHttpClient(),
            LlmProvider::None => new NullLlmClient,
        };
    }

    /**
     * Build the Ollama client, degrading to the no-op client when no base URL
     * is configured (R30.5).
     */
    private function makeOllamaClient(): LlmClient
    {
        $baseUrl = $this->stringOrNull($this->config->get('ai.base_url'));

        if ($baseUrl === null) {
            return new NullLlmClient;
        }

        return new OllamaClient(
            baseUrl: $baseUrl,
            model: $this->stringOrNull($this->config->get('ai.model')),
            timeout: $this->timeout(),
        );
    }

    /**
     * Build the OpenAI-compatible HTTP client, degrading to the no-op client
     * when no base URL is configured (R30.5).
     */
    private function makeHttpClient(): LlmClient
    {
        $baseUrl = $this->stringOrNull($this->config->get('ai.base_url'));

        if ($baseUrl === null) {
            return new NullLlmClient;
        }

        return new HttpLlmClient(
            baseUrl: $baseUrl,
            model: $this->stringOrNull($this->config->get('ai.model')),
            apiKey: $this->stringOrNull($this->config->get('ai.api_key')),
            timeout: $this->timeout(),
        );
    }

    /**
     * The configured per-request timeout in seconds, defaulting to 30s.
     */
    private function timeout(): int
    {
        $timeout = (int) $this->config->get('ai.timeout', 30);

        return $timeout > 0 ? $timeout : 30;
    }

    /**
     * Normalise a config value to a non-empty trimmed string, or null. Treats
     * blank/whitespace-only values as "not set" so partial configuration
     * degrades gracefully.
     */
    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
