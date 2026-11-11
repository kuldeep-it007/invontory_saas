<?php

declare(strict_types=1);

use App\Services\Ai\HttpLlmClient;
use App\Services\Ai\LlmClient;
use App\Services\Ai\LlmClientFactory;
use App\Services\Ai\NullLlmClient;
use App\Services\Ai\OllamaClient;
use Illuminate\Config\Repository;
use Innmind\BlackBox\Set;

/*
|--------------------------------------------------------------------------
| Task 13.2 — Property 33: LlmClient resolution
|--------------------------------------------------------------------------
|
| Feature: inventory-distribution-platform, Property 33: LlmClient resolution
|
| Validates: Requirements 30.4, 30.5
|
| Property statement (tasks.md §13.2 / design.md "AI Reporting Layer"):
|   For ANY AI configuration, LlmClientFactory::make() resolves to the correct
|   concrete LlmClient — and never fails to resolve:
|     • AI disabled (ai.enabled false)                      -> NullLlmClient (R30.5)
|     • LLM_PROVIDER = none / unrecognised / blank          -> NullLlmClient (R30.5)
|     • LLM_PROVIDER = ollama (with a base_url)             -> OllamaClient  (R30.4)
|     • LLM_PROVIDER = openai_compatible (with a base_url)  -> HttpLlmClient (R30.4)
|     • a provider missing its required base_url            -> NullLlmClient (R30.5)
|   Provider config maps to the correct client; the NullLlmClient degrades
|   gracefully (resolves and completes) without error.
|
| Strategy — pure logic, no database.
|   LlmClientFactory depends only on an Illuminate config Repository, so every
|   scenario constructs the factory over a fresh in-memory Repository seeded
|   with a generated (enabled, provider, base_url, api_key, timeout) tuple. No
|   MySQL, tenancy, or HTTP is touched: resolution is a pure function of config,
|   so this proof runs standalone.
|
|   The expected concrete class is derived here from the SAME rules the design
|   prescribes (an independent oracle, not a re-call of the factory), so a drift
|   in the factory's mapping is caught. We additionally assert the resolved
|   client always satisfies the LlmClient contract and that the degraded /
|   no-op NullLlmClient path returns an empty completion without throwing
|   (R30.5).
|
|   Generation uses innmind/black-box (NOT hand-rolled) via the shared
|   static::forAll(), drawing the mandated minimum of 100 generated scenarii per
|   proof and shrinking any counterexample to a minimal failing input.
*/

/**
 * Build an LlmClientFactory over a fresh in-memory config Repository seeded
 * with the given AI config — mirroring config/ai.php's key layout exactly.
 *
 * @param  mixed  $provider  the raw `ai.provider` value (string, blank, or null)
 */
function llmFactoryWith(
    bool $enabled,
    mixed $provider,
    ?string $baseUrl,
    ?string $apiKey,
    int $timeout,
): LlmClientFactory {
    $config = new Repository([
        'ai' => [
            'enabled' => $enabled,
            'provider' => $provider,
            'model' => null,
            'base_url' => $baseUrl,
            'api_key' => $apiKey,
            'timeout' => $timeout,
        ],
    ]);

    return new LlmClientFactory($config);
}

/**
 * The independent oracle for Property 33: given the same config inputs, return
 * the concrete LlmClient class the design mandates. This re-derives the mapping
 * from the stated rules rather than calling the factory, so it is a genuine
 * cross-check of the factory's resolution.
 *
 * @return class-string<LlmClient>
 */
function expectedLlmClientClass(
    bool $enabled,
    mixed $provider,
    ?string $baseUrl,
): string {
    // AI switched off entirely -> no-op, regardless of provider (R30.5).
    if (! $enabled) {
        return NullLlmClient::class;
    }

    // Normalise the provider the way LlmProvider::fromConfig does: lower-cased,
    // trimmed; anything null/blank/unrecognised collapses to "none".
    $normalized = is_string($provider) ? trim(strtolower($provider)) : '';
    $hasBaseUrl = is_string($baseUrl) && trim($baseUrl) !== '';

    return match ($normalized) {
        // A recognised provider still degrades to the no-op when its required
        // base_url is missing/blank (R30.5).
        'ollama' => $hasBaseUrl ? OllamaClient::class : NullLlmClient::class,
        'openai_compatible' => $hasBaseUrl ? HttpLlmClient::class : NullLlmClient::class,
        // "none" and every unrecognised/blank value -> no-op (R30.5).
        default => NullLlmClient::class,
    };
}

/**
 * A BlackBox set of raw `ai.provider` values spanning the whole input space:
 *   - the two recognised backing-enum values (ollama / openai_compatible),
 *   - the explicit "none",
 *   - case / whitespace variants that must still normalise correctly,
 *   - unrecognised strings and a blank string (both -> none),
 *   - a null (provider key unset) (-> none).
 */
function llmProviderValueSet(): Set
{
    return Set::of(
        'ollama',
        'openai_compatible',
        'none',
        'OLLAMA',
        '  Ollama  ',
        'OpenAI_Compatible',
        'None',
        'gpt',
        'anthropic',
        'unknown-provider',
        '',
        '   ',
        null,
    );
}

/**
 * A BlackBox set of raw `ai.base_url` values: real URLs, a null (unset), a
 * blank string, and whitespace-only — the latter three must be treated as
 * "not configured" so a provider degrades to the no-op (R30.5).
 */
function llmBaseUrlSet(): Set
{
    return Set::of(
        'http://localhost:11434',
        'https://api.example.com',
        'http://ollama.internal:11434',
        null,
        '',
        '   ',
    );
}

it('resolves AI config to the correct concrete LlmClient across every (enabled, provider, base_url, api_key, timeout) combination (R30.4, R30.5)', function () {
    static::forAll(
        Set::of(true, false),
        llmProviderValueSet(),
        llmBaseUrlSet(),
        // api_key: present / unset / blank — never affects which client resolves.
        Set::of('sk-test-key', null, ''),
        // timeout: zero/negative must not break resolution (factory clamps it).
        Set::integers()->between(-5, 120),
    )->then(function (
        bool $enabled,
        mixed $provider,
        ?string $baseUrl,
        ?string $apiKey,
        int $timeout,
    ) {
        $factory = llmFactoryWith($enabled, $provider, $baseUrl, $apiKey, $timeout);

        $client = $factory->make();

        $expected = expectedLlmClientClass($enabled, $provider, $baseUrl);

        // The resolved client is always a valid LlmClient (never fails to
        // resolve), and is exactly the class the design's rules mandate.
        expect($client)->toBeInstanceOf(LlmClient::class);
        expect($client::class)->toBe(
            $expected,
            sprintf(
                'enabled=%s provider=%s base_url=%s must resolve to %s, got %s.',
                var_export($enabled, true),
                var_export($provider, true),
                var_export($baseUrl, true),
                $expected,
                $client::class,
            ),
        );

        // Resolution must be stable: identical config yields the same class.
        expect($factory->make()::class)->toBe($client::class);

        // Whenever we degrade to the no-op client, it completes gracefully with
        // an empty string and never throws (R30.5).
        if ($client instanceof NullLlmClient) {
            expect($client->complete('any prompt'))->toBe('');
        }
    });
});

it('always degrades to a graceful NullLlmClient when AI is disabled, whatever the provider/base_url (R30.5)', function () {
    static::forAll(
        llmProviderValueSet(),
        llmBaseUrlSet(),
    )->then(function (mixed $provider, ?string $baseUrl) {
        // enabled=false short-circuits the whole mapping to the no-op client.
        $client = llmFactoryWith(false, $provider, $baseUrl, 'sk-key', 30)->make();

        expect($client)->toBeInstanceOf(NullLlmClient::class);
        // The no-op resolves AND completes without error — the graceful
        // degradation Property 33 asserts.
        expect($client->complete('report please'))->toBe('');
    });
});

it('degrades a recognised provider to the NullLlmClient when its required base_url is missing or blank (R30.5)', function () {
    static::forAll(
        Set::of('ollama', 'openai_compatible'),
        // Only "unconfigured" base URLs: null, empty, or whitespace-only.
        Set::of(null, '', '   ', "\t"),
    )->then(function (string $provider, ?string $baseUrl) {
        $client = llmFactoryWith(true, $provider, $baseUrl, 'sk-key', 30)->make();

        expect($client)->toBeInstanceOf(NullLlmClient::class);
        expect($client->complete('x'))->toBe('');
    });
});
