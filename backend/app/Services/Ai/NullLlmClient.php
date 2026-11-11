<?php

declare(strict_types=1);

namespace App\Services\Ai;

/**
 * The graceful no-op {@see LlmClient} (R30.5).
 *
 * Resolved whenever AI reporting is disabled or `LLM_PROVIDER` is `none` (or any
 * unrecognised value — see {@see LlmProvider::fromConfig()}). It is also the
 * default the application boots with when zero AI configuration is present
 * (R30.3), which is why the whole AI layer can ship behind flags without any
 * backend configured.
 *
 * {@see complete()} performs no network call and always returns an empty string
 * — the "safe empty result" the design's Degradation note prescribes — so a
 * caller that routes through the AI layer while it is off simply receives no AI
 * output and standard (non-AI) reporting remains fully functional.
 */
final class NullLlmClient implements LlmClient
{
    /**
     * Return an empty completion without contacting any provider (R30.5).
     */
    public function complete(string $prompt): string
    {
        return '';
    }
}
