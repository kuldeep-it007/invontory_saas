<?php

declare(strict_types=1);

namespace App\Reports;

use App\Auth\Roles;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The authorization context a report runs under (design "Reporting Engine" —
 * the `user` passed to `ReportQueryService.run(spec, user)`).
 *
 * Tenant scoping is PHYSICAL: a report always runs against the active tenant
 * database switched in by tenancy initialization, so there is no `tenant_id`
 * predicate and no way for a report to reach another tenant's rows (R28.10).
 * What this context adds is the ROLE scoping layer on top of that physical
 * isolation (R28.10):
 *
 *   - Company-side roles (Platform_Admin, Company_Admin, Company_Staff,
 *     Read-only) may read the whole tenant's data — the report is confined only
 *     by the tenant database they are already in.
 *   - A Distributor or Dealer principal may read ONLY its own account's data,
 *     so its reports are force-scoped to its own buyer identity regardless of
 *     what the request asked for.
 *
 * The context carries the principal's role names and, for a buyer principal,
 * the resolved (buyerType, buyerId) binding the service uses to confine the
 * spec. A buyer principal with no resolvable binding holds no readable scope,
 * and the service denies the report rather than leaking tenant-wide data.
 */
final class ReportAccessContext
{
    /**
     * @param  list<string>  $roles  The principal's role names (spatie).
     * @param  bool  $canViewReports  Whether the principal holds the `reports.view` ability (R8.4).
     * @param  string|null  $buyerType  The buyer morph type a buyer principal is bound to, if any.
     * @param  int|null  $buyerId  The buyer id a buyer principal is bound to, if any.
     */
    public function __construct(
        public readonly array $roles,
        public readonly bool $canViewReports,
        public readonly ?string $buyerType = null,
        public readonly ?int $buyerId = null,
    ) {}

    /**
     * The role names that may read tenant-wide report data (confined only by the
     * active tenant database). A Distributor/Dealer is deliberately excluded —
     * those principals are confined to their own account scope.
     *
     * @return list<string>
     */
    public static function tenantWideRoles(): array
    {
        return [
            Roles::PLATFORM_ADMIN,
            Roles::COMPANY_ADMIN,
            Roles::COMPANY_STAFF,
            Roles::READ_ONLY,
        ];
    }

    /**
     * Build an access context from an authenticated principal. Reads the
     * principal's spatie role names and the `reports.view` ability, and — when
     * the principal is a buyer-side user — its (buyerType, buyerId) binding.
     *
     * The binding is resolved defensively: a principal that directly IS a
     * Distributor/Dealer model carries its own key; otherwise an optional
     * `reportBuyerBinding()` hook on the principal (returning
     * `[morphType, id]`) is honored. This keeps the service decoupled from how
     * a tenant links its login users to buyer records, which varies and is not
     * yet a persisted schema relation.
     */
    public static function fromUser(Authenticatable $user): self
    {
        $roles = self::rolesOf($user);
        $canViewReports = self::abilityOf($user, 'reports.view');

        [$buyerType, $buyerId] = self::resolveBuyerBinding($user);

        return new self(
            roles: $roles,
            canViewReports: $canViewReports,
            buyerType: $buyerType,
            buyerId: $buyerId,
        );
    }

    /**
     * Whether this principal may read tenant-wide data (any company-side role).
     */
    public function hasTenantWideScope(): bool
    {
        foreach (self::tenantWideRoles() as $role) {
            if (in_array($role, $this->roles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether this principal is a buyer-side principal (Distributor/Dealer)
     * confined to its own account scope.
     */
    public function isBuyerScoped(): bool
    {
        return in_array(Roles::DISTRIBUTOR, $this->roles, true)
            || in_array(Roles::DEALER, $this->roles, true);
    }

    /**
     * Whether a buyer-scoped principal has a concrete (buyerType, buyerId)
     * binding to confine its reports to. A buyer principal with no binding has
     * no readable scope.
     */
    public function hasBuyerBinding(): bool
    {
        return $this->buyerType !== null && $this->buyerId !== null;
    }

    /**
     * The role names a given principal holds. Uses spatie's `getRoleNames()`
     * when available (the tenant {@see User} has it) and otherwise
     * reports no roles.
     *
     * @return list<string>
     */
    private static function rolesOf(Authenticatable $user): array
    {
        if (method_exists($user, 'getRoleNames')) {
            /** @var iterable<string> $names */
            $names = $user->getRoleNames();

            return array_values(array_map(static fn ($n): string => (string) $n, iterator_to_array($names, false)));
        }

        return [];
    }

    /**
     * Whether a principal holds a named ability. Uses spatie's permission
     * check when available; defaults to false when the principal cannot report
     * abilities.
     */
    private static function abilityOf(Authenticatable $user, string $ability): bool
    {
        if (method_exists($user, 'hasPermissionTo')) {
            try {
                return (bool) $user->hasPermissionTo($ability);
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    /**
     * Resolve the (buyerType, buyerId) a principal is bound to, if any.
     *
     * @return array{0: string|null, 1: int|null}
     */
    private static function resolveBuyerBinding(Authenticatable $user): array
    {
        if ($user instanceof Distributor || $user instanceof Dealer) {
            return [$user->getMorphClass(), (int) $user->getKey()];
        }

        if (method_exists($user, 'reportBuyerBinding')) {
            /** @var array{0?: string|null, 1?: int|null}|null $binding */
            $binding = $user->reportBuyerBinding();

            if (is_array($binding) && isset($binding[0], $binding[1])) {
                return [(string) $binding[0], (int) $binding[1]];
            }
        }

        return [null, null];
    }
}
