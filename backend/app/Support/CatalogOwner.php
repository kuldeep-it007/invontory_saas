<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Concerns\HasCatalogOwner;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Who owns a piece of catalogue data, and who may manage it.
 *
 * Catalogue rows (categories, collections, colours, products, discounts) carry
 * `owner_distributor_id`: NULL for the Company, otherwise the owning
 * Distributor. The "owner key" is the same value with 0 for the Company, which
 * is what the per-owner unique indexes use.
 *
 *  - A Company user (no distributor/dealer link) manages owner key 0.
 *  - A Distributor user manages the rows of its own distributor.
 *  - Dealers own no catalogue and may not manage one.
 *
 * The owner is always derived from the authenticated user, never from input.
 */
final class CatalogOwner
{
    /** Owner key of Company-owned catalogue rows. */
    public const COMPANY = 0;

    /** The current authenticated user, without forcing a guard to resolve one. */
    public static function currentUser(): ?User
    {
        $user = Auth::hasUser() ? Auth::user() : null;

        return $user instanceof User ? $user : null;
    }

    /** True for a Company-side user (not linked to a distributor or dealer). */
    public static function isCompanyUser(?User $user): bool
    {
        return $user !== null && $user->distributor_id === null && $user->dealer_id === null;
    }

    /** The distributor a Distributor user acts for, or null for anyone else. */
    public static function distributorIdOf(?User $user): ?int
    {
        if ($user === null || $user->dealer_id !== null || $user->distributor_id === null) {
            return null;
        }

        return (int) $user->distributor_id;
    }

    /**
     * The owner key the user manages: 0 for the Company, the distributor id for
     * a Distributor user.
     *
     * @throws AccessDeniedHttpException for dealers (they own no catalogue).
     */
    public static function keyFor(User $user): int
    {
        if (self::isCompanyUser($user)) {
            return self::COMPANY;
        }

        $distributorId = self::distributorIdOf($user);
        if ($distributorId === null) {
            throw new AccessDeniedHttpException('Only the Company or a Distributor can manage a catalogue.');
        }

        return $distributorId;
    }

    /**
     * Validation rule: the id must be a row of $table owned by the user, so a
     * distributor can only build on its own categories/collections/colours/products.
     */
    public static function ownedExistsRule(?User $user, string $table): Exists
    {
        return Rule::exists("tenant.{$table}", 'id')->where('owner_key', self::ruleKey($user));
    }

    /**
     * Validation rule: $column must be unique among the user's own rows of
     * $table (SKU / code are unique per owner).
     */
    public static function ownedUniqueRule(?User $user, string $table, string $column, ?int $ignoreId = null): Unique
    {
        $rule = Rule::unique("tenant.{$table}", $column)->where('owner_key', self::ruleKey($user));

        return $ignoreId === null ? $rule : $rule->ignore($ignoreId);
    }

    /**
     * Filter for a catalogue list (a query on a {@see HasCatalogOwner} model):
     *
     *  - `owner=mine` (management screens): the caller's own rows;
     *  - otherwise the orderable network catalogue: the Company's rows for a
     *    Company user (or no user), everyone else's rows for a Distributor
     *    (it cannot buy its own products), every row for a Dealer.
     *
     * @return Closure(Builder<Model>): void
     */
    public static function listFilter(?User $user, mixed $ownerParam): Closure
    {
        return function (Builder $query) use ($user, $ownerParam): void {
            $column = $query->getModel()->qualifyColumn('owner_key');

            if ($ownerParam === 'mine' && $user !== null) {
                $query->where($column, self::keyFor($user));

                return;
            }
            if ($user === null || self::isCompanyUser($user)) {
                $query->where($column, self::COMPANY);

                return;
            }
            $distributorId = self::distributorIdOf($user);
            if ($distributorId !== null) {
                $query->where($column, '!=', $distributorId);
            }
        };
    }

    /**
     * Owner key for validation rules. Without a user (rules built outside an
     * authenticated request) this is the Company; catalogue routes always
     * require authentication.
     */
    private static function ruleKey(?User $user): int
    {
        return $user === null ? self::COMPANY : self::keyFor($user);
    }

    /** `owner_distributor_id` value for rows created by the user. */
    public static function distributorColumnFor(User $user): ?int
    {
        $key = self::keyFor($user);

        return $key === self::COMPANY ? null : $key;
    }

    /** Owner key of a catalogue row. */
    public static function keyOf(Model $model): int
    {
        return (int) ($model->getAttribute('owner_distributor_id') ?? self::COMPANY);
    }

    /**
     * Reject managing a row the user does not own. Answers 404 rather than 403
     * so another owner's ids are not confirmed to exist.
     *
     * @throws NotFoundHttpException
     */
    public static function assertManages(User $user, Model $model): void
    {
        if (self::keyOf($model) !== self::keyFor($user)) {
            throw new NotFoundHttpException('Not found.');
        }
    }
}
