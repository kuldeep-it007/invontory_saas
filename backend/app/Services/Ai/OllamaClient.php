<?php

declare(strict_types=1);

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The {@see LlmClient} for a local/self-hosted Ollama daemon (R30.4).
 *
 * Resolved when `LLM_PROVIDER=ollama`. Talks to Ollama's native
 * `POST {base_url}/api/generate` endpoint with streaming disabled, reading the
 * configured `model`, `base_url`, and `timeout` (R30.2). No API key is used —
 * Ollama runs unauthenticated on a trusted local network.
 *
 * Consistent with the design's Degradation note, a transport failure or an
 * unexpected response shape never throws out of {@see complete()}: it is logged
 * out-of-band and an empty completion is returned so the AI path degrades to
 * "no output" and standard reporting is unaffected (R30.3, R30.5).
 */
final class OllamaClient implements LlmClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $model,
        private readonly int $timeout,
    ) {}

    /**
     * Request a completion from the Ollama daemon, returning an empty string on
     * any failure (R30.5).
     */
    public function complete(string $prompt): string
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->acceptJson()
                ->post('/api/generate', [
                    'model' => $this->model,
                    'prompt' => $prompt,
                    'stream' => false,
                ]);

            if ($response->failed()) {
                return '';
            }

            // Ollama's /api/generate returns the completion under `response`.
            return (string) ($response->json('response') ?? '');
        } catch (Throwable $e) {
            Log::warning('OllamaClient completion failed; degrading to empty result.', [
                'exception' => $e->getMessage(),
            ]);

            return '';
        }
    }
}
