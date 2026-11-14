<?php

declare(strict_types=1);

use App\Exceptions\FeatureDisabledException;
use App\Exceptions\QuotaExceededException;
use App\Http\Middleware\ResolveTenantByHost;
use App\Jobs\VerifyCustomDomain;
use App\Models\Domain;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\PlanLimit;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\CustomDomainService;
use App\Services\CustomDomainSslService;
use App\Services\Dns\DnsResolver;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Exceptions\DomainOccupiedByOtherTenantException;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
|--------------------------------------------------------------------------
| Custom domain mapping
|--------------------------------------------------------------------------
|
| Runs the real migrations on an in-memory SQLite `central` connection, so no
| MySQL server is needed. Tenancy is never initialised against a tenant
| database; host resolution, DNS verification and the domain lifecycle all
| live in the central database. DNS is faked with a developer-defined resolver.
*/

beforeEach(function () {
    config([
        'database.connections.central' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'tenancy.central_domains' => ['platform.test'],
        'domains.target_host' => 'domains.platform.test',
        'domains.target_ips' => ['203.0.113.10'],
        'domains.max_per_tenant' => 3,
        'domains.manual_check_interval' => 20,
        'domains.pending_ttl_hours' => 72,
    ]);
    DB::purge('central');

    Artisan::call('migrate', [
        '--database' => 'central',
        '--realpath' => true,
        '--force' => true,
        '--path' => [
            database_path('migrations/2019_09_15_000010_create_tenants_table.php'),
            database_path('migrations/2019_09_15_000020_create_domains_table.php'),
            database_path('migrations/2026_10_03_130000_create_plans_table.php'),
            database_path('migrations/2026_10_03_130100_create_plan_limits_table.php'),
            database_path('migrations/2026_10_03_130200_create_subscriptions_table.php'),
            database_path('migrations/2026_10_03_130300_create_plan_features_table.php'),
            database_path('migrations/2026_11_20_000000_add_custom_domain_columns_to_domains_table.php'),
        ],
    ]);

    fakeDns();
});

afterEach(function () {
    tenancy()->end();
});

/**
 * Replace DNS with canned answers.
 *
 * @param  array<string, list<string>>  $txt  keyed by record name
 * @param  array<string, list<string>>  $a  keyed by host
 * @param  array<string, list<string>>  $cname  keyed by host
 */
function fakeDns(array $txt = [], array $a = [], array $cname = []): void
{
    app()->instance(DnsResolver::class, new class($txt, $a, $cname) extends DnsResolver
    {
        public function __construct(private array $txtRecords, private array $aRecords, private array $cnameRecords) {}

        public function txt(string $name): array
        {
            return $this->txtRecords[$name] ?? [];
        }

        public function a(string $host): array
        {
            return $this->aRecords[$host] ?? [];
        }

        public function cname(string $host): array
        {
            return $this->cnameRecords[$host] ?? [];
        }
    });
}

function makeTenant(string $id): Tenant
{
    DB::connection('central')->table('tenants')->insert([
        'id' => $id, 'subdomain' => $id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    Domain::query()->create(['domain' => $id.'.platform.test', 'tenant_id' => $id]);

    return Tenant::query()->findOrFail($id);
}

function domainService(): CustomDomainService
{
    return app(CustomDomainService::class);
}

/** Answers that prove ownership of $domain and route it to the platform. */
function proveOwnership(Domain $domain, bool $routing = true): void
{
    fakeDns(
        txt: [$domain->txtRecordName() => [$domain->txtRecordValue()]],
        cname: $routing ? [$domain->domain => ['domains.platform.test']] : [],
    );
}

function activeCustomDomain(Tenant $tenant, string $host, array $attributes = []): Domain
{
    return Domain::query()->create(array_merge([
        'domain' => $host,
        'tenant_id' => $tenant->getTenantKey(),
        'type' => Domain::TYPE_CUSTOM,
        'status' => Domain::STATUS_ACTIVE,
        'verification_token' => 'token-'.$host,
        'routing_ok' => true,
        'ssl_status' => Domain::SSL_PENDING,
    ], $attributes));
}

function planFor(Tenant $tenant, array $features = [], array $limits = []): void
{
    $plan = Plan::query()->create(['name' => 'Plan '.$tenant->getTenantKey(), 'price' => 0, 'billing_cycle' => 'monthly', 'is_active' => true]);
    foreach ($features as $key => $enabled) {
        $plan->features()->create(['key' => $key, 'enabled' => $enabled]);
    }
    foreach ($limits as $key => $value) {
        $plan->limits()->create(['key' => $key, 'value' => $value]);
    }
    Subscription::query()->create([
        'tenant_id' => $tenant->getTenantKey(), 'plan_id' => $plan->getKey(),
        'status' => Subscription::STATUS_ACTIVE, 'starts_at' => now(),
    ]);
}

describe('adding a domain', function () {
    it('creates a pending claim with a verification token and a normalized host', function () {
        $tenant = makeTenant('acme');

        $domain = domainService()->add($tenant, 'https://Shop.Example.COM/', null);

        expect($domain->domain)->toBe('shop.example.com')
            ->and($domain->status)->toBe(Domain::STATUS_PENDING)
            ->and($domain->type)->toBe(Domain::TYPE_CUSTOM)
            ->and($domain->tenant_id)->toBe('acme')
            ->and(strlen($domain->verification_token))->toBe(40)
            ->and($domain->active_key)->toBeNull()
            ->and($domain->txtRecordName())->toBe('_inventory-verify.shop.example.com')
            ->and($domain->txtRecordValue())->toBe('inventory-verify='.$domain->verification_token);
    });

    it('rejects platform domains and anything under them', function (string $host) {
        $tenant = makeTenant('acme');

        domainService()->add($tenant, $host, null);
    })->with(['platform.test', 'other.platform.test', 'Evil.PLATFORM.test', 'domains.platform.test'])
        ->throws(ValidationException::class, 'platform');

    it('rejects a value that is not a public hostname', function (string $value) {
        domainService()->add(makeTenant('acme'), $value, null);
    })->with(['203.0.113.7', 'localhost', 'shop.example.com:8443', '*.example.com'])
        ->throws(ValidationException::class, 'valid domain name');

    it('returns 409 when the workspace already added the domain', function () {
        $tenant = makeTenant('acme');
        domainService()->add($tenant, 'shop.example.com', null);

        expect(fn () => domainService()->add($tenant, 'SHOP.example.com', null))
            ->toThrow(ConflictHttpException::class, 'already added');
    });

    it('accepts a claim on a host another workspace serves without serving it', function () {
        activeCustomDomain(makeTenant('other'), 'shop.example.com');

        domainService()->add(makeTenant('acme'), 'shop.example.com', null);

        expect(app(DomainTenantResolver::class)->resolve('shop.example.com')->getTenantKey())->toBe('other');
    });

    it('lets two workspaces hold an unverified claim on the same host', function () {
        domainService()->add(makeTenant('acme'), 'shop.example.com', null);
        domainService()->add(makeTenant('other'), 'shop.example.com', null);

        expect(Domain::query()->where('domain', 'shop.example.com')->count())->toBe(2);
    });

    it('returns 403 once the configured domain limit is reached', function () {
        config(['domains.max_per_tenant' => 1]);
        $tenant = makeTenant('acme');
        domainService()->add($tenant, 'one.example.com', null);

        expect(fn () => domainService()->add($tenant, 'two.example.com', null))
            ->toThrow(QuotaExceededException::class);
    });

    it('lets the plan limit override the default and treats 0 as unlimited', function () {
        config(['domains.max_per_tenant' => 1]);
        $tenant = makeTenant('acme');
        planFor($tenant, limits: [PlanLimit::MAX_CUSTOM_DOMAINS => 0]);

        domainService()->add($tenant, 'one.example.com', null);
        domainService()->add($tenant, 'two.example.com', null);

        expect(Domain::query()->custom()->count())->toBe(2);
    });

    it('returns 403 when the plan disables custom domains', function () {
        $tenant = makeTenant('acme');
        planFor($tenant, features: [PlanFeature::CUSTOM_DOMAINS => false]);

        expect(fn () => domainService()->add($tenant, 'shop.example.com', null))
            ->toThrow(FeatureDisabledException::class);
    });
});

describe('verifying a domain', function () {
    it('activates the domain once the TXT record matches and flags routing', function () {
        $domain = domainService()->add(makeTenant('acme'), 'shop.example.com', null);
        proveOwnership($domain);

        $domain = domainService()->verify($domain);

        expect($domain->status)->toBe(Domain::STATUS_ACTIVE)
            ->and($domain->active_key)->toBe('shop.example.com')
            ->and($domain->routing_ok)->toBeTrue()
            ->and($domain->ssl_status)->toBe(Domain::SSL_PENDING)
            ->and($domain->verified_at)->not->toBeNull()
            ->and($domain->last_error)->toBeNull();
    });

    it('accepts an A record pointing at the platform IP as routing', function () {
        $domain = domainService()->add(makeTenant('acme'), 'example.store', null);
        fakeDns(
            txt: [$domain->txtRecordName() => [$domain->txtRecordValue()]],
            a: ['example.store' => ['203.0.113.10']],
        );

        expect(domainService()->verify($domain)->routing_ok)->toBeTrue();
    });

    it('activates but reports missing routing when DNS does not point at the platform', function () {
        $domain = domainService()->add(makeTenant('acme'), 'shop.example.com', null);
        proveOwnership($domain, routing: false);

        $domain = domainService()->verify($domain);

        expect($domain->status)->toBe(Domain::STATUS_ACTIVE)
            ->and($domain->routing_ok)->toBeFalse()
            ->and($domain->last_error)->toContain('Routing record');
    });

    it('stays pending with a hint when the TXT record is missing', function () {
        $domain = domainService()->add(makeTenant('acme'), 'shop.example.com', null);

        $domain = domainService()->verify($domain);

        expect($domain->status)->toBe(Domain::STATUS_PENDING)
            ->and($domain->last_error)->toContain('TXT record not found')
            ->and($domain->check_attempts)->toBe(1);
    });

    it('stays pending when a TXT record exists with the wrong value', function () {
        $domain = domainService()->add(makeTenant('acme'), 'shop.example.com', null);
        fakeDns(txt: [$domain->txtRecordName() => ['inventory-verify=someone-elses-token']]);

        $domain = domainService()->verify($domain);

        expect($domain->status)->toBe(Domain::STATUS_PENDING)
            ->and($domain->last_error)->toContain('does not match');
    });

    it('marks the claim failed after the verification window expires', function () {
        $domain = domainService()->add(makeTenant('acme'), 'shop.example.com', null);

        $this->travel(73)->hours();
        $domain = domainService()->verify($domain);

        expect($domain->status)->toBe(Domain::STATUS_FAILED)
            ->and($domain->last_error)->toContain('expired');
    });

    it('re-arms a failed claim on a manual check', function () {
        $domain = domainService()->add(makeTenant('acme'), 'shop.example.com', null);
        $this->travel(73)->hours();
        domainService()->verify($domain);

        $this->travel(1)->minutes();
        $domain = domainService()->verify($domain->refresh(), manual: true);

        expect($domain->status)->toBe(Domain::STATUS_PENDING)
            ->and($domain->expires_at->isFuture())->toBeTrue();
    });

    it('ignores a second manual check inside the rate-limit window', function () {
        $domain = domainService()->add(makeTenant('acme'), 'shop.example.com', null);
        domainService()->verify($domain, manual: true);

        $domain = domainService()->verify($domain->refresh(), manual: true);

        expect($domain->check_attempts)->toBe(1);
    });

    it('deletes competing unverified claims when one workspace proves ownership', function () {
        $loser = domainService()->add(makeTenant('other'), 'shop.example.com', null);
        $winner = domainService()->add(makeTenant('acme'), 'shop.example.com', null);
        proveOwnership($winner);

        domainService()->verify($winner);

        expect(Domain::query()->find($loser->getKey()))->toBeNull()
            ->and(Domain::query()->active()->where('domain', 'shop.example.com')->value('tenant_id'))->toBe('acme');
    });

    it('moves a host to the verified claimant when the old holder lost its TXT record', function () {
        $old = activeCustomDomain(makeTenant('other'), 'shop.example.com');
        $claim = domainService()->add(makeTenant('acme'), 'shop.example.com', null);
        proveOwnership($claim);

        domainService()->verify($claim);

        expect($claim->refresh()->status)->toBe(Domain::STATUS_ACTIVE)
            ->and($old->refresh()->status)->toBe(Domain::STATUS_FAILED);
    });

    it('keeps the host with its current holder while the holder still proves ownership', function () {
        $old = activeCustomDomain(makeTenant('other'), 'shop.example.com');
        $claim = domainService()->add(makeTenant('acme'), 'shop.example.com', null);
        fakeDns(txt: [
            $old->txtRecordName() => [$old->txtRecordValue(), $claim->txtRecordValue()],
        ]);

        $claim = domainService()->verify($claim);

        expect($claim->status)->toBe(Domain::STATUS_FAILED)
            ->and($claim->last_error)->toContain('another workspace')
            ->and($old->refresh()->status)->toBe(Domain::STATUS_ACTIVE);
    });

    it('flags an active domain whose TXT record disappeared without disabling it', function () {
        $domain = activeCustomDomain(makeTenant('acme'), 'shop.example.com');
        fakeDns(cname: ['shop.example.com' => ['domains.platform.test']]);

        $domain = domainService()->verify($domain);

        expect($domain->status)->toBe(Domain::STATUS_ACTIVE)
            ->and($domain->last_error)->toContain('TXT record not found');
    });
});

describe('resolving a tenant from the host', function () {
    it('resolves a platform subdomain by its full host', function () {
        makeTenant('acme');

        $tenant = app(DomainTenantResolver::class)->resolve('acme.platform.test');

        expect($tenant->getTenantKey())->toBe('acme');
    });

    it('resolves a tenant whose row stores only the subdomain label, as provisioning does by default', function () {
        config(['tenancy.central_domains' => ['localhost', 'platform.test']]);
        $tenant = makeTenant('acme');
        Domain::query()->where('tenant_id', 'acme')->update(['domain' => 'acme', 'active_key' => 'acme']);
        $resolver = app(DomainTenantResolver::class);

        expect($resolver->resolve('acme.localhost')->getTenantKey())->toBe('acme')
            ->and($resolver->resolve('acme.platform.test')->getTenantKey())->toBe($tenant->getTenantKey());
    });

    it('does not resolve a bare label for a deeper subdomain or a non-central host', function (string $host) {
        makeTenant('acme');
        Domain::query()->where('tenant_id', 'acme')->update(['domain' => 'acme', 'active_key' => 'acme']);

        expect(fn () => app(DomainTenantResolver::class)->resolve($host))
            ->toThrow(TenantCouldNotBeIdentifiedOnDomainException::class);
    })->with(['x.acme.platform.test', 'acme.evil.test', 'acme.evilplatform.test']);

    it('never resolves a custom domain row through the bare-label rule', function () {
        $tenant = makeTenant('other');
        activeCustomDomain($tenant, 'shop.example.com');
        Domain::query()->where('domain', 'shop.example.com')->update(['domain' => 'acme', 'active_key' => 'acme']);

        expect(fn () => app(DomainTenantResolver::class)->resolve('acme.platform.test'))
            ->toThrow(TenantCouldNotBeIdentifiedOnDomainException::class);
    });

    it('resolves the same host on repeated requests with a cache that really serializes', function () {
        // Regression: the resolver used to cache the Tenant model, and a cache that
        // refuses to unserialize objects (cache.serializable_classes = false) handed
        // back an incomplete object on the second request, failing every login.
        config(['cache.default' => 'file', 'cache.stores.file.path' => storage_path('framework/cache/test-resolver')]);
        makeTenant('acme');
        Domain::query()->where('tenant_id', 'acme')->update(['domain' => 'acme', 'active_key' => 'acme']);

        $first = app(DomainTenantResolver::class)->resolve('acme.platform.test');
        $second = app(DomainTenantResolver::class)->resolve('acme.platform.test');

        expect($first->getTenantKey())->toBe('acme')
            ->and($second)->toBeInstanceOf(Tenant::class)
            ->and($second->getTenantKey())->toBe('acme');
    });

    it('stops resolving a label host as soon as its row stops being active', function () {
        makeTenant('acme');
        $row = Domain::query()->where('tenant_id', 'acme')->firstOrFail();
        $row->forceFill(['domain' => 'acme'])->save();
        app(DomainTenantResolver::class)->resolve('acme.platform.test');

        $row->forceFill(['status' => Domain::STATUS_DISABLED])->save();

        expect(fn () => app(DomainTenantResolver::class)->resolve('acme.platform.test'))
            ->toThrow(TenantCouldNotBeIdentifiedOnDomainException::class);
    });

    it('refuses to make a bare label the primary address', function () {
        makeTenant('acme');
        $row = Domain::query()->where('tenant_id', 'acme')->firstOrFail();
        $row->forceFill(['domain' => 'acme'])->save();

        expect(fn () => domainService()->setPrimary($row, true))->toThrow(ConflictHttpException::class);
    });

    it('returns 403 for the old login request when the host matches no tenant at all', function () {
        $request = Request::create('https://nobody.platform.test/api/login', 'POST');

        try {
            app(ResolveTenantByHost::class)->handle($request, fn () => response('ok'));
            $this->fail('Expected a 403.');
        } catch (HttpException $e) {
            expect($e->getStatusCode())->toBe(403);
        }
    });

    it('resolves an active custom domain to its tenant', function () {
        activeCustomDomain(makeTenant('acme'), 'inspiresoft.store');

        expect(app(DomainTenantResolver::class)->resolve('inspiresoft.store')->getTenantKey())->toBe('acme');
    });

    it('does not resolve a domain that is not active', function (string $status) {
        activeCustomDomain(makeTenant('acme'), 'shop.example.com', ['status' => $status]);

        expect(fn () => app(DomainTenantResolver::class)->resolve('shop.example.com'))
            ->toThrow(TenantCouldNotBeIdentifiedOnDomainException::class);
    })->with([Domain::STATUS_PENDING, Domain::STATUS_FAILED, Domain::STATUS_DISABLED]);

    it('stops resolving a cached host as soon as the domain is disabled', function () {
        $domain = activeCustomDomain(makeTenant('acme'), 'shop.example.com');
        $resolver = app(DomainTenantResolver::class);
        $resolver->resolve('shop.example.com');

        domainService()->disable($domain, null);

        expect(fn () => $resolver->resolve('shop.example.com'))
            ->toThrow(TenantCouldNotBeIdentifiedOnDomainException::class);
    });

    it('refuses two active rows for the same host', function () {
        activeCustomDomain(makeTenant('acme'), 'shop.example.com');

        expect(fn () => activeCustomDomain(makeTenant('other'), 'shop.example.com'))
            ->toThrow(DomainOccupiedByOtherTenantException::class);
    });

    it('returns 403 for a host that maps to no tenant', function () {
        $request = Request::create('https://unknown.example.org/api/me');

        expect(fn () => app(ResolveTenantByHost::class)->handle($request, fn () => response('ok')))
            ->toThrow(HttpException::class);
    });

    it('returns 403 when the host is an IP address', function () {
        $request = Request::create('https://203.0.113.5/api/me');

        try {
            app(ResolveTenantByHost::class)->handle($request, fn () => response('ok'));
            $this->fail('Expected a 403.');
        } catch (HttpException $e) {
            expect($e->getStatusCode())->toBe(403);
        }
    });
});

describe('managing domains', function () {
    it('removes a custom domain', function () {
        $domain = activeCustomDomain(makeTenant('acme'), 'shop.example.com');

        domainService()->remove($domain, null);

        expect(Domain::query()->find($domain->getKey()))->toBeNull();
    });

    it('returns 409 when removing the platform address', function () {
        makeTenant('acme');
        $platform = Domain::query()->where('domain', 'acme.platform.test')->firstOrFail();

        expect(fn () => domainService()->remove($platform, null))->toThrow(ConflictHttpException::class);
    });

    it('returns 409 when removing the only active address', function () {
        makeTenant('acme');
        Domain::query()->where('domain', 'acme.platform.test')->update(['status' => Domain::STATUS_DISABLED, 'active_key' => null]);
        $domain = activeCustomDomain(Tenant::query()->findOrFail('acme'), 'shop.example.com');

        expect(fn () => domainService()->remove($domain, null))->toThrow(ConflictHttpException::class, 'at least one');
    });

    it('requires an issued certificate before a custom domain can be primary', function () {
        $domain = activeCustomDomain(makeTenant('acme'), 'shop.example.com');

        expect(fn () => domainService()->setPrimary($domain, true))->toThrow(ConflictHttpException::class);
    });

    it('keeps a single primary address per workspace', function () {
        $first = activeCustomDomain(makeTenant('acme'), 'a.example.com', ['ssl_status' => Domain::SSL_ISSUED]);
        $second = activeCustomDomain(Tenant::query()->findOrFail('acme'), 'b.example.com', ['ssl_status' => Domain::SSL_ISSUED]);

        domainService()->setPrimary($first, true);
        domainService()->setPrimary($second, true);

        expect($first->refresh()->is_primary)->toBeFalse()
            ->and($second->refresh()->is_primary)->toBeTrue();
    });

    it('requires a disabled domain to prove DNS ownership again after the admin enables it', function () {
        $domain = activeCustomDomain(makeTenant('acme'), 'shop.example.com', ['is_primary' => true]);

        domainService()->disable($domain, null);
        expect($domain->refresh()->status)->toBe(Domain::STATUS_DISABLED)
            ->and($domain->is_primary)->toBeFalse();

        domainService()->enable($domain, null);
        expect($domain->refresh()->status)->toBe(Domain::STATUS_PENDING);
    });

    it('does not let a platform admin disable a platform address', function () {
        makeTenant('acme');
        $platform = Domain::query()->where('domain', 'acme.platform.test')->firstOrFail();

        domainService()->disable($platform, null);

        expect($platform->refresh()->status)->toBe(Domain::STATUS_ACTIVE);
    });

    it('lists DNS records with an A record recommended for a root domain and CNAME for a subdomain', function () {
        $tenant = makeTenant('acme');
        $root = domainService()->describe(activeCustomDomain($tenant, 'example.store'));
        $sub = domainService()->describe(activeCustomDomain($tenant, 'shop.example.store'));

        $recommended = fn (array $described) => collect($described['dns_records'])
            ->where('purpose', 'routing')->where('recommended', true)->pluck('type')->all();

        expect($recommended($root))->toBe(['A'])
            ->and($recommended($sub))->toBe(['CNAME'])
            ->and(collect($sub['dns_records'])->firstWhere('purpose', 'ownership')['host'])->toBe('_inventory-verify.shop');
    });
});

describe('certificate hand-off', function () {
    it('asks the host script to issue only domains that route to the server', function () {
        $tenant = makeTenant('acme');
        activeCustomDomain($tenant, 'ready.example.com');
        activeCustomDomain($tenant, 'unrouted.example.com', ['routing_ok' => false]);
        activeCustomDomain($tenant, 'done.example.com', ['ssl_status' => Domain::SSL_ISSUED]);
        Domain::query()->create(['domain' => 'pending.example.com', 'tenant_id' => 'acme', 'type' => 'custom', 'status' => 'pending']);

        $work = app(CustomDomainSslService::class)->work();

        expect($work['issue'])->toBe(['ready.example.com'])
            ->and($work['keep'])->toBe(['ready.example.com', 'unrouted.example.com', 'done.example.com']);
    });

    it('backs off after a failed issuance and gives up after the maximum attempts', function () {
        config(['domains.ssl.max_attempts' => 3, 'domains.ssl.retry_backoff' => 3600]);
        $tenant = makeTenant('acme');
        $domain = activeCustomDomain($tenant, 'shop.example.com', [
            'ssl_status' => Domain::SSL_FAILED, 'ssl_attempts' => 1, 'ssl_checked_at' => now(),
        ]);
        $ssl = app(CustomDomainSslService::class);

        expect($ssl->work()['issue'])->toBe([]);

        $this->travel(61)->minutes();
        expect($ssl->work()['issue'])->toBe(['shop.example.com']);

        $domain->forceFill(['ssl_attempts' => 3])->save();
        expect($ssl->work()['issue'])->toBe([]);
    });

    it('records issued and failed results and ignores unknown hosts and statuses', function () {
        $tenant = makeTenant('acme');
        $ok = activeCustomDomain($tenant, 'ok.example.com');
        $bad = activeCustomDomain($tenant, 'bad.example.com');
        $other = activeCustomDomain($tenant, 'other.example.com');

        $applied = app(CustomDomainSslService::class)->report([
            ['host' => 'ok.example.com', 'status' => 'issued', 'expires' => '2027-01-15T10:00:00Z'],
            ['host' => 'bad.example.com', 'status' => 'failed'],
            ['host' => 'other.example.com', 'status' => 'revoked-lol'],
            ['host' => 'nobody.example.com', 'status' => 'issued'],
            ['host' => ['array'], 'status' => 'issued'],
        ]);

        expect($applied)->toBe(2)
            ->and($ok->refresh()->ssl_status)->toBe(Domain::SSL_ISSUED)
            ->and($ok->ssl_expires_at->toDateString())->toBe('2027-01-15')
            ->and($bad->refresh()->ssl_status)->toBe(Domain::SSL_FAILED)
            ->and($bad->ssl_attempts)->toBe(1)
            ->and($other->refresh()->ssl_status)->toBe(Domain::SSL_PENDING);
    });
});

describe('scheduled verification', function () {
    it('queues checks only for domains that are due', function () {
        $tenant = makeTenant('acme');
        $fresh = domainService()->add($tenant, 'new.example.com', null);
        $recent = domainService()->add($tenant, 'recent.example.com', null);
        $recent->forceFill(['last_checked_at' => now()->subMinute()])->save();
        $failed = activeCustomDomain($tenant, 'failed.example.com', ['status' => Domain::STATUS_FAILED]);

        Queue::fake([VerifyCustomDomain::class]);
        Artisan::call('domains:verify-pending');

        Queue::assertPushed(VerifyCustomDomain::class, 1);
        Queue::assertPushed(VerifyCustomDomain::class, fn (VerifyCustomDomain $job) => $job->domainId === $fresh->getKey());
    });

    it('verifies the domain when the queued job runs', function () {
        $domain = domainService()->add(makeTenant('acme'), 'shop.example.com', null);
        proveOwnership($domain);

        (new VerifyCustomDomain($domain->getKey()))->handle(domainService());

        expect($domain->refresh()->status)->toBe(Domain::STATUS_ACTIVE);
    });
});

describe('endpoints', function () {
    it('limits the tenant routes to Company_Admin and the admin routes to Platform_Admin', function () {
        $router = app(Router::class);
        $middlewareFor = function (string $method, string $uri) use ($router): array {
            foreach ($router->getRoutes() as $route) {
                if ($route->uri() === $uri && in_array($method, $route->methods(), true)) {
                    return array_values($router->gatherRouteMiddleware($route));
                }
            }
            $this->fail("Route [{$method} {$uri}] was not found.");
        };

        foreach ([['GET', 'api/tenant/domains'], ['POST', 'api/tenant/domains'], ['POST', 'api/tenant/domains/{domain}/verify'], ['DELETE', 'api/tenant/domains/{domain}']] as [$method, $uri]) {
            expect($middlewareFor($method, $uri))->toContain('Spatie\Permission\Middleware\RoleMiddleware:Company_Admin');
        }
        expect($middlewareFor('POST', 'api/admin/domains/{domain}/disable'))->toContain('Spatie\Permission\Middleware\RoleMiddleware:Platform_Admin');
    });

    it('lists only the active tenant\'s domains', function () {
        $acme = makeTenant('acme');
        activeCustomDomain($acme, 'shop.example.com');
        activeCustomDomain(makeTenant('other'), 'rival.example.com');
        tenancy()->initialize($acme);

        $response = $this->withoutMiddleware()->getJson('/api/tenant/domains');

        $response->assertOk();
        expect(collect($response->json('data'))->pluck('domain')->sort()->values()->all())
            ->toBe(['acme.platform.test', 'shop.example.com']);
    });

    it('returns 404 when another tenant\'s domain id is used', function () {
        $rival = activeCustomDomain(makeTenant('other'), 'rival.example.com');
        tenancy()->initialize(makeTenant('acme'));

        $this->withoutMiddleware()->postJson("/api/tenant/domains/{$rival->getKey()}/verify")->assertNotFound();
        $this->withoutMiddleware()->deleteJson("/api/tenant/domains/{$rival->getKey()}")->assertNotFound();

        expect(Domain::query()->find($rival->getKey()))->not->toBeNull();
    });

    it('creates a pending domain and returns its DNS records with 201', function () {
        tenancy()->initialize(makeTenant('acme'));

        $response = $this->withoutMiddleware()->postJson('/api/tenant/domains', ['domain' => 'Shop.Example.com']);

        $response->assertCreated()
            ->assertJsonPath('data.domain', 'shop.example.com')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.dns_records.0.type', 'TXT');
        expect($response->json('data.dns_records.0.value'))->toStartWith('inventory-verify=');
    });

    it('returns 422 with a message when the domain is missing', function () {
        tenancy()->initialize(makeTenant('acme'));

        $this->withoutMiddleware()->postJson('/api/tenant/domains', [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['domain']]]]);
    });

    it('returns 204 when the tenant removes its own domain', function () {
        $acme = makeTenant('acme');
        $domain = activeCustomDomain($acme, 'shop.example.com');
        tenancy()->initialize($acme);

        $this->withoutMiddleware()->deleteJson("/api/tenant/domains/{$domain->getKey()}")->assertNoContent();

        expect(Domain::query()->find($domain->getKey()))->toBeNull();
    });

    it('filters the admin list by status and never lists platform addresses', function () {
        $acme = makeTenant('acme');
        activeCustomDomain($acme, 'live.example.com');
        activeCustomDomain($acme, 'dead.example.com', ['status' => Domain::STATUS_DISABLED]);

        $response = $this->withoutMiddleware()->getJson('/api/admin/domains?status=disabled');

        $response->assertOk();
        expect(collect($response->json('data'))->pluck('domain')->all())->toBe(['dead.example.com']);
    });
});
