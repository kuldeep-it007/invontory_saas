<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Roles;
use App\Models\Dealer;
use App\Models\Distributor;
use App\Models\User;
use App\Support\AuditRecorder;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Tenant-side party impersonation (R8.6, R34.3).
 *
 * Distinct from {@see ImpersonationService} (which is a Platform_Admin swapping
 * into a tenant USER account across the central boundary). This service lets a
 * tenant actor "act as" a BUYER PARTY — a Distributor or a Dealer — so they can
 * browse that party's catalog and place orders on its behalf. Parties are
 * business records (not user logins), so impersonation here establishes a
 * BUYER CONTEXT (buyer_type + buyer_id) that the cart/order flow already
 * consumes, rather than issuing a new auth token.
 *
 * ## Who may impersonate whom (hierarchy)
 *
 *   • Company_Admin / Company_Staff (the Company) → ANY Distributor, and ANY
 *     Dealer directly (company → dealer is allowed) (R10.1, R10.2).
 *   • Distributor → only ITS OWN Dealers (a Dealer belongs to exactly one
 *     Distributor — R10.3).
 *
 * Any other actor/target combination is rejected with a 403. Every started
 * session is recorded in the tenant audit log with the actor and target
 * (R34.3).
 */
class PartyImpersonationService
{
    /** Audit event name recorded for a party impersonation session (R34.3). */
    public const AUDIT_EVENT = 'party_impersonation';

    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * Authorize and begin impersonating a buyer party, returning the acting-as
     * context the frontend holds for the session.
     *
     * @param  User  $actor     The authenticated tenant user starting the session.
     * @param  string  $targetType  `distributor` or `dealer`.
     * @param  int  $targetId   The target party id in the active tenant database.
     * @return array{buyer_type: string, buyer_id: int, label: string, kind: string, parent: array{id:int,name:string}|null}
     *
     * @throws AccessDeniedHttpException When the actor may not impersonate the target.
     */
    public function impersonate(User $actor, string $targetType, int $targetId): array
    {
        $target = $this->resolveTarget($targetType, $targetId);

        $this->assertAllowed($actor, $target);

        $this->audit->record(
            self::AUDIT_EVENT,
            $actor,
            [
                'actor_email' => $actor->email,
                'actor_roles' => $actor->getRoleNames()->values()->all(),
                'target_type' => $targetType,
                'target_id' => $targetId,
                'target_name' => $target->name,
            ],
            reason: sprintf('Impersonating %s "%s" (#%d)', $targetType, $target->name, $targetId),
        );

        return $this->contextFor($targetType, $target);
    }

    /**
     * The set of parties the actor is allowed to impersonate, for building the
     * frontend's impersonation menu.
     *
     * @return array{distributors: list<array{id:int,name:string}>, dealers: list<array{id:int,name:string,distributor_id:int}>}
     */
    public function targetsFor(User $actor): array
    {
        if ($this->isCompanyActor($actor)) {
            // Company → every distributor and every dealer (company → dealer).
            return [
                'distributors' => Distributor::query()
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Distributor $d) => ['id' => (int) $d->id, 'name' => $d->name])
                    ->all(),
                'dealers' => Dealer::query()
                    ->orderBy('name')
                    ->get(['id', 'name', 'distributor_id'])
                    ->map(fn (Dealer $d) => [
                        'id' => (int) $d->id,
                        'name' => $d->name,
                        'distributor_id' => (int) $d->distributor_id,
                    ])
                    ->all(),
            ];
        }

        // A Distributor-role actor: only its own dealers. The actor must carry a
        // `distributor_id` linking it to its party record; without that link we
        // cannot scope safely, so we surface nothing.
        $distributorId = $this->actorDistributorId($actor);
        if ($distributorId === null) {
            return ['distributors' => [], 'dealers' => []];
        }

        return [
            'distributors' => [],
            'dealers' => Dealer::query()
                ->where('distributor_id', $distributorId)
                ->orderBy('name')
                ->get(['id', 'name', 'distributor_id'])
                ->map(fn (Dealer $d) => [
                    'id' => (int) $d->id,
                    'name' => $d->name,
                    'distributor_id' => (int) $d->distributor_id,
                ])
                ->all(),
        ];
    }

    /**
     * Enforce the impersonation hierarchy (R10.1–R10.3).
     */
    private function assertAllowed(User $actor, Model $target): void
    {
        // Company actors (Company_Admin / Company_Staff) may impersonate any
        // Distributor or any Dealer directly.
        if ($this->isCompanyActor($actor)) {
            return;
        }

        // Otherwise the actor must be a Distributor acting on its OWN dealer.
        if ($actor->hasRole(Roles::DISTRIBUTOR) && $target instanceof Dealer) {
            $actorDistributorId = $this->actorDistributorId($actor);
            if ($actorDistributorId !== null && (int) $target->distributor_id === $actorDistributorId) {
                return;
            }
        }

        throw new AccessDeniedHttpException('You are not permitted to impersonate this party.');
    }

    /**
     * Whether the actor represents the Company (Company_Admin or Company_Staff),
     * which may impersonate any party including a Dealer directly.
     */
    private function isCompanyActor(User $actor): bool
    {
        return $actor->hasRole(Roles::COMPANY_ADMIN) || $actor->hasRole(Roles::COMPANY_STAFF);
    }

    /**
     * The distributor id a Distributor-role actor is bound to, if any. A tenant
     * user may carry a `distributor_id` linking it to its party record; when it
     * does not, the actor can impersonate none (handled by the caller).
     */
    private function actorDistributorId(User $actor): ?int
    {
        $id = $actor->distributor_id ?? null;

        return $id !== null ? (int) $id : null;
    }

    /**
     * Load the target party model from the active tenant database.
     */
    private function resolveTarget(string $targetType, int $targetId): Model
    {
        return match ($targetType) {
            'distributor' => Distributor::query()->findOrFail($targetId),
            'dealer' => Dealer::query()->with('distributor')->findOrFail($targetId),
            default => throw new AccessDeniedHttpException('Unsupported impersonation target.'),
        };
    }

    /**
     * Shape the acting-as context returned to the client.
     *
     * @return array{buyer_type: string, buyer_id: int, label: string, kind: string, parent: array{id:int,name:string}|null}
     */
    private function contextFor(string $targetType, Model $target): array
    {
        $parent = null;
        if ($target instanceof Dealer && $target->distributor !== null) {
            $parent = ['id' => (int) $target->distributor->id, 'name' => $target->distributor->name];
        }

        return [
            'buyer_type' => $targetType,
            'buyer_id' => (int) $target->getKey(),
            'label' => (string) $target->name,
            'kind' => $targetType,
            'parent' => $parent,
        ];
    }
}
