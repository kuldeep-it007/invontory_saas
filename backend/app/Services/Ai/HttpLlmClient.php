<?php

declare(strict_types=1);

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The {@see LlmClient} for any OpenAI-compatible chat-completions endpoint
 * (R30.4).
 *
 * Resolved when `LLM_PROVIDER=openai_compatible`. Posts to
 * `POST {base_url}/v1/chat/completions` with a single user message, reading the
 * configured `model`, `base_url`, `api_key`, and `timeout` (R30.2). The API key,
 * when present, is sent as a bearer token; endpoints that need no auth simply
 * leave it unset.
 *
 * Consistent with the design's Degradation note, a transport failure or an
 * unexpected response shape never throws out of {@see complete()}: it is logged
 * out-of-band and an empty completion is returned so the AI path degrades to
 * "no output" and standard reporting is unaffected (R30.3, R30.5).
 */
final class HttpLlmClient implements LlmClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $model,
        private readonly ?string $apiKey,
        private readonly int $timeout,
    ) {}

    /**
     * Request a chat completion from the OpenAI-compatible endpoint, returning
     * an empty string on any failure (R30.5).
     */
    public function complete(string $prompt): string
    {
        try {
            $request = Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->acceptJson()
                ->asJson();

            if ($this->apiKey !== null && $this->apiKey !== '') {
                $request = $request->withToken($this->apiKey);
            }

            $response = $request->post('/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            if ($response->failed()) {
                return '';
            }

            // OpenAI-compatible responses place the completion at
            // choices[0].message.content.
            return (string) ($response->json('choices.0.message.content') ?? '');
        } catch (Throwable $e) {
            Log::warning('HttpLlmClient completion failed; degrading to empty result.', [
                'exception' => $e->getMessage(),
            ]);

            return '';
        }
    }
}
