<?php

namespace App\Providers;

use App\Http\Middleware\ReconcileTenantUser;
use App\Models\PersonalAccessToken;
use App\Reports\Definitions\LowStockReportDefinition;
use App\Reports\Definitions\OrderStatusReportDefinition;
use App\Reports\Definitions\OutstandingAgeingReportDefinition;
use App\Reports\Definitions\PaymentsReportDefinition;
use App\Reports\Definitions\PlanUsageReportDefinition;
use App\Reports\Definitions\PurchasesReportDefinition;
use App\Reports\Definitions\SalesReportDefinition;
use App\Reports\Definitions\StockLedgerReportDefinition;
use App\Reports\Definitions\StockOnHandReportDefinition;
use App\Reports\Export\ReportExportWriterFactory;
use App\Reports\ReportDefinition;
use App\Services\ReportCache;
use App\Services\ReportExportService;
use App\Services\ReportQueryService;
use App\Services\TenantIntegrations\TenantExportStorage;
use App\Tenancy\ActiveDomainTenantResolver;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerReportQueryLayer();
        $this->registerReportExportLayer();
        $this->registerTenantHostResolver();
    }

    /**
     * Resolve tenants by exact host through ACTIVE domain rows only, with a short
     * cache that Domain invalidates whenever a row changes.
     */
    private function registerTenantHostResolver(): void
    {
        $this->app->bind(DomainTenantResolver::class, ActiveDomainTenantResolver::class);

        DomainTenantResolver::$shouldCache = (bool) config('domains.resolver_cache');
        DomainTenantResolver::$cacheTTL = (int) config('domains.resolver_cache_ttl');
    }

    /**
     * Bind the report export + scheduling layer (R29.4, R29.5, R29.6).
     *
     * {@see ReportExportService} builds on the shared {@see ReportQueryService}
     * so every export and scheduled run streams through the one query path
     * (inheriting defaults, scoping, and allow-listing). The
     * {@see ReportExportWriterFactory} resolves the per-format writer
     * (Excel/CSV/printable PDF — R29.4). Bound as a singleton so the writer
     * factory is shared across a request.
     */
    private function registerReportExportLayer(): void
    {
        $this->app->singleton(ReportExportWriterFactory::class);

        $this->app->singleton(ReportExportService::class, function ($app): ReportExportService {
            return new ReportExportService(
                $app->make(ReportQueryService::class),
                $app->make(ReportExportWriterFactory::class),
                $app->make(TenantExportStorage::class),
            );
        });
    }

    /**
     * Bind the single shared report query layer (R29, R31.3).
     *
     * {@see ReportQueryService} is the one query path both the standard report
     * catalog and the AI layer run through. It is a singleton so the registered
     * report-definition catalog is built once per request. Definitions are
     * collected from the `report.definitions` tag, so the standard catalog
     * (task 11.6) can register additional reports simply by tagging them —
     * without touching this binding.
     */
    private function registerReportQueryLayer(): void
    {
        // The report-definition catalog. Task 11.1 shipped the shared engine
        // plus the stock-on-hand definition; task 11.6 tags the remaining
        // standard reports (R28.1–R28.9). Each tagged definition is collected
        // into the ReportQueryService below, so adding a report is a matter of
        // tagging it here — the engine and the HTTP surface need no change.
        $this->app->tag([
            StockOnHandReportDefinition::class,       // R28.1 stock-on-hand
            StockLedgerReportDefinition::class,        // R28.2 stock-ledger
            SalesReportDefinition::class,              // R28.3 sales
            PurchasesReportDefinition::class,          // R28.4 purchases
            OutstandingAgeingReportDefinition::class,  // R28.5 outstanding-ageing
            PaymentsReportDefinition::class,           // R28.6 payments/collections
            OrderStatusReportDefinition::class,        // R28.7 order-status/challan
            LowStockReportDefinition::class,           // R28.8 low-stock
            PlanUsageReportDefinition::class,          // R28.9 plan-usage
        ], 'report.definitions');

        // The per-tenant report aggregate cache (R29.8) is a shared singleton so
        // the SAME generation token backs both the query layer's reads and the
        // write services' invalidations — a confirmation/payment/stock movement
        // bumping the generation is observed by the next report read.
        $this->app->singleton(ReportCache::class);

        $this->app->singleton(ReportQueryService::class, function ($app): ReportQueryService {
            /** @var iterable<ReportDefinition> $definitions */
            $definitions = $app->tagged('report.definitions');

            return new ReportQueryService($definitions, $app->make(ReportCache::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->rejectUnidentifiedTenants();
        $this->useTenantStampedTokens();
    }

    /**
     * Resolve Sanctum tokens through the tenant-stamped
     * {@see PersonalAccessToken} model (R1.6).
     *
     * The custom model carries a `tenant_id` stamp recorded at login so the
     * post-authentication {@see ReconcileTenantUser}
     * guard can reject a token replayed across tenant databases. Sanctum must
     * be told to use this model so the stamp is loaded on every authenticated
     * request.
     */
    private function useTenantStampedTokens(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }

    /**
     * Reject tenant requests that resolve to no tenant with HTTP 403 (R1.4).
     *
     * stancl/tenancy's identification middleware throws a
     * TenantCouldNotBeIdentifiedException by default. Per the isolation
     * requirement, a request whose tenant identification is unresolved (or
     * does not match an initialized tenant database) must be rejected with a
     * 403 — not leak as an unhandled 500. We install a shared `onFail`
     * handler on each identification middleware so the behavior is uniform
     * regardless of which resolver runs, and the centralized API exception
     * renderer wraps the abort in the standard
     * `{ "error": { code, message, details } }` envelope.
     *
     * The combined `InitializeTenancyByDomainOrSubdomain` delegates to one of
     * these two resolvers at runtime, so configuring both covers it.
     */
    private function rejectUnidentifiedTenants(): void
    {
        $onFail = fn () => abort(403, 'The request could not be matched to a tenant.');

        InitializeTenancyByDomain::$onFail = $onFail;
        InitializeTenancyBySubdomain::$onFail = $onFail;
    }
}
