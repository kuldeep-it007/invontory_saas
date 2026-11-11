<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Ai\AiReportService;
use App\Services\Ai\DatasetMinimizer;
use App\Services\Ai\LlmClient;
use App\Services\Ai\LlmClientFactory;
use App\Services\Ai\PiiRedactor;
use App\Services\ReportQueryService;
use App\Services\SubscriptionGateService;
use App\Support\AuditRecorder;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the AI reporting layer's {@see LlmClient} abstraction (M5b, R30.4).
 *
 * The container resolves {@see LlmClient} through the {@see LlmClientFactory},
 * which maps the `config/ai.php` keys to a concrete provider implementation
 * (OllamaClient / HttpLlmClient / NullLlmClient). Callers (e.g. the forthcoming
 * AiReportService) type-hint only the interface, so the configured provider is
 * swapped purely through configuration.
 *
 * The binding is a singleton: the resolved client is stateless per request and
 * holds only configuration, so one instance is reused across a request. Because
 * the factory always returns a safe client (defaulting to the NullLlmClient
 * when AI is disabled, `none`, or unconfigured), resolving {@see LlmClient}
 * never fails — the app builds and runs with zero AI config (R30.3, R30.5).
 */
class AiServiceProvider extends ServiceProvider
{
    /**
     * Register the AI layer's container bindings.
     */
    public function register(): void
    {
        $this->app->singleton(LlmClientFactory::class, function ($app): LlmClientFactory {
            return new LlmClientFactory($app->make(Config::class));
        });

        $this->app->singleton(LlmClient::class, function ($app): LlmClient {
            return $app->make(LlmClientFactory::class)->make();
        });

        // The AI reporting layer (task 13.3) — a thin wrapper over the shared
        // ReportQueryService that enforces the composite AI gate, maps NL to a
        // validated ReportQuerySpec via the resolved LlmClient, and runs the
        // validated spec through the SAME query path standard reports use
        // (R31.3). Bound per-request so it composes the request-scoped gate,
        // client, and query service.
        $this->app->bind(AiReportService::class, function ($app): AiReportService {
            return new AiReportService(
                $app->make(SubscriptionGateService::class),
                $app->make(LlmClient::class),
                $app->make(ReportQueryService::class),
                $app->make(DatasetMinimizer::class),
                $app->make(PiiRedactor::class),
                // The canonical audit writer so EVERY AI request (generate,
                // summary, follow-up) is recorded in the tenant Audit_Log
                // (R32.5). Best-effort — an audit failure never blocks the AI
                // response.
                $app->make(AuditRecorder::class),
            );
        });
    }
}
