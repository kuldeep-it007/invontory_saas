<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Roles;
use App\Exceptions\QuotaExceededException;
use App\Mail\AccountCreatedMail;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Models\PlanLimit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates and manages buyer-side PANEL login users (R7, R8.6, R10.3).
 *
 * A Distributor or Dealer business record has no login of its own; this service
 * creates the {@see User} rows that a distributor/dealer logs in with to reach
 * its panel — view its own details and the orders it placed/received. Each such
 * user is:
 *
 *   • linked to exactly one party via `distributor_id` OR `dealer_id` (the FK
 *     the panel and the report engine scope on — see {@see User::reportBuyerBinding()});
 *   • assigned the matching spatie role ({@see Roles::DISTRIBUTOR} /
 *     {@see Roles::DEALER}) so it carries the buyer ability set (orders.create,
 *     the view abilities) and nothing more;
 *   • counted against the tenant `max_users` quota BEFORE insert (R5.1, R5.2);
 *   • welcomed with the queued {@see AccountCreatedMail} (R27.1).
 *
 * All work runs on the active tenant connection (physical isolation) and the
 * created user is audited via the {@see User} model's auditable trait (R34.1).
 */
class TenantUserService
{
    public function __construct(private readonly QuotaService $quota) {}

    /**
     * Create a login user that acts as the given Distributor's panel user
     * (R8.6, R10.3). The user is assigned the Distributor role and linked via
     * `distributor_id`.
     *
     * @param  array{name: string, email: string, password: string}  $data
     *
     * @throws QuotaExceededException When the `max_users` quota is reached (403).
     */
    public function createForDistributor(Distributor $distributor, array $data): User
    {
        return $this->create(
            data: $data,
            role: Roles::DISTRIBUTOR,
            link: ['distributor_id' => (int) $distributor->getKey()],
            accountName: (string) $distributor->name,
            buyerType: 'distributor',
        );
    }

    /**
     * Create a login user that acts as the given Dealer's panel user (R8.6,
     * R10.3). The user is assigned the Dealer role and linked via `dealer_id`.
     *
     * @param  array{name: string, email: string, password: string}  $data
     *
     * @throws QuotaExceededException When the `max_users` quota is reached (403).
     */
    public function createForDealer(Dealer $dealer, array $data): User
    {
        return $this->create(
            data: $data,
            role: Roles::DEALER,
            link: ['dealer_id' => (int) $dealer->getKey()],
            accountName: (string) $dealer->name,
            buyerType: 'dealer',
        );
    }

    /**
     * Administratively reset a panel user's login password (R7.3, R8.6).
     *
     * Used when the Company resets one of its distributors' panel users, or a
     * Distributor resets one of its dealers' panel users (ownership is asserted
     * by the controller before this runs). The new secret is assigned to the
     * `password` attribute and re-hashed by the User model's `hashed` cast on
     * save, so a plaintext value is never persisted. The write runs on the
     * active tenant connection and is audited via the User model's auditable
     * trait (R34.1).
     */
    public function resetPassword(User $user, string $password): User
    {
        return DB::connection('tenant')->transaction(function () use ($user, $password): User {
            $user->forceFill(['password' => $password])->save();

            return $user->refresh();
        });
    }

    /**
     * Shared creation path: quota-check, create the user with the party link,
     * assign the role, invalidate the users count, and queue the welcome mail.
     *
     * @param  array{name: string, email: string, password: string}  $data
     * @param  array<string, int>  $link  Exactly one of ['distributor_id' => id] | ['dealer_id' => id].
     */
    private function create(array $data, string $role, array $link, string $accountName, string $buyerType): User
    {
        // Enforce the whole-tenant user quota BEFORE creating (R5.1, R5.2).
        $this->quota->assertAllows(PlanLimit::MAX_USERS);

        $user = DB::connection('tenant')->transaction(function () use ($data, $role, $link): User {
            // Keep spatie's permission cache fresh on the tenant connection so
            // the seeded role is resolvable before we assign it.
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $user = User::create(array_merge([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'], // hashed by the `hashed` cast (R7.3)
            ], $link));

            // Roles are seeded under the `web` guard. A Sanctum-authenticated
            // request sets the active guard to `sanctum`, so resolve the role
            // explicitly on `web` (matching the User model's pinned guard) to
            // avoid a RoleDoesNotExist for guard `sanctum`.
            $user->assignRole(Role::findByName($role, 'web'));

            return $user;
        });

        // A new counted user exists: invalidate the cached count (R5.6).
        $this->quota->invalidate(PlanLimit::MAX_USERS);

        // Welcome the new panel user (R27.1); queued so it never blocks the
        // create response, and sent under the tenant's own sender identity.
        Mail::to($user->email)->queue(new AccountCreatedMail(
            accountName: $accountName,
            buyerType: $buyerType,
        ));

        return $user->refresh();
    }
}
