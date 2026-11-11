<?php

declare(strict_types=1);

use App\Services\Ai\LlmProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | AI Reporting Layer — provider resolution (R30.1–R30.5)
    |--------------------------------------------------------------------------
    |
    | The optional natural-language reporting subsystem (the "AI_Layer") is a
    | thin wrapper over the shared report query layer. It ships behind flags and
    | the application builds and runs with ZERO AI configuration present (R30.3)
    | — every key below has a safe default and resolving the layer with nothing
    | set yields the NullLlmClient (a graceful no-op — R30.5).
    |
    | These keys are read ONLY from this config (not env() at call time) so they
    | are cacheable via `php artisan config:cache` and so there is exactly one
    | place that maps the environment to the AI layer's behaviour.
    |
    */

    /*
    | The platform-wide master switch (R30.1). Defaults to false, so the AI
    | layer is OFF unless an operator explicitly turns it on. The composite gate
    | (this switch AND the per-tenant `ai_reports` feature flag — R30.6, R30.7)
    | lives in SubscriptionGateService, which reads the same env var via
    | `services.ai.reports_enabled`; this entry mirrors it for the AI config
    | namespace so the provider factory can short-circuit to the NullLlmClient
    | whenever AI is disabled.
    */
    'enabled' => (bool) env('AI_REPORTS_ENABLED', false),

    /*
    | The configured LLM provider (R30.2, R30.4). One of:
    |   - `ollama`            -> App\Services\Ai\OllamaClient
    |   - `openai_compatible` -> App\Services\Ai\HttpLlmClient
    |   - `none`              -> App\Services\Ai\NullLlmClient (graceful no-op)
    |
    | Defaults to `none` so a fresh install resolves the NullLlmClient and the
    | app boots with no AI backend configured (R30.3, R30.5). Any unrecognised
    | value also falls back to the NullLlmClient rather than failing to boot.
    */
    'provider' => env('LLM_PROVIDER', LlmProvider::None->value),

    /*
    | The model identifier passed to the provider (e.g. `llama3.1`,
    | `gpt-4o-mini`). Optional; unused by the NullLlmClient.
    */
    'model' => env('LLM_MODEL'),

    /*
    | The provider base URL (e.g. `http://localhost:11434` for a local Ollama
    | daemon, or an OpenAI-compatible endpoint). Optional; unused by the
    | NullLlmClient.
    */
    'base_url' => env('LLM_BASE_URL'),

    /*
    | The provider API key for OpenAI-compatible endpoints that require bearer
    | auth. Optional; local Ollama and the NullLlmClient ignore it.
    */
    'api_key' => env('LLM_API_KEY'),

    /*
    | The per-request timeout, in seconds, for an outbound completion call.
    | Defaults to 30s so a slow/unreachable provider fails fast rather than
    | hanging a request.
    */
    'timeout' => (int) env('LLM_TIMEOUT', 30),

];
