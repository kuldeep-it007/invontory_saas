<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\TenantAuditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Tenant-scoped user.
 *
 * Tenant users live in each tenant's own database (physical isolation, no
 * `tenant_id` column); the active tenant connection resolves authentication,
 * tokens, and RBAC roles/permissions against that tenant's tables (R7, R8).
 *
 * - {@see HasApiTokens} issues/validates Sanctum tokens on login (R7.1).
 * - {@see HasRoles} binds spatie roles/permissions for authorization (R8.1, R8.2).
 * - {@see TenantAuditable} records create/update/delete of this tenant-owned
 *   record in the tenant `audit_logs` table with actor, timestamp, and record
 *   identity (R34.1); the password hash is excluded from audited values below.
 * - Passwords are stored hashed (bcrypt/argon2) via the `hashed` cast (R7.3).
 */
#[Fillable(['name', 'email', 'password', 'distributor_id', 'dealer_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements Auditable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, TenantAuditable;

    /**
     * Attributes excluded from audited old/new value snapshots.
     *
     * The password hash and remember token are credentials and must never be
     * copied into the audit trail (R7.3, R34.1).
     *
     * @var array<int, string>
     */
    protected $auditExclude = [
        'password',
        'remember_token',
    ];

    /**
     * Pin the spatie guard for this model to `web` (R8).
     *
     * Roles/permissions are seeded under the `web` guard (the app's default).
     * When a request authenticates via Sanctum, Laravel's active guard becomes
     * `sanctum`, and without this pin spatie infers `sanctum` as the model's
     * guard and rejects assigning/checking a `web`-guard role
     * (GuardDoesNotMatch). Pinning the model to `web` makes every role/ability
     * operation use the guard the roles were created under, regardless of how
     * the current request authenticated (token vs session). This keeps RBAC and
     * the abilities reported to the frontend (`GET /api/me`) consistent.
     *
     * @var string
     */
    protected $guard_name = 'web';

    /**
     * Force spatie to treat this model's RBAC guard as `web` (R8).
     *
     * `Spatie\Permission\Guard::getNames()` checks for a `guardName()` method
     * FIRST, before falling back to the active auth guard. Token-authenticated
     * requests set the active guard to `sanctum`; without this method spatie
     * infers `sanctum` and rejects the `web`-guarded roles with
     * GuardDoesNotMatch (e.g. during tenant provisioning). Returning `web`
     * unconditionally keeps every role/ability operation on the guard the roles
     * are seeded under, regardless of how the request authenticated.
     */
    public function guardName(): string
    {
        return 'web';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The flat list of ability names this user holds, aggregated across all of
     * the user's roles (and any directly assigned permissions).
     *
     * Backs the `abilities` field of `GET /api/me` (R8.5) and reflects the same
     * permission set the RBAC middleware authorizes against (R8.2).
     *
     * @return list<string>
     */
    public function abilities(): array
    {
        return $this->getAllPermissions()
            ->pluck('name')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * The Distributor this user logs in as, when it is a distributor panel user
     * (R8.6, R10.3). Null for company-side and dealer users.
     *
     * @return BelongsTo<Distributor, $this>
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    /**
     * The Dealer this user logs in as, when it is a dealer panel user (R8.6,
     * R10.3). Null for company-side and distributor users.
     *
     * @return BelongsTo<Dealer, $this>
     */
    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    /**
     * The buyer party (Distributor or Dealer) this user acts as, if any. Null
     * for a company-side user that represents no single buyer.
     */
    public function party(): Distributor|Dealer|null
    {
        if ($this->dealer_id !== null) {
            return $this->dealer;
        }

        if ($this->distributor_id !== null) {
            return $this->distributor;
        }

        return null;
    }

    /**
     * The (morphType, id) buyer binding the reporting engine and the panel use
     * to confine this user to its OWN data (R28.10). A user linked to a Dealer
     * binds to that Dealer; one linked to a Distributor binds to that
     * Distributor; a company-side user returns null (no buyer scope).
     *
     * This is the optional hook {@see App\Reports\ReportAccessContext} and
     * {@see App\Services\PartyImpersonationService} read, so adding the party
     * link lights up report scoping and impersonation automatically.
     *
     * @return array{0: string, 1: int}|null
     */
    public function reportBuyerBinding(): ?array
    {
        if ($this->dealer_id !== null) {
            return [(new Dealer)->getMorphClass(), (int) $this->dealer_id];
        }

        if ($this->distributor_id !== null) {
            return [(new Distributor)->getMorphClass(), (int) $this->distributor_id];
        }

        return null;
    }
}
