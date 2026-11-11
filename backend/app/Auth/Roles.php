<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * Canonical RBAC definition: the six platform roles, the ability (permission)
 * catalogue, and the role → abilities map (R8.1).
 *
 * This is the single source of truth consumed by the tenant role seeder
 * (`RoleSeeder`), the authorization layer, and tests. Keeping it in one place
 * guarantees the seeded permissions and the abilities reported by
 * `GET /api/me` (R8.5) stay in lockstep with what the RBAC middleware
 * authorizes against (R8.2).
 *
 * Ability naming convention: `<resource>.<action>`. Actions are split into a
 * read set (`view`) and a mutation set (`create`, `update`, `delete`,
 * `confirm`, ...). The Read-only role is granted ONLY the view abilities so it
 * can see dashboards/reports but is denied every mutation (R8.4).
 */
final class Roles
{
    public const PLATFORM_ADMIN = 'Platform_Admin';

    public const COMPANY_ADMIN = 'Company_Admin';

    public const COMPANY_STAFF = 'Company_Staff';

    public const DISTRIBUTOR = 'Distributor';

    public const DEALER = 'Dealer';

    public const READ_ONLY = 'Read-only';

    /**
     * All six roles supported by the platform (R8.1).
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::PLATFORM_ADMIN,
            self::COMPANY_ADMIN,
            self::COMPANY_STAFF,
            self::DISTRIBUTOR,
            self::DEALER,
            self::READ_ONLY,
        ];
    }

    /**
     * Read (view) abilities. These are the ONLY abilities the Read-only role
     * receives; every role that can read also receives these (R8.4).
     *
     * @return list<string>
     */
    public static function viewAbilities(): array
    {
        return [
            'dashboard.view',
            'reports.view',
            'catalog.view',
            'inventory.view',
            'orders.view',
            'payments.view',
            'distributors.view',
            'dealers.view',
            'users.view',
            'settings.view',
        ];
    }

    /**
     * Mutation abilities (create/update/delete/state transitions). Granting any
     * of these to a role makes that role able to change data; withholding them
     * all (as for Read-only) denies every mutation (R8.4).
     *
     * @return list<string>
     */
    public static function mutationAbilities(): array
    {
        return [
            'catalog.manage',
            'inventory.adjust',
            'orders.create',
            'orders.confirm',
            'orders.cancel',
            'payments.record',
            'distributors.manage',
            'dealers.manage',
            'users.manage',
            'settings.manage',
        ];
    }

    /**
     * The complete ability catalogue (view + mutation).
     *
     * @return list<string>
     */
    public static function allAbilities(): array
    {
        return array_values(array_merge(self::viewAbilities(), self::mutationAbilities()));
    }

    /**
     * Role → abilities map (R8.1, R8.2).
     *
     * - Platform_Admin / Company_Admin: full ability set.
     * - Company_Staff: processes orders, records payments, adjusts stock, views.
     * - Distributor: manages its dealers, places/views orders, views catalog.
     * - Dealer: places/views orders, views catalog.
     * - Read-only: view abilities ONLY — all mutations denied (R8.4).
     *
     * @return array<string, list<string>>
     */
    public static function abilitiesByRole(): array
    {
        $view = self::viewAbilities();

        return [
            self::PLATFORM_ADMIN => self::allAbilities(),

            self::COMPANY_ADMIN => self::allAbilities(),

            self::COMPANY_STAFF => array_values(array_merge($view, [
                'inventory.adjust',
                'orders.create',
                'orders.confirm',
                'orders.cancel',
                'payments.record',
            ])),

            // A Distributor places its own orders AND drives the lifecycle of
            // the orders its dealers place against ITS OWN stock (R21). The
            // seller-ownership check in OrderLifecycleAuthorizer confines
            // `orders.confirm`/`orders.cancel` to orders this distributor sells,
            // so it can never touch Company-stock orders.
            self::DISTRIBUTOR => array_values(array_merge($view, [
                'orders.create',
                'orders.confirm',
                'orders.cancel',
                'dealers.manage',
            ])),

            self::DEALER => array_values(array_merge($view, [
                'orders.create',
            ])),

            // Read-only: views permitted, every mutation denied (R8.4).
            self::READ_ONLY => $view,
        ];
    }
}
